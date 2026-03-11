<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Karigar;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Services\LedgerImpactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'party_id'   => 'required|integer',
            'party_type' => ['required', Rule::in([Supplier::class, Karigar::class, Customer::class])],
            'type'       => ['required', Rule::in(['PAYMENT', 'RECEIPT'])],
            'amount'     => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'date'       => 'required|date',
            'payment_method' => ['nullable', Rule::in(['CASH', 'UPI', 'BANK', 'CARD'])],
        ]);

        if (! $validated['party_type']::whereKey($validated['party_id'])->exists()) {
            throw ValidationException::withMessages([
                'party_id' => 'Selected party does not exist.',
            ]);
        }

        try {
            DB::transaction(function () use ($validated) {
            $transaction = Transaction::create([
                'transactable_type' => $validated['party_type'],
                'transactable_id'   => $validated['party_id'],
                'type'              => $validated['type'],
                'amount'            => $validated['amount'],
                'description'       => $validated['description'],
                'date'              => $validated['date'],
                'user_id'           => Auth::id(),
                'payment_method'    => $validated['payment_method'] ?? 'CASH',
                'entry_source'      => 'MANUAL',
                'entry_type_code'   => $validated['type'] === 'PAYMENT' ? 'PAY_CASH' : 'RECEIVE_CASH',
            ]);

            LedgerImpactService::applyCashTransaction($transaction);
            });
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'amount' => $e->getMessage(),
            ]);
        }

        return redirect()->back();
    }
}
