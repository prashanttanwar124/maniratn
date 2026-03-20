<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Karigar;
use App\Models\MetalTransaction;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Services\LedgerImpactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LedgerController extends Controller
{
    private const EDITABLE_ENTRY_TYPES = [
        'ISSUE_GOLD',
        'RECEIVE_GOLD',
        'ISSUE_SILVER',
        'RECEIVE_SILVER',
        'PAY_CASH',
        'RECEIVE_CASH',
    ];

    public function show($type, $id)
    {
        $modelClass = match ($type) {
            'suppliers' => Supplier::class,
            'karigars'  => Karigar::class,
            'customers' => Customer::class,
            default     => abort(404),
        };

        $party = $modelClass::findOrFail($id);

        $cashTxns = Transaction::where('transactable_type', $modelClass)
            ->where('transactable_id', $id)
            ->get()
            ->toBase()
            ->map(function ($txn) {
                $entryTypeCode = $txn->entry_type_code ?: $this->inferCashEntryType($txn);

                return [
                    'id' => 'cash-' . $txn->id,
                    'row_id' => $txn->id,
                    'date' => $txn->date,
                    'description' => $txn->description,
                    'category' => 'CASH',
                    'type' => $txn->type, // PAYMENT / RECEIPT
                    'amount' => (float) $txn->amount,
                    'purity' => null,
                    'payment_method' => $txn->payment_method,
                    'entry_source' => $txn->entry_source ?? 'SYSTEM',
                    'entry_type_code' => $entryTypeCode,
                    'is_editable' => $this->isEditableCashTransaction($txn, $entryTypeCode),
                    'created_at' => optional($txn->created_at)->toDateTimeString(),
                    'sort_date' => strtotime((string) $txn->date) ?: 0,
                    'sort_created_at' => optional($txn->created_at)->timestamp ?? 0,
                ];
            });

        $metalTxns = MetalTransaction::where('party_type', $modelClass)
            ->where('party_id', $id)
            ->get()
            ->toBase()
            ->map(function ($txn) {
                $entryTypeCode = $txn->entry_type_code ?: $this->inferMetalEntryType($txn);

                return [
                    'id' => 'metal-' . $txn->id,
                    'row_id' => $txn->id,
                    'date' => $txn->date,
                    'description' => $txn->description,
                    'category' => 'METAL',
                    'metal_type' => $txn->metal_type ?? 'GOLD',
                    'type' => $txn->type, // ISSUE / RECEIPT
                    'amount' => (float) $txn->gross_weight,
                    'purity' => $this->calculatePurityFromFineWeight((float) $txn->gross_weight, (float) $txn->fine_weight),
                    'entry_source' => $txn->entry_source ?? 'SYSTEM',
                    'entry_type_code' => $entryTypeCode,
                    'is_editable' => $this->isEditableMetalTransaction($txn, $entryTypeCode),
                    'created_at' => optional($txn->created_at)->toDateTimeString(),
                    'sort_date' => strtotime((string) $txn->date) ?: 0,
                    'sort_created_at' => optional($txn->created_at)->timestamp ?? 0,
                ];
            });

        $mergedTransactions = $cashTxns
            ->merge($metalTxns)
            ->sort(function (array $left, array $right) {
                $dateCompare = ($left['sort_date'] ?? 0) <=> ($right['sort_date'] ?? 0);
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                $createdCompare = ($left['sort_created_at'] ?? 0) <=> ($right['sort_created_at'] ?? 0);
                if ($createdCompare !== 0) {
                    return $createdCompare;
                }

                return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
            })
            ->values();

        return Inertia::render('ledger/Show', [
            'party' => $party,
            'party_type_class' => $modelClass,
            'transactions' => $mergedTransactions,
        ]);
    }

    public function updateEntry(Request $request, string $category, int $id)
    {
        $validated = $request->validate([
            'party_type' => [
                'required',
                Rule::in([
                    Supplier::class,
                    Karigar::class,
                    Customer::class,
                ]),
            ],
            'party_id' => ['required', 'integer'],
            'entry_type' => ['required', Rule::in(self::EDITABLE_ENTRY_TYPES)],
            'gold_weight' => ['nullable', 'numeric', 'min:0.001'],
            'cash_amount' => ['nullable', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', Rule::in(['CASH', 'BANK', 'UPI'])],
            'purity' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'date' => ['required', 'date'],
        ]);

        $partyType = $validated['party_type'];
        $partyId = $validated['party_id'];

        if (! $partyType::where('id', $partyId)->exists()) {
            return back()->withErrors([
                'party_id' => 'Selected party was not found.',
            ]);
        }

        $entryType = $validated['entry_type'];

        if (in_array($entryType, ['ISSUE_GOLD', 'RECEIVE_GOLD', 'ISSUE_SILVER', 'RECEIVE_SILVER'], true) && empty($validated['gold_weight'])) {
            return back()->withErrors([
                'gold_weight' => 'Metal weight is required for this entry type.',
            ]);
        }

        if (in_array($entryType, ['ISSUE_GOLD', 'RECEIVE_GOLD', 'ISSUE_SILVER', 'RECEIVE_SILVER'], true) && empty($validated['purity'])) {
            return back()->withErrors([
                'purity' => 'Purity is required for metal entries.',
            ]);
        }

        if (in_array($entryType, ['PAY_CASH', 'RECEIVE_CASH'], true) && empty($validated['cash_amount'])) {
            return back()->withErrors([
                'cash_amount' => 'Cash amount is required for this entry type.',
            ]);
        }

        if (in_array($entryType, ['PAY_CASH', 'RECEIVE_CASH'], true) && empty($validated['payment_method'])) {
            return back()->withErrors([
                'payment_method' => 'Payment method is required for this entry type.',
            ]);
        }

        try {
            DB::transaction(function () use ($category, $id, $partyType, $partyId, $entryType, $validated) {
                if ($category === 'cash') {
                    $transaction = Transaction::findOrFail($id);

                    if (
                        $transaction->transactable_type !== $partyType
                        || (int) $transaction->transactable_id !== $partyId
                    ) {
                        abort(404);
                    }

                    if (! $this->isEditableCashTransaction($transaction, $transaction->entry_type_code ?: $this->inferCashEntryType($transaction))) {
                        throw ValidationException::withMessages([
                            'entry' => 'Only manual non-invoice cash entries can be edited.',
                        ]);
                    }

                    LedgerImpactService::reverseCashTransaction($transaction);

                    $transaction->update([
                        'type' => $entryType === 'PAY_CASH' ? 'PAYMENT' : 'RECEIPT',
                        'amount' => (float) $validated['cash_amount'],
                        'payment_method' => $validated['payment_method'] ?? 'CASH',
                        'description' => trim($validated['description'] ?? '') ?: $this->defaultDescriptionForEntryType($entryType, null),
                        'date' => $validated['date'],
                        'entry_type_code' => $entryType,
                    ]);

                    LedgerImpactService::applyCashTransaction($transaction);
                } else {
                    $transaction = MetalTransaction::findOrFail($id);

                    if (
                        $transaction->party_type !== $partyType
                        || (int) $transaction->party_id !== $partyId
                    ) {
                        abort(404);
                    }

                    if (! $this->isEditableMetalTransaction($transaction, $transaction->entry_type_code ?: $this->inferMetalEntryType($transaction))) {
                        throw ValidationException::withMessages([
                            'entry' => 'Only manual non-conversion gold entries can be edited.',
                        ]);
                    }

                    LedgerImpactService::reverseMetalTransaction($transaction);

                    $goldWeight = (float) $validated['gold_weight'];
                    $purity = (float) $validated['purity'];

                    $transaction->update([
                        'type' => in_array($entryType, ['ISSUE_GOLD', 'ISSUE_SILVER'], true) ? 'ISSUE' : 'RECEIPT',
                        'metal_type' => str_contains($entryType, 'SILVER') ? 'SILVER' : 'GOLD',
                        'gross_weight' => $goldWeight,
                        'fine_weight' => $this->makeFineWeight($goldWeight, $purity),
                        'description' => trim($validated['description'] ?? '') ?: $this->defaultDescriptionForEntryType($entryType, $purity),
                        'date' => $validated['date'],
                        'entry_type_code' => $entryType,
                    ]);

                    LedgerImpactService::applyMetalTransaction($transaction);
                }
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withErrors([
                'entry' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Ledger entry updated successfully.');
    }

    public function storeEntry(Request $request)
    {
        $validated = $request->validate([
            'party_type' => [
                'required',
                Rule::in([
                    Supplier::class,
                    Karigar::class,
                    Customer::class,
                ]),
            ],
            'party_id' => ['required', 'integer'],
            'entry_type' => [
                'required',
                Rule::in([
                    'ISSUE_GOLD',
                    'RECEIVE_GOLD',
                    'ISSUE_SILVER',
                    'RECEIVE_SILVER',
                    'PAY_CASH',
                    'RECEIVE_CASH',
                    'GOLD_TO_CASH',
                    'CASH_TO_GOLD',
                    'SILVER_TO_CASH',
                    'CASH_TO_SILVER',
                ]),
            ],
            'gold_weight' => ['nullable', 'numeric', 'min:0.001'],
            'cash_amount' => ['nullable', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', Rule::in(['CASH', 'BANK', 'UPI'])],
            'rate' => ['nullable', 'numeric', 'min:0.01'],
            'purity' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'date' => ['required', 'date'],
        ]);

        $partyType = $validated['party_type'];
        $partyId = $validated['party_id'];

        $partyExists = $partyType::where('id', $partyId)->exists();

        if (! $partyExists) {
            return back()->withErrors([
                'party_id' => 'Selected party was not found.',
            ]);
        }

        $entryType = $validated['entry_type'];
        $goldWeight = isset($validated['gold_weight']) ? (float) $validated['gold_weight'] : null;
        $cashAmount = isset($validated['cash_amount']) ? (float) $validated['cash_amount'] : null;
        $rate = isset($validated['rate']) ? (float) $validated['rate'] : null;
        $purity = isset($validated['purity']) ? (float) $validated['purity'] : null;
        $description = trim($validated['description'] ?? '');
        $date = $validated['date'];

        if (in_array($entryType, ['ISSUE_GOLD', 'RECEIVE_GOLD', 'ISSUE_SILVER', 'RECEIVE_SILVER', 'GOLD_TO_CASH', 'SILVER_TO_CASH'], true) && ! $goldWeight) {
            return back()->withErrors([
                'gold_weight' => 'Metal weight is required for this entry type.',
            ]);
        }

        if (in_array($entryType, ['PAY_CASH', 'RECEIVE_CASH', 'CASH_TO_GOLD', 'CASH_TO_SILVER'], true) && ! $cashAmount) {
            return back()->withErrors([
                'cash_amount' => 'Cash amount is required for this entry type.',
            ]);
        }

        if (in_array($entryType, ['PAY_CASH', 'RECEIVE_CASH'], true) && empty($validated['payment_method'])) {
            return back()->withErrors([
                'payment_method' => 'Payment method is required for this entry type.',
            ]);
        }

        if (in_array($entryType, ['ISSUE_GOLD', 'RECEIVE_GOLD', 'ISSUE_SILVER', 'RECEIVE_SILVER', 'GOLD_TO_CASH', 'CASH_TO_GOLD', 'SILVER_TO_CASH', 'CASH_TO_SILVER'], true) && ! $purity) {
            return back()->withErrors([
                'purity' => 'Purity is required for metal entries.',
            ]);
        }

        if ($entryType === 'GOLD_TO_CASH' && (! $rate || ! $goldWeight)) {
            return back()->withErrors([
                'rate' => 'Rate is required for gold to cash adjustment.',
            ]);
        }

        if ($entryType === 'SILVER_TO_CASH' && (! $rate || ! $goldWeight)) {
            return back()->withErrors([
                'rate' => 'Rate is required for silver to cash adjustment.',
            ]);
        }

        if ($entryType === 'CASH_TO_GOLD' && (! $rate || ! $cashAmount)) {
            return back()->withErrors([
                'rate' => 'Rate is required for cash to gold adjustment.',
            ]);
        }

        if ($entryType === 'CASH_TO_SILVER' && (! $rate || ! $cashAmount)) {
            return back()->withErrors([
                'rate' => 'Rate is required for cash to silver adjustment.',
            ]);
        }

        try {
            DB::transaction(function () use (
                $partyType,
                $partyId,
                $entryType,
                $goldWeight,
                $cashAmount,
                $rate,
                $purity,
                $description,
                $date,
                $validated
            ) {
                $makeFineWeight = function (float $grossWeight, float $purityPercent): float {
                    return $this->makeFineWeight($grossWeight, $purityPercent);
                };

                switch ($entryType) {
                case 'ISSUE_GOLD':
                case 'ISSUE_SILVER':
                    $metalTransaction = MetalTransaction::create([
                        'party_type' => $partyType,
                        'party_id' => $partyId,
                        'type' => 'ISSUE',
                        'metal_type' => $entryType === 'ISSUE_SILVER' ? 'SILVER' : 'GOLD',
                        'gross_weight' => $goldWeight,
                        'fine_weight' => $makeFineWeight($goldWeight, $purity),
                        'description' => $description ?: $this->defaultDescriptionForEntryType($entryType, $purity),
                        'date' => $date,
                        'entry_source' => 'MANUAL',
                        'entry_type_code' => $entryType,
                    ]);
                    LedgerImpactService::applyMetalTransaction($metalTransaction);
                    break;

                case 'RECEIVE_GOLD':
                case 'RECEIVE_SILVER':
                    $metalTransaction = MetalTransaction::create([
                        'party_type' => $partyType,
                        'party_id' => $partyId,
                        'type' => 'RECEIPT',
                        'metal_type' => $entryType === 'RECEIVE_SILVER' ? 'SILVER' : 'GOLD',
                        'gross_weight' => $goldWeight,
                        'fine_weight' => $makeFineWeight($goldWeight, $purity),
                        'description' => $description ?: $this->defaultDescriptionForEntryType($entryType, $purity),
                        'date' => $date,
                        'entry_source' => 'MANUAL',
                        'entry_type_code' => $entryType,
                    ]);
                    LedgerImpactService::applyMetalTransaction($metalTransaction);
                    break;

                case 'PAY_CASH':
                    $cashTransaction = Transaction::create([
                        'transactable_type' => $partyType,
                        'transactable_id' => $partyId,
                        'type' => 'PAYMENT',
                        'amount' => $cashAmount,
                        'payment_method' => $validated['payment_method'],
                        'description' => $description ?: 'Cash paid',
                        'date' => $date,
                        'entry_source' => 'MANUAL',
                        'entry_type_code' => 'PAY_CASH',
                    ]);
                    LedgerImpactService::applyCashTransaction($cashTransaction);
                    break;

                case 'RECEIVE_CASH':
                    $cashTransaction = Transaction::create([
                        'transactable_type' => $partyType,
                        'transactable_id' => $partyId,
                        'type' => 'RECEIPT',
                        'amount' => $cashAmount,
                        'payment_method' => $validated['payment_method'],
                        'description' => $description ?: 'Cash received',
                        'date' => $date,
                        'entry_source' => 'MANUAL',
                        'entry_type_code' => 'RECEIVE_CASH',
                    ]);
                    LedgerImpactService::applyCashTransaction($cashTransaction);
                    break;

                case 'GOLD_TO_CASH':
                    $calculatedCash = round($goldWeight * $rate, 2);

                    $metalTransaction = MetalTransaction::create([
                        'party_type' => $partyType,
                        'party_id' => $partyId,
                        'type' => 'RECEIPT',
                        'metal_type' => 'GOLD',
                        'gross_weight' => $goldWeight,
                        'fine_weight' => $makeFineWeight($goldWeight, $purity),
                        'description' => $description ?: "Gold adjusted against cash @ {$rate} ({$purity}%)",
                        'date' => $date,
                        'entry_source' => 'MANUAL',
                        'entry_type_code' => 'GOLD_TO_CASH',
                    ]);
                    LedgerImpactService::applyMetalTransaction($metalTransaction);

                    Transaction::create([
                        'transactable_type' => $partyType,
                        'transactable_id' => $partyId,
                        'type' => 'RECEIPT',
                        'amount' => $calculatedCash,
                        'description' => $description ?: "Cash adjusted from gold @ {$rate}",
                        'date' => $date,
                        'entry_source' => 'MANUAL',
                        'entry_type_code' => 'GOLD_TO_CASH',
                    ]);
                    break;

                case 'CASH_TO_GOLD':
                    $calculatedGold = round($cashAmount / $rate, 3);

                    $cashTransaction = Transaction::create([
                        'transactable_type' => $partyType,
                        'transactable_id' => $partyId,
                        'type' => 'PAYMENT',
                        'amount' => $cashAmount,
                        'description' => $description ?: "Cash adjusted into gold @ {$rate}",
                        'date' => $date,
                        'entry_source' => 'MANUAL',
                        'entry_type_code' => 'CASH_TO_GOLD',
                    ]);
                    LedgerImpactService::applyCashTransaction($cashTransaction);

                    $metalTransaction = MetalTransaction::create([
                        'party_type' => $partyType,
                        'party_id' => $partyId,
                        'type' => 'RECEIPT',
                        'metal_type' => 'GOLD',
                        'gross_weight' => $calculatedGold,
                        'fine_weight' => $makeFineWeight($calculatedGold, $purity),
                        'description' => $description ?: "Gold received from cash @ {$rate} ({$purity}%)",
                        'date' => $date,
                        'entry_source' => 'MANUAL',
                        'entry_type_code' => 'CASH_TO_GOLD',
                    ]);
                    LedgerImpactService::applyMetalTransaction($metalTransaction);
                    break;

                case 'SILVER_TO_CASH':
                    $calculatedCash = round($goldWeight * $rate, 2);

                    $metalTransaction = MetalTransaction::create([
                        'party_type' => $partyType,
                        'party_id' => $partyId,
                        'type' => 'RECEIPT',
                        'metal_type' => 'SILVER',
                        'gross_weight' => $goldWeight,
                        'fine_weight' => $makeFineWeight($goldWeight, $purity),
                        'description' => $description ?: "Silver adjusted against cash @ {$rate} ({$purity}%)",
                        'date' => $date,
                        'entry_source' => 'MANUAL',
                        'entry_type_code' => 'SILVER_TO_CASH',
                    ]);
                    LedgerImpactService::applyMetalTransaction($metalTransaction);

                    Transaction::create([
                        'transactable_type' => $partyType,
                        'transactable_id' => $partyId,
                        'type' => 'RECEIPT',
                        'amount' => $calculatedCash,
                        'description' => $description ?: "Cash adjusted from silver @ {$rate}",
                        'date' => $date,
                        'entry_source' => 'MANUAL',
                        'entry_type_code' => 'SILVER_TO_CASH',
                    ]);
                    break;

                case 'CASH_TO_SILVER':
                    $calculatedSilver = round($cashAmount / $rate, 3);

                    $cashTransaction = Transaction::create([
                        'transactable_type' => $partyType,
                        'transactable_id' => $partyId,
                        'type' => 'PAYMENT',
                        'amount' => $cashAmount,
                        'description' => $description ?: "Cash adjusted into silver @ {$rate}",
                        'date' => $date,
                        'entry_source' => 'MANUAL',
                        'entry_type_code' => 'CASH_TO_SILVER',
                    ]);
                    LedgerImpactService::applyCashTransaction($cashTransaction);

                    $metalTransaction = MetalTransaction::create([
                        'party_type' => $partyType,
                        'party_id' => $partyId,
                        'type' => 'RECEIPT',
                        'metal_type' => 'SILVER',
                        'gross_weight' => $calculatedSilver,
                        'fine_weight' => $makeFineWeight($calculatedSilver, $purity),
                        'description' => $description ?: "Silver received from cash @ {$rate} ({$purity}%)",
                        'date' => $date,
                        'entry_source' => 'MANUAL',
                        'entry_type_code' => 'CASH_TO_SILVER',
                    ]);
                    LedgerImpactService::applyMetalTransaction($metalTransaction);
                    break;
                }
            });
        } catch (\Throwable $e) {
            return back()->withErrors([
                'entry' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Ledger entry created successfully.');
    }

    private function isEditableCashTransaction(Transaction $transaction, ?string $entryTypeCode): bool
    {
        return ($transaction->entry_source ?? 'SYSTEM') === 'MANUAL'
            && $transaction->invoice_id === null
            && in_array($entryTypeCode, self::EDITABLE_ENTRY_TYPES, true);
    }

    private function isEditableMetalTransaction(MetalTransaction $transaction, ?string $entryTypeCode): bool
    {
        return ($transaction->entry_source ?? 'SYSTEM') === 'MANUAL'
            && in_array($entryTypeCode, ['ISSUE_GOLD', 'RECEIVE_GOLD', 'ISSUE_SILVER', 'RECEIVE_SILVER'], true);
    }

    private function inferCashEntryType(Transaction $transaction): string
    {
        return $transaction->type === 'PAYMENT' ? 'PAY_CASH' : 'RECEIVE_CASH';
    }

    private function inferMetalEntryType(MetalTransaction $transaction): string
    {
        $metalType = strtoupper((string) ($transaction->metal_type ?? 'GOLD'));

        if ($metalType === 'SILVER') {
            return $transaction->type === 'ISSUE' ? 'ISSUE_SILVER' : 'RECEIVE_SILVER';
        }

        return $transaction->type === 'ISSUE' ? 'ISSUE_GOLD' : 'RECEIVE_GOLD';
    }

    private function calculatePurityFromFineWeight(float $grossWeight, float $fineWeight): ?float
    {
        if ($grossWeight <= 0) {
            return null;
        }

        return round(($fineWeight / $grossWeight) * 100, 2);
    }

    private function makeFineWeight(float $grossWeight, float $purityPercent): float
    {
        return round(($grossWeight * $purityPercent) / 100, 3);
    }

    private function defaultDescriptionForEntryType(string $entryType, ?float $purity): string
    {
        return match ($entryType) {
            'ISSUE_GOLD' => "Gold issued ({$purity}%)",
            'RECEIVE_GOLD' => "Gold received ({$purity}%)",
            'ISSUE_SILVER' => "Silver issued ({$purity}%)",
            'RECEIVE_SILVER' => "Silver received ({$purity}%)",
            'PAY_CASH' => 'Cash paid',
            'RECEIVE_CASH' => 'Cash received',
            default => 'Ledger entry',
        };
    }
}
