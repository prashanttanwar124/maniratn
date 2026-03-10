<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'party_id'   => 'required|integer',
            'party_type' => 'required|string', // e.g., "App\Models\Supplier"
            'type'       => 'required',
            'amount'     => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'date'       => 'required|date',
        ]);

        Transaction::create([
            // DYNAMIC POLYMORPHISM
            'transactable_type' => $validated['party_type'],
            'transactable_id'   => $validated['party_id'],

            'type'              => $validated['type'],
            'amount'            => $validated['amount'],
            'description'       => $validated['description'],
            'date'              => $validated['date'],
            'user_id'           => Auth::id(),
        ]);

        return redirect()->back();
    }
}
