<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OmnisearchController extends Controller
{
    /**
     * Handle global spotlight search across Customers, Invoices, and Inventory.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 1) {
            return response()->json([
                'customers' => [],
                'invoices' => [],
                'products' => [],
            ]);
        }

        $customers = Customer::query()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%");
            })
            ->limit(5)
            ->get(['id', 'name', 'phone', 'city']);

        $invoices = Invoice::query()
            ->with('customer:id,name,phone')
            ->where(function ($query) use ($q) {
                $query->where('invoice_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($q) {
                        $customerQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%");
                    });
            })
            ->latest('id')
            ->limit(5)
            ->get(['id', 'invoice_number', 'customer_id', 'total_amount', 'date', 'status']);

        $products = Product::query()
            ->with(['category:id,name', 'purity:id,name'])
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            })
            ->limit(5)
            ->get(['id', 'name', 'barcode', 'category_id', 'purity_id', 'gross_weight', 'status']);

        return response()->json([
            'customers' => $customers,
            'invoices' => $invoices,
            'products' => $products,
        ]);
    }
}
