<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Karigar;
use App\Models\MetalTransaction;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LedgerController extends Controller
{
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
            ->map(function ($txn) {
                return [
                    'id' => 'cash-' . $txn->id,
                    'row_id' => $txn->id,
                    'date' => $txn->date,
                    'description' => $txn->description,
                    'category' => 'CASH',
                    'type' => $txn->type, // PAYMENT / RECEIPT
                    'amount' => (float) $txn->amount,
                    'created_at' => optional($txn->created_at)->toDateTimeString(),
                    'sort_at' => optional($txn->created_at)->timestamp ?? strtotime($txn->date),
                ];
            });

        $metalTxns = MetalTransaction::where('party_type', $modelClass)
            ->where('party_id', $id)
            ->get()
            ->map(function ($txn) {
                return [
                    'id' => 'metal-' . $txn->id,
                    'row_id' => $txn->id,
                    'date' => $txn->date,
                    'description' => $txn->description,
                    'category' => 'METAL',
                    'type' => $txn->type, // ISSUE / RECEIPT
                    'amount' => (float) $txn->gross_weight,
                    'created_at' => optional($txn->created_at)->toDateTimeString(),
                    'sort_at' => optional($txn->created_at)->timestamp ?? strtotime($txn->date),
                ];
            });

        $mergedTransactions = $cashTxns
            ->merge($metalTxns)
            ->sortBy('sort_at')
            ->values();

        return Inertia::render('ledger/Show', [
            'party' => $party,
            'party_type_class' => $modelClass,
            'transactions' => $mergedTransactions,
        ]);
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
                    'PAY_CASH',
                    'RECEIVE_CASH',
                    'GOLD_TO_CASH',
                    'CASH_TO_GOLD',
                ]),
            ],
            'gold_weight' => ['nullable', 'numeric', 'min:0.001'],
            'cash_amount' => ['nullable', 'numeric', 'min:0.01'],
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

        if (in_array($entryType, ['ISSUE_GOLD', 'RECEIVE_GOLD', 'GOLD_TO_CASH'], true) && ! $goldWeight) {
            return back()->withErrors([
                'gold_weight' => 'Gold weight is required for this entry type.',
            ]);
        }

        if (in_array($entryType, ['PAY_CASH', 'RECEIVE_CASH', 'CASH_TO_GOLD'], true) && ! $cashAmount) {
            return back()->withErrors([
                'cash_amount' => 'Cash amount is required for this entry type.',
            ]);
        }

        if (in_array($entryType, ['ISSUE_GOLD', 'RECEIVE_GOLD', 'GOLD_TO_CASH', 'CASH_TO_GOLD'], true) && ! $purity) {
            return back()->withErrors([
                'purity' => 'Purity is required for gold entries.',
            ]);
        }

        if ($entryType === 'GOLD_TO_CASH' && (! $rate || ! $goldWeight)) {
            return back()->withErrors([
                'rate' => 'Rate is required for gold to cash adjustment.',
            ]);
        }

        if ($entryType === 'CASH_TO_GOLD' && (! $rate || ! $cashAmount)) {
            return back()->withErrors([
                'rate' => 'Rate is required for cash to gold adjustment.',
            ]);
        }

        DB::transaction(function () use (
            $partyType,
            $partyId,
            $entryType,
            $goldWeight,
            $cashAmount,
            $rate,
            $purity,
            $description,
            $date
        ) {
            $makeFineWeight = function (float $grossWeight, float $purityPercent): float {
                return round(($grossWeight * $purityPercent) / 100, 3);
            };

            switch ($entryType) {
                case 'ISSUE_GOLD':
                    MetalTransaction::create([
                        'party_type' => $partyType,
                        'party_id' => $partyId,
                        'type' => 'ISSUE',
                        'gross_weight' => $goldWeight,
                        'fine_weight' => $makeFineWeight($goldWeight, $purity),
                        'description' => $description ?: "Gold issued ({$purity}%)",
                        'date' => $date,
                    ]);
                    break;

                case 'RECEIVE_GOLD':
                    MetalTransaction::create([
                        'party_type' => $partyType,
                        'party_id' => $partyId,
                        'type' => 'RECEIPT',
                        'gross_weight' => $goldWeight,
                        'fine_weight' => $makeFineWeight($goldWeight, $purity),
                        'description' => $description ?: "Gold received ({$purity}%)",
                        'date' => $date,
                    ]);
                    break;

                case 'PAY_CASH':
                    Transaction::create([
                        'transactable_type' => $partyType,
                        'transactable_id' => $partyId,
                        'type' => 'PAYMENT',
                        'amount' => $cashAmount,
                        'description' => $description ?: 'Cash paid',
                        'date' => $date,
                    ]);
                    break;

                case 'RECEIVE_CASH':
                    Transaction::create([
                        'transactable_type' => $partyType,
                        'transactable_id' => $partyId,
                        'type' => 'RECEIPT',
                        'amount' => $cashAmount,
                        'description' => $description ?: 'Cash received',
                        'date' => $date,
                    ]);
                    break;

                case 'GOLD_TO_CASH':
                    $calculatedCash = round($goldWeight * $rate, 2);

                    MetalTransaction::create([
                        'party_type' => $partyType,
                        'party_id' => $partyId,
                        'type' => 'RECEIPT',
                        'gross_weight' => $goldWeight,
                        'fine_weight' => $makeFineWeight($goldWeight, $purity),
                        'description' => $description ?: "Gold adjusted against cash @ {$rate} ({$purity}%)",
                        'date' => $date,
                    ]);

                    Transaction::create([
                        'transactable_type' => $partyType,
                        'transactable_id' => $partyId,
                        'type' => 'RECEIPT',
                        'amount' => $calculatedCash,
                        'description' => $description ?: "Cash adjusted from gold @ {$rate}",
                        'date' => $date,
                    ]);
                    break;

                case 'CASH_TO_GOLD':
                    $calculatedGold = round($cashAmount / $rate, 3);

                    Transaction::create([
                        'transactable_type' => $partyType,
                        'transactable_id' => $partyId,
                        'type' => 'PAYMENT',
                        'amount' => $cashAmount,
                        'description' => $description ?: "Cash adjusted into gold @ {$rate}",
                        'date' => $date,
                    ]);

                    MetalTransaction::create([
                        'party_type' => $partyType,
                        'party_id' => $partyId,
                        'type' => 'RECEIPT',
                        'gross_weight' => $calculatedGold,
                        'fine_weight' => $makeFineWeight($calculatedGold, $purity),
                        'description' => $description ?: "Gold received from cash @ {$rate} ({$purity}%)",
                        'date' => $date,
                    ]);
                    break;
            }
        });

        return back()->with('success', 'Ledger entry created successfully.');
    }
}
