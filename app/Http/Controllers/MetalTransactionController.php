<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\Karigar;
use App\Models\MetalTransaction;
use App\Models\Supplier;
use App\Services\LedgerImpactService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MetalTransactionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'party_id'   => 'required|integer',
            'party_type' => ['required', Rule::in([Supplier::class, Karigar::class, Customer::class])],
            'type'       => ['required', Rule::in(['ISSUE', 'RECEIPT'])],
            'amount'     => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'date'       => 'required|date',
        ]);

        if (! $validated['party_type']::whereKey($validated['party_id'])->exists()) {
            throw ValidationException::withMessages([
                'party_id' => 'Selected party does not exist.',
            ]);
        }

        try {
            DB::transaction(function () use ($validated) {
            $transaction = MetalTransaction::create([
                'party_type'   => $validated['party_type'],
                'party_id'     => $validated['party_id'],
                'type'         => $validated['type'],
                'gross_weight' => $validated['amount'],
                'fine_weight'  => $validated['amount'],
                'description'  => $validated['description'],
                'date'         => $validated['date'],
                'user_id'      => Auth::id(),
                'entry_source' => 'MANUAL',
                'entry_type_code' => $validated['type'] === 'ISSUE' ? 'ISSUE_GOLD' : 'RECEIVE_GOLD',
            ]);

            LedgerImpactService::applyMetalTransaction($transaction);
            });
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'amount' => $e->getMessage(),
            ]);
        }

        return redirect()->back();
    }
}
