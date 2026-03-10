<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MetalTransaction;
use Illuminate\Support\Facades\Auth;

class MetalTransactionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'party_id'   => 'required|integer',
            'party_type' => 'required|string',
            'type'       => 'required',
            'amount'     => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'date'       => 'required|date',
        ]);

        MetalTransaction::create([
            // DYNAMIC POLYMORPHISM
            'party_type'   => $validated['party_type'],
            'party_id'     => $validated['party_id'],

            'type'         => $validated['type'],
            'gross_weight' => $validated['amount'],
            'fine_weight'  => $validated['amount'],
            'description'  => $validated['description'],
            'date'         => $validated['date'],
            'user_id'           => Auth::id(),

        ]);

        return redirect()->back();
    }
}
