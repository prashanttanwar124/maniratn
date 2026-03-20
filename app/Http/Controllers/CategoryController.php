<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::query()
            ->withCount(['products', 'silverProducts'])
            ->orderBy('metal_type')
            ->orderBy('name')
            ->get()
            ->map(function (Category $category) {
                $itemsCount = $category->metal_type === 'SILVER'
                    ? (int) $category->silver_products_count
                    : (int) $category->products_count;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'code' => $category->code,
                    'metal_type' => $category->metal_type,
                    'items_count' => $itemsCount,
                    'created_at' => optional($category->created_at)?->toDateString(),
                ];
            });

        return Inertia::render('categories/Index', [
            'categories' => $categories,
            'summary' => [
                'total_categories' => $categories->count(),
                'gold_categories' => $categories->where('metal_type', 'GOLD')->count(),
                'silver_categories' => $categories->where('metal_type', 'SILVER')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        Category::create($validated);

        return back()->with('message', 'Category created successfully.');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $this->validatePayload($request, $category->id);

        $hasLinkedItems = $category->products()->exists() || $category->silverProducts()->exists();
        if ($hasLinkedItems && $validated['metal_type'] !== $category->metal_type) {
            return back()->withErrors([
                'metal_type' => 'Metal type cannot be changed after products are linked to this category.',
            ]);
        }

        $category->update($validated);

        return back()->with('message', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists() || $category->silverProducts()->exists()) {
            return back()->withErrors([
                'category' => 'Linked categories cannot be deleted.',
            ]);
        }

        $category->delete();

        return back()->with('message', 'Category deleted successfully.');
    }

    protected function validatePayload(Request $request, ?int $categoryId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('categories', 'code')->ignore($categoryId),
            ],
            'metal_type' => ['required', Rule::in(['GOLD', 'SILVER'])],
        ]);

        $validated['name'] = trim($validated['name']);
        $validated['code'] = strtoupper(trim($validated['code']));

        return $validated;
    }
}
