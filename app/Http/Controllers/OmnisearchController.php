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

        // 1. Search Customers (By Name, Mobile Number, City, Membership ID, Email)
        $customers = Customer::query()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhere('membership_id', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->limit(6)
            ->get(['id', 'name', 'mobile', 'city', 'email', 'membership_id']);

        // 2. Search Invoices (By Invoice Number or Customer Name / Mobile)
        $invoices = Invoice::query()
            ->with('customer:id,name,mobile')
            ->where(function ($query) use ($q) {
                $query->where('invoice_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($q) {
                        $customerQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('mobile', 'like', "%{$q}%");
                    });
            })
            ->latest('id')
            ->limit(6)
            ->get(['id', 'invoice_number', 'customer_id', 'total_amount', 'date']);

        // 3. Search Products & Barcode Tags (By Product Name, Barcode)
        $products = Product::query()
            ->with(['category:id,name', 'purity:id,name'])
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            })
            ->limit(6)
            ->get(['id', 'name', 'barcode', 'category_id', 'purity_id', 'gross_weight', 'is_sold']);

        return response()->json([
            'customers' => $customers,
            'invoices' => $invoices,
            'products' => $products,
        ]);
    }
}
