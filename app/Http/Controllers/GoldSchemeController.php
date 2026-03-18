<?php

namespace App\Http\Controllers;

use App\Enums\VaultType;
use App\Models\Customer;
use App\Models\CustomerGoldScheme;
use App\Models\GoldSchemeInstallment;
use App\Services\VaultService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Inertia\Inertia;

class GoldSchemeController extends Controller
{
    public function index()
    {
        $schemeModels = CustomerGoldScheme::query()
            ->with(['customer'])
            ->withCount(['installments as paid_installments_count_live' => fn ($query) => $query->where('status', 'PAID')])
            ->latest()
            ->take(30)
            ->get();

        $customerSchemes = $schemeModels
            ->map(fn (CustomerGoldScheme $scheme) => [
                'id' => $scheme->id,
                'scheme_number' => $scheme->scheme_number,
                'status' => $scheme->status,
                'start_date' => optional($scheme->start_date)->toDateString(),
                'maturity_date' => optional($scheme->maturity_date)->toDateString(),
                'monthly_amount' => (float) $scheme->monthly_amount,
                'total_months' => (int) $scheme->total_months,
                'bonus_amount' => (float) $scheme->bonus_amount,
                'paid_total' => (float) $scheme->paid_total,
                'paid_installments_count' => $scheme->paid_installments_count,
                'expected_customer_total' => $scheme->expected_customer_total,
                'redeemable_total' => $scheme->redeemable_total,
                'bonus_applied_at' => optional($scheme->bonus_applied_at)?->toDateTimeString(),
                'notes' => $scheme->notes,
                'customer' => [
                    'id' => $scheme->customer?->id,
                    'name' => $scheme->customer?->name,
                    'mobile' => $scheme->customer?->mobile,
                ],
                'can_edit' => (int) $scheme->paid_installments_count === 0 && $scheme->status !== 'CANCELLED',
                'can_cancel' => (int) $scheme->paid_installments_count === 0 && $scheme->status !== 'CANCELLED',
                'scheme_label' => 'Direct Customer Scheme',
                'next_pending_installment' => $scheme->installments()
                    ->where('status', '!=', 'PAID')
                    ->orderBy('installment_no')
                    ->first()?->only(['id', 'installment_no', 'due_date', 'amount_due', 'status']),
            ]);

        return Inertia::render('gold-schemes/Index', [
            'customerSchemes' => $customerSchemes,
            'customers' => Customer::query()
                ->orderBy('name')
                ->get(['id', 'name', 'mobile'])
                ->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'mobile' => $customer->mobile,
                ]),
            'summary' => [
                'active_schemes' => CustomerGoldScheme::where('status', 'ACTIVE')->count(),
                'matured_schemes' => CustomerGoldScheme::where('status', 'MATURED')->count(),
                'monthly_commitment' => (float) CustomerGoldScheme::where('status', 'ACTIVE')->sum('monthly_amount'),
                'scheme_collections' => (float) GoldSchemeInstallment::where('status', 'PAID')->sum('amount_paid'),
            ],
        ]);
    }

    public function print(CustomerGoldScheme $goldScheme): View
    {
        $goldScheme->load([
            'customer',
            'installments' => fn ($query) => $query->orderBy('installment_no'),
        ]);

        return view('print.gold-scheme', [
            'scheme' => $goldScheme,
            'pendingInstallments' => $goldScheme->installments->where('status', '!=', 'PAID')->values(),
            'paidInstallments' => $goldScheme->installments->where('status', 'PAID')->values(),
        ]);
    }

    public function show(CustomerGoldScheme $goldScheme): JsonResponse
    {
        $goldScheme->load('customer');

        $installments = $goldScheme->installments()
            ->orderBy('installment_no')
            ->get()
            ->map(function (GoldSchemeInstallment $installment) {
                return [
                    'id' => $installment->id,
                    'installment_no' => $installment->installment_no,
                    'due_date' => optional($installment->due_date)->toDateString(),
                    'amount_due' => (float) $installment->amount_due,
                    'amount_paid' => (float) ($installment->amount_paid ?? 0),
                    'paid_on' => optional($installment->paid_on)->toDateString(),
                    'payment_method' => $installment->payment_method,
                    'status' => $installment->status,
                    'note' => $installment->note,
                    'void_reason' => $installment->void_reason,
                    'voided_at' => optional($installment->voided_at)?->toDateTimeString(),
                ];
            })
            ->values();

        $nextPendingId = $installments->firstWhere('status', '!=', 'PAID')['id'] ?? null;

        return response()->json([
            'id' => $goldScheme->id,
            'scheme_number' => $goldScheme->scheme_number,
            'status' => $goldScheme->status,
            'start_date' => optional($goldScheme->start_date)->toDateString(),
            'maturity_date' => optional($goldScheme->maturity_date)->toDateString(),
            'monthly_amount' => (float) $goldScheme->monthly_amount,
            'total_months' => (int) $goldScheme->total_months,
            'bonus_amount' => (float) $goldScheme->bonus_amount,
            'paid_total' => (float) $goldScheme->paid_total,
            'paid_installments_count' => (int) $goldScheme->paid_installments_count,
            'redeemable_total' => (float) $goldScheme->redeemable_total,
            'scheme_label' => 'Direct Customer Scheme',
            'customer' => [
                'id' => $goldScheme->customer?->id,
                'name' => $goldScheme->customer?->name,
                'mobile' => $goldScheme->customer?->mobile,
            ],
            'can_edit' => (int) $goldScheme->paid_installments_count === 0 && $goldScheme->status !== 'CANCELLED',
            'can_cancel' => (int) $goldScheme->paid_installments_count === 0 && $goldScheme->status !== 'CANCELLED',
            'next_pending_installment_id' => $nextPendingId,
            'installments' => $installments,
        ]);
    }

    public function enroll(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'start_date' => ['required', 'date'],
            'monthly_amount' => ['required', 'numeric', 'min:1'],
            'total_months' => ['required', 'integer', 'min:1', 'max:36'],
            'bonus_amount' => ['required', 'numeric', 'min:0'],
            'already_paid_months' => ['required', 'integer', 'min:0'],
            'import_mode' => ['required', 'in:HISTORY_ONLY,POST_TO_VAULT'],
            'import_payment_method' => ['nullable', 'in:CASH,UPI,BANK,CARD'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($validated) {
            $startDate = Carbon::parse($validated['start_date'])->startOfDay();
            $totalMonths = (int) $validated['total_months'];
            $monthlyAmount = (float) $validated['monthly_amount'];
            $bonusAmount = (float) $validated['bonus_amount'];
            $alreadyPaidMonths = (int) $validated['already_paid_months'];
            $importMode = $validated['import_mode'];
            $importPaymentMethod = $validated['import_payment_method'] ?? null;

            if ($alreadyPaidMonths > $totalMonths) {
                return back()->withErrors([
                    'already_paid_months' => 'Already paid months cannot be more than total months.',
                ]);
            }

            if ($alreadyPaidMonths > 0 && $importMode === 'POST_TO_VAULT' && ! $importPaymentMethod) {
                return back()->withErrors([
                    'import_payment_method' => 'Choose a payment method when imported months should be posted to the vault.',
                ]);
            }

            $paidTotal = $monthlyAmount * $alreadyPaidMonths;
            $isFullyPaid = $alreadyPaidMonths >= $totalMonths;

            $scheme = CustomerGoldScheme::create([
                'customer_id' => $validated['customer_id'],
                'start_date' => $startDate,
                'maturity_date' => $startDate->copy()->addMonths($totalMonths - 1),
                'status' => $isFullyPaid ? 'MATURED' : 'ACTIVE',
                'monthly_amount' => $monthlyAmount,
                'total_months' => $totalMonths,
                'bonus_amount' => $bonusAmount,
                'paid_total' => $paidTotal,
                'paid_installments_count' => $alreadyPaidMonths,
                'bonus_applied_at' => $isFullyPaid ? now() : null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach (range(1, $totalMonths) as $installmentNo) {
                $dueDate = $startDate->copy()->addMonths($installmentNo - 1);
                $isImportedPaid = $installmentNo <= $alreadyPaidMonths;

                $installment = GoldSchemeInstallment::create([
                    'customer_gold_scheme_id' => $scheme->id,
                    'installment_no' => $installmentNo,
                    'due_date' => $dueDate,
                    'amount_due' => $monthlyAmount,
                    'amount_paid' => $isImportedPaid ? $monthlyAmount : null,
                    'paid_on' => $isImportedPaid ? $dueDate : null,
                    'payment_method' => $isImportedPaid ? ($importPaymentMethod ?: 'CASH') : null,
                    'note' => $isImportedPaid
                        ? ($importMode === 'HISTORY_ONLY' ? 'Imported paid installment (history only).' : 'Imported paid installment posted to vault.')
                        : null,
                    'collected_by' => $isImportedPaid ? Auth::id() : null,
                    'status' => $isImportedPaid ? 'PAID' : 'PENDING',
                ]);

                if ($isImportedPaid && $importMode === 'POST_TO_VAULT') {
                    $vaultType = in_array($importPaymentMethod, ['UPI', 'BANK', 'CARD'], true)
                        ? VaultType::BANK
                        : VaultType::CASH;

                    VaultService::credit($vaultType, $monthlyAmount, [
                        'source_type' => GoldSchemeInstallment::class,
                        'source_id' => $installment->id,
                        'reference' => $scheme->scheme_number,
                        'user_id' => Auth::id(),
                        'note' => "Imported scheme installment {$installmentNo} for {$scheme->scheme_number}",
                        'recorded_at' => $dueDate,
                    ]);
                }
            }

            return back()->with('success', "Scheme {$scheme->scheme_number} enrolled successfully.");
        });
    }

    public function update(Request $request, CustomerGoldScheme $goldScheme)
    {
        if ((int) $goldScheme->paid_installments_count > 0 || $goldScheme->status === 'CANCELLED') {
            return back()->withErrors([
                'scheme' => 'Only schemes with no collected installments can be edited.',
            ]);
        }

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'start_date' => ['required', 'date'],
            'monthly_amount' => ['required', 'numeric', 'min:1'],
            'total_months' => ['required', 'integer', 'min:1', 'max:36'],
            'bonus_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($validated, $goldScheme) {
            $startDate = Carbon::parse($validated['start_date'])->startOfDay();
            $totalMonths = (int) $validated['total_months'];
            $monthlyAmount = (float) $validated['monthly_amount'];

            $goldScheme->update([
                'customer_id' => $validated['customer_id'],
                'start_date' => $startDate,
                'maturity_date' => $startDate->copy()->addMonths($totalMonths - 1),
                'monthly_amount' => $monthlyAmount,
                'total_months' => $totalMonths,
                'bonus_amount' => (float) $validated['bonus_amount'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $goldScheme->installments()->delete();

            foreach (range(1, $totalMonths) as $installmentNo) {
                GoldSchemeInstallment::create([
                    'customer_gold_scheme_id' => $goldScheme->id,
                    'installment_no' => $installmentNo,
                    'due_date' => $startDate->copy()->addMonths($installmentNo - 1),
                    'amount_due' => $monthlyAmount,
                    'status' => 'PENDING',
                ]);
            }

            return back()->with('success', "Scheme {$goldScheme->scheme_number} updated successfully.");
        });
    }

    public function cancel(Request $request, CustomerGoldScheme $goldScheme)
    {
        if ((int) $goldScheme->paid_installments_count > 0 || $goldScheme->status === 'CANCELLED') {
            return back()->withErrors([
                'scheme' => 'Only schemes with no active paid installments can be cancelled.',
            ]);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $reasonNote = 'Cancelled: ' . $validated['reason'];
        $existingNotes = trim((string) $goldScheme->notes);

        $goldScheme->update([
            'status' => 'CANCELLED',
            'notes' => $existingNotes !== '' ? $existingNotes . "\n" . $reasonNote : $reasonNote,
        ]);

        return back()->with('success', "Scheme {$goldScheme->scheme_number} cancelled successfully.");
    }

    public function payInstallment(Request $request, GoldSchemeInstallment $goldSchemeInstallment)
    {
        $validated = $request->validate([
            'amount_paid' => ['required', 'numeric', 'min:1'],
            'paid_on' => ['required', 'date'],
            'payment_method' => ['required', 'in:CASH,UPI,BANK,CARD'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($goldSchemeInstallment->status === 'PAID') {
            return back()->withErrors([
                'installment' => 'This installment is already marked as paid.',
            ]);
        }

        if ((float) $validated['amount_paid'] !== (float) $goldSchemeInstallment->amount_due) {
            return back()->withErrors([
                'amount_paid' => 'For now, installment payment must match the exact due amount.',
            ]);
        }

        return DB::transaction(function () use ($validated, $goldSchemeInstallment) {
            $scheme = $goldSchemeInstallment->scheme()->lockForUpdate()->firstOrFail();

            $vaultType = in_array($validated['payment_method'], ['UPI', 'BANK', 'CARD'], true)
                ? VaultType::BANK
                : VaultType::CASH;

            VaultService::credit($vaultType, (float) $validated['amount_paid'], [
                'source_type' => GoldSchemeInstallment::class,
                'source_id' => $goldSchemeInstallment->id,
                'reference' => $scheme->scheme_number,
                'user_id' => Auth::id(),
                'note' => "Scheme installment {$goldSchemeInstallment->installment_no} collected for {$scheme->scheme_number}",
                'recorded_at' => Carbon::parse($validated['paid_on']),
            ]);

            $goldSchemeInstallment->update([
                'amount_paid' => $validated['amount_paid'],
                'paid_on' => $validated['paid_on'],
                'payment_method' => $validated['payment_method'],
                'note' => $validated['note'] ?? null,
                'status' => 'PAID',
                'collected_by' => Auth::id(),
            ]);

            $scheme->update([
                'paid_total' => (float) $scheme->paid_total + (float) $validated['amount_paid'],
                'paid_installments_count' => (int) $scheme->paid_installments_count + 1,
            ]);

            if ((int) $scheme->paid_installments_count >= (int) $scheme->total_months && ! $scheme->bonus_applied_at) {
                $scheme->update([
                    'status' => 'MATURED',
                    'bonus_applied_at' => now(),
                ]);
            }

            return back()->with('success', "Installment {$goldSchemeInstallment->installment_no} marked as paid.");
        });
    }

    public function voidInstallment(Request $request, GoldSchemeInstallment $goldSchemeInstallment)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($goldSchemeInstallment->status !== 'PAID') {
            return back()->withErrors([
                'installment' => 'Only paid installments can be voided.',
            ]);
        }

        return DB::transaction(function () use ($validated, $goldSchemeInstallment) {
            $scheme = $goldSchemeInstallment->scheme()->lockForUpdate()->firstOrFail();

            $vaultType = in_array($goldSchemeInstallment->payment_method, ['UPI', 'BANK', 'CARD'], true)
                ? VaultType::BANK
                : VaultType::CASH;

            VaultService::debit($vaultType, (float) $goldSchemeInstallment->amount_paid, [
                'source_type' => GoldSchemeInstallment::class,
                'source_id' => $goldSchemeInstallment->id,
                'reference' => $scheme->scheme_number,
                'user_id' => Auth::id(),
                'note' => "Void scheme installment {$goldSchemeInstallment->installment_no} for {$scheme->scheme_number}: {$validated['reason']}",
                'recorded_at' => now(),
            ]);

            $goldSchemeInstallment->update([
                'amount_paid' => null,
                'paid_on' => null,
                'payment_method' => null,
                'status' => 'PENDING',
                'note' => null,
                'voided_at' => now(),
                'voided_by' => Auth::id(),
                'void_reason' => $validated['reason'],
                'collected_by' => null,
            ]);

            $paidCount = max(0, (int) $scheme->paid_installments_count - 1);
            $paidTotal = max(0, (float) $scheme->paid_total - (float) $goldSchemeInstallment->amount_due);

            $scheme->update([
                'paid_total' => $paidTotal,
                'paid_installments_count' => $paidCount,
                'status' => $paidCount >= (int) $scheme->total_months ? 'MATURED' : 'ACTIVE',
                'bonus_applied_at' => $paidCount >= (int) $scheme->total_months ? $scheme->bonus_applied_at : null,
            ]);

            return back()->with('success', "Installment {$goldSchemeInstallment->installment_no} has been voided.");
        });
    }
}
