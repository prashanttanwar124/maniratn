<?php

namespace App\Http\Controllers;

use App\Models\Purity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PurityController extends Controller
{
    public function index()
    {
        $purities = Purity::query()
            ->withCount('products')
            ->orderBy('name')
            ->get()
            ->map(fn (Purity $purity) => [
                'id' => $purity->id,
                'name' => $purity->name,
                'products_count' => (int) $purity->products_count,
                'created_at' => optional($purity->created_at)?->toDateString(),
            ]);

        return Inertia::render('purities/Index', [
            'purities' => $purities,
            'summary' => [
                'total_purities' => $purities->count(),
                'linked_purities' => $purities->where('products_count', '>', 0)->count(),
                'unused_purities' => $purities->where('products_count', 0)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        Purity::create($validated);

        return back()->with('message', 'Purity created successfully.');
    }

    public function update(Request $request, Purity $purity)
    {
        $validated = $this->validatePayload($request, $purity->id);

        $purity->update($validated);

        return back()->with('message', 'Purity updated successfully.');
    }

    public function destroy(Purity $purity)
    {
        if ($purity->products()->exists()) {
            return back()->withErrors([
                'purity' => 'Linked purities cannot be deleted.',
            ]);
        }

        $purity->delete();

        return back()->with('message', 'Purity deleted successfully.');
    }

    protected function validatePayload(Request $request, ?int $purityId = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('purities', 'name')->ignore($purityId),
            ],
        ]);

        $validated['name'] = trim($validated['name']);

        return $validated;
    }
}
