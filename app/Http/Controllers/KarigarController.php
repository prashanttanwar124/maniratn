<?php

namespace App\Http\Controllers;

use App\Models\Karigar;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KarigarController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $karigarsQuery = Karigar::query();

        if ($search !== '') {
            $karigarsQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('mobile', 'like', '%' . $search . '%');
            });
        }

        $karigars = $karigarsQuery->get()->map(fn (Karigar $karigar) => $this->transformKarigar($karigar));

        $recoveryDesk = $karigars
            ->map(fn ($karigar) => [
                'id' => $karigar['id'],
                'name' => $karigar['name'],
                'cash_balance' => $karigar['cash_balance'],
                'metal_balance' => $karigar['metal_balance'],
                'priority_score' => abs($karigar['cash_balance']) + (abs($karigar['metal_balance']) * 10000),
            ])
            ->sortByDesc('priority_score')
            ->take(6)
            ->values();

        return Inertia::render('karigars/Index', [
            'karigars' => $karigars,
            'recoveryDesk' => $recoveryDesk,
            'metrics' => [
                'karigar_count' => $karigars->count(),
                'karigar_cash_exposure' => $karigars->sum('cash_balance'),
                'karigar_gold_out' => $karigars->sum('metal_balance'),
                'urgent_accounts' => $recoveryDesk->filter(fn ($row) => abs($row['cash_balance']) > 0 || abs($row['metal_balance']) > 0)->count(),
            ],
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        Karigar::create($request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|regex:/^[0-9]{10}$/|unique:karigars,mobile',
            'work_type' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]));

        return back()->with('success', 'Karigar added successfully.');
    }

    public function update(Request $request, Karigar $karigar)
    {
        $karigar->update($request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|regex:/^[0-9]{10}$/|unique:karigars,mobile,' . $karigar->id,
            'work_type' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]));

        return back()->with('success', 'Karigar updated successfully.');
    }

    public function destroy(Karigar $karigar)
    {
        $karigar->delete();

        return back()->with('success', 'Karigar deleted successfully.');
    }

    private function transformKarigar(Karigar $karigar): array
    {
        $cashPaid = $karigar->transactions()->where('type', 'PAYMENT')->sum('amount');
        $cashReceived = $karigar->transactions()->where('type', 'RECEIPT')->sum('amount');

        return [
            'id' => $karigar->id,
            'name' => $karigar->name,
            'mobile' => $karigar->mobile,
            'work_type' => $karigar->work_type,
            'city' => $karigar->city,
            'notes' => $karigar->notes,
            'cash_balance' => (float) $cashPaid - (float) $cashReceived,
            'metal_balance' => (float) $karigar->metal_balance,
        ];
    }
}
