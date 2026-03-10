<?php

// app/Http/Controllers/CustomerController.php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;


class CustomerController extends Controller
{
    public function index()
    {
        // 1. Get All Customers (with calculated Balance & Spend)
        // We use 'withSum' to calculate totals without loading thousands of transaction rows
        $customers = Customer::query()
            ->withSum(['transactions as total_spend' => function ($query) {
                $query->where('type', 'SALE');
            }], 'amount')

            ->paginate(5)
            ->through(function ($customer) {
                $data = $customer->toArray();

                $data['balance'] = $customer->balance;

                return $data;
            });

        // 2. GLOBAL TOP SPENDERS (Independent Query)
        // We query the database AGAIN to check everyone, not just the current page
        $topSpenders = Customer::query()
            ->withSum(['transactions as total_spend' => function ($query) {
                $query->where('type', 'SALE');
            }], 'amount')
            ->orderByDesc('total_spend') // Database Sort
            ->limit(5)
            ->get(); // Get the actual top 5 from the whole table

        // 3. GLOBAL TOP DEBTORS (Independent Query)
        // Since 'balance' is calculated in PHP, we can't sort by it in SQL easily
        // UNLESS you have a real 'balance' column. 
        // OPTION A (If balance is virtual): Fetch all, then sort (Heavy for large DB)
        // OPTION B (Better): Create a raw query or calculate balance in SQL.

        // Assuming you stick to PHP sorting for now (okay for <1000 customers):
        $topDebtors = Customer::query()
            ->select('customers.*') // Select customer details
            ->selectRaw('
        (
            (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE transactions.transactable_id = customers.id AND transactions.transactable_type = "App\\\Models\\\Customer" AND type = "SALE") 
            - 
            (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE transactions.transactable_id = customers.id AND transactions.transactable_type = "App\\\Models\\\Customer" AND type = "PAYMENT")
        ) as balance
    ')
            ->having('balance', '>', 0) // Only people who owe money
            ->orderByDesc('balance')    // Sort by the math result
            ->limit(5)
            ->get();

        return Inertia::render('customers/Index', [
            'customers'    => $customers,
            'topSpenders'  => $topSpenders,
            'topDebtors'   => $topDebtors,
            'totalCount'   => Customer::count(),
            'newThisWeek'  => Customer::where('created_at', '>=', now()->subDays(7))->count(),
        ]);
    }


    public function show($id)
    {
        $customer = Customer::findOrFail($id);

        // 1. FAST MATH: Let SQL calculate the totals (Super fast even with 1M rows)
        // We use the query builder () to run SUM in the database
        $totalSales = $customer->transactions()->where('type', 'SALE')->sum('amount');
        $totalPaid  = $customer->transactions()->where('type', 'PAYMENT')->sum('amount');

        // 2. PAGINATION: Only fetch 15 rows for the table
        $transactions = $customer->transactions()->with('user')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc') // Fallback sort
            ->paginate(5) // <--- This is the Magic
            ->withQueryString(); // Keeps filters if you add them later

        return Inertia::render('customers/Show', [
            'customer' => $customer,
            'transactions' => $transactions, // Send the Paginator object, not just array
            'stats' => [
                'total_sales' => $totalSales,
                'total_paid'  => $totalPaid,
                'current_balance' => $totalSales - $totalPaid // Calculate explicitly here
            ]
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->input('query');

        $customers = \App\Models\Customer::query()
            ->where('name', 'like', "%{$query}%")
            ->orWhere('mobile', 'like', "%{$query}%")
            ->limit(10) // ⚡ Only get 5-10 results, super fast
            ->select('id', 'name', 'mobile')
            ->get();

        return response()->json($customers);
    }
}
