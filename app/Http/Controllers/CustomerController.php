<?php

// app/Http/Controllers/CustomerController.php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Customer;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $today = Carbon::today();

        // 1. Get All Customers (with calculated Balance & Spend)
        // We use 'withSum' to calculate totals without loading thousands of transaction rows
        $customers = Customer::query()
            ->withSum(['transactions as total_spend' => function ($query) {
                $query->where('type', 'SALE');
            }], 'amount')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($customerQuery) use ($search) {
                    $customerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('pan_no', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('membership_id', 'like', "%{$search}%");
                });
            })
            ->paginate(5)
            ->withQueryString()
            ->through(function ($customer) use ($today) {
                $data = $customer->toArray();

                $data['balance'] = $customer->balance;
                $data['vault_url'] = $customer->vault_token ? ($this->publicBaseUrl() . '/vault/' . $customer->vault_token) : null;
                $data['dob_reminder'] = $this->buildOccasionReminder($customer->dob, $today);
                $data['anniversary_reminder'] = $this->buildOccasionReminder($customer->anniversary_date, $today);
                $data['has_upcoming_occasion'] = $data['dob_reminder'] !== null || $data['anniversary_reminder'] !== null;

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
            'birthdaysThisWeek' => $this->countUpcomingOccasions('dob', $today),
            'anniversariesThisWeek' => $this->countUpcomingOccasions('anniversary_date', $today),
            'filters' => [
                'search' => $search,
            ],
            'business' => [
                'store_name' => BusinessSetting::first()?->store_name ?? 'Maniratn Jewellers',
                'google_review_url' => BusinessSetting::first()?->google_review_url,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateCustomer($request);

        Customer::create($validated);

        return back()->with('success', 'Customer created successfully.');
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

        $baseUrl = $this->publicBaseUrl();
        $vaultUrl = $customer->vault_token ? ($baseUrl . '/vault/' . $customer->vault_token) : null;

        return Inertia::render('customers/Show', [
            'customer' => $customer,
            'transactions' => $transactions, // Send the Paginator object, not just array
            'stats' => [
                'total_sales' => $totalSales,
                'total_paid'  => $totalPaid,
                'current_balance' => $totalSales - $totalPaid // Calculate explicitly here
            ],
            'vault' => [
                'has_card' => (bool) $customer->vault_token,
                'vault_token' => $customer->vault_token,
                'vault_url' => $vaultUrl,
                'card_status' => $customer->card_status ?? 'NOT_ISSUED',
                'nfc_card_uid' => $customer->nfc_card_uid,
                'card_issued_at' => optional($customer->card_issued_at)?->toDateTimeString(),
                'card_written_at' => optional($customer->card_written_at)?->toDateTimeString(),
                'card_locked_at' => optional($customer->card_locked_at)?->toDateTimeString(),
                'card_last_accessed_at' => optional($customer->card_last_accessed_at)?->toDateTimeString(),
                'card_access_count' => (int) $customer->card_access_count,
                'invoices_count' => $customer->invoices()->where('status', '!=', 'CANCELLED')->count(),
                'schemes_count' => $customer->goldSchemes()->count(),
                'google_review_url' => BusinessSetting::first()?->google_review_url,
            ],
        ]);
    }

    public function issueVaultCard(Customer $customer)
    {
        if (! $customer->vault_token) {
            $customer->vault_token = Customer::generateVaultToken();
        }

        $customer->card_status = 'ISSUED';
        $customer->card_issued_at = now();
        $customer->save();

        return back()->with('success', 'Customer Smart Vault Card issued successfully.');
    }

    public function cardWriter(Customer $customer)
    {
        if (! $customer->vault_token) {
            $customer->vault_token = Customer::generateVaultToken();
            $customer->card_status = 'ISSUED';
            $customer->card_issued_at = now();
            $customer->save();
        }

        $vaultUrl = $this->publicBaseUrl() . '/vault/' . $customer->vault_token;

        return view('customers.card-writer', [
            'customer' => $customer,
            'vaultUrl' => $vaultUrl,
            'token' => $customer->vault_token,
        ]);
    }

    public function confirmCardWritten(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'nfc_uid' => ['nullable', 'string', 'max:255'],
        ]);

        $customer->update([
            'card_status' => $customer->card_locked_at ? 'LOCKED' : 'WRITTEN',
            'card_written_at' => $customer->card_written_at ?: now(),
            'nfc_card_uid' => $validated['nfc_uid'] ?? $customer->nfc_card_uid,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer Card written successfully.',
        ]);
    }

    public function confirmCardLocked(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'nfc_uid' => ['nullable', 'string', 'max:255'],
        ]);

        $customer->update([
            'card_status' => 'LOCKED',
            'card_written_at' => $customer->card_written_at ?: now(),
            'card_locked_at' => $customer->card_locked_at ?: now(),
            'nfc_card_uid' => $validated['nfc_uid'] ?? $customer->nfc_card_uid,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer Card locked successfully.',
        ]);
    }

    public function lockCard(Customer $customer)
    {
        $customer->update([
            'card_status' => 'LOCKED',
            'card_locked_at' => $customer->card_locked_at ?: now(),
        ]);

        return back()->with('success', 'Customer Card marked as locked.');
    }

    public function deactivateCard(Customer $customer)
    {
        $customer->update([
            'card_status' => 'DISABLED',
        ]);

        return back()->with('success', 'Customer Card deactivated.');
    }

    public function reissueVaultCard(Customer $customer)
    {
        $customer->update([
            'vault_token' => Customer::generateVaultToken(),
            'card_status' => 'ISSUED',
            'card_issued_at' => now(),
            'card_written_at' => null,
            'card_locked_at' => null,
            'nfc_card_uid' => null,
        ]);

        return back()->with('success', 'New Smart Vault Card generated. Old card will no longer work.');
    }

    private function publicBaseUrl(): string
    {
        $website = trim((string) \App\Models\BusinessSetting::query()->value('website'));

        return rtrim($website !== '' ? $website : config('app.url'), '/');
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $this->validateCustomer($request, $customer);

        $customer->update($validated);

        return back()->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->transactions()->exists() || $customer->metalTransactions()->exists() || $customer->mortgages()->exists()) {
            return back()->withErrors([
                'customer' => 'Customer cannot be deleted because ledger or mortgage records already exist.',
            ]);
        }

        $customer->delete();

        return back()->with('success', 'Customer deleted successfully.');
    }

    public function quickStore(Request $request)
    {
        $validated = $this->validateCustomer($request);

        $customer = Customer::create($validated);

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'mobile' => $customer->mobile,
        ], 201);
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

    private function validateCustomer(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => [
                'required',
                'string',
                'max:20',
                Rule::unique('customers', 'mobile')->ignore($customer?->id),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'pan_no' => ['nullable', 'string', 'max:20'],
            'aadhaar_no' => ['nullable', 'string', 'max:20'],
            'dob' => ['nullable', 'date'],
            'anniversary_date' => ['nullable', 'date'],
            'membership_id' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function countUpcomingOccasions(string $column, Carbon $today, int $daysAhead = 7): int
    {
        return Customer::query()
            ->whereNotNull($column)
            ->pluck($column)
            ->filter(function ($value) use ($today, $daysAhead) {
                return $this->buildOccasionReminder($value, $today, $daysAhead) !== null;
            })
            ->count();
    }

    private function buildOccasionReminder(?string $value, Carbon $today, int $daysAhead = 7): ?array
    {
        if (blank($value)) {
            return null;
        }

        $sourceDate = Carbon::parse($value);
        $month = $sourceDate->month;
        $targetDay = min($sourceDate->day, Carbon::create($today->year, $month, 1)->daysInMonth);
        $nextDate = Carbon::create($today->year, $month, $targetDay)->startOfDay();

        if ($nextDate->lt($today->copy()->startOfDay())) {
            $nextYear = $today->year + 1;
            $targetDay = min($sourceDate->day, Carbon::create($nextYear, $month, 1)->daysInMonth);
            $nextDate = Carbon::create($nextYear, $month, $targetDay)->startOfDay();
        }

        $daysUntil = $today->copy()->startOfDay()->diffInDays($nextDate, false);

        if ($daysUntil < 0 || $daysUntil > $daysAhead) {
            return null;
        }

        return [
            'date' => $nextDate->toDateString(),
            'days_until' => $daysUntil,
            'is_today' => $daysUntil === 0,
        ];
    }
}
