<?php

namespace App\Http\Controllers;

use App\Models\Karigar;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KarigarController extends Controller
{
    public function index()
    {
        return Inertia::render('Karigars/Index', [
            'karigars' => Karigar::all()
        ]);
    }

    public function store(Request $request)
    {
        Karigar::create($request->validate([
            'name' => 'required',
            'mobile' => 'required|unique:karigars'
        ]));
        return back()->with('success', 'Karigar Added');
    }

    public function show(Karigar $karigar)
    {
        // Metal Balance: (Issued - Received)
        // Positive means HE HAS YOUR GOLD.
        $issued = $karigar->metalTransactions()->where('type', 'ISSUE')->sum('fine_weight');
        $received = $karigar->metalTransactions()->where('type', 'RECEIVE')->sum('fine_weight');

        // Money Balance: Just tracking payments made to him
        $paid = $karigar->transactions()->where('type', 'PAYMENT')->sum('amount');

        // Fetch Merged History
        $metal = $karigar->metalTransactions()
            ->select('id', 'date', 'type', 'fine_weight as val', 'description', 'created_at')
            ->selectRaw("'METAL' as category");

        $history = $karigar->transactions()
            ->select('id', 'date', 'type', 'amount as val', 'description', 'created_at')
            ->selectRaw("'CASH' as category")
            ->union($metal)
            ->orderBy('date', 'desc')
            ->take(50)
            ->get();

        return Inertia::render('Karigars/Show', [
            'karigar' => $karigar,
            'balances' => [
                'metal' => $issued - $received,
                'cash_paid' => $paid
            ],
            'history' => $history
        ]);
    }
}
