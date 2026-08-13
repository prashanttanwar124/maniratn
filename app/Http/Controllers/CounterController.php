<?php

namespace App\Http\Controllers;

use App\Models\Counter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CounterController extends Controller
{
    public function index()
    {
        $counters = Counter::query()
            ->withCount(['products', 'silverProducts'])
            ->orderBy('name')
            ->get()
            ->map(fn (Counter $counter) => [
                'id' => $counter->id,
                'name' => $counter->name,
                'gold_items_count' => (int) $counter->products_count,
                'silver_items_count' => (int) $counter->silver_products_count,
                'created_at' => optional($counter->created_at)?->toDateString(),
            ]);

        return Inertia::render('counters/Index', [
            'counters' => $counters,
            'summary' => [
                'total_counters' => $counters->count(),
                'gold_items' => $counters->sum('gold_items_count'),
                'silver_items' => $counters->sum('silver_items_count'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        Counter::query()->create($this->validatePayload($request));

        return back()->with('message', 'Counter created successfully.');
    }

    public function update(Request $request, Counter $counter)
    {
        $counter->update($this->validatePayload($request, $counter->id));

        return back()->with('message', 'Counter updated successfully.');
    }

    public function destroy(Counter $counter)
    {
        if ($counter->products()->exists() || $counter->silverProducts()->exists()) {
            return back()->withErrors([
                'counter' => 'A counter linked to products cannot be deleted.',
            ]);
        }

        $counter->delete();

        return back()->with('message', 'Counter deleted successfully.');
    }

    private function validatePayload(Request $request, ?int $counterId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('counters', 'name')->ignore($counterId)],
        ]);

        $validated['name'] = trim($validated['name']);

        return $validated;
    }
}
