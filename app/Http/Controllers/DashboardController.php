<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\Vault;
use App\Models\Karigar;
use App\Models\VaultMovement;
use App\Enums\VaultType;
use App\Models\DailyRate;
use App\Models\Transaction;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\DailyRegister;
use App\Services\VaultService;
use App\Services\DayOpeningService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    private function buildCustomerReminders(Carbon $today, int $daysAhead = 7)
    {
        $customers = Customer::query()
            ->whereNotNull('dob')
            ->orWhereNotNull('anniversary_date')
            ->get(['id', 'name', 'mobile', 'dob', 'anniversary_date']);

        return $customers
            ->flatMap(function (Customer $customer) use ($today, $daysAhead) {
                return collect([
                    ['type' => 'Birthday', 'date' => $customer->dob],
                    ['type' => 'Anniversary', 'date' => $customer->anniversary_date],
                ])->filter(fn ($entry) => ! empty($entry['date']))
                    ->map(function ($entry) use ($customer, $today, $daysAhead) {
                        $sourceDate = Carbon::parse($entry['date']);
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
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->name,
                            'mobile' => $customer->mobile,
                            'type' => $entry['type'],
                            'date' => $nextDate->toDateString(),
                            'days_until' => $daysUntil,
                            'is_today' => $daysUntil === 0,
                        ];
                    })
                    ->filter();
            })
            ->sortBy([
                ['days_until', 'asc'],
                ['customer_name', 'asc'],
            ])
            ->values()
            ->all();
    }

    private function buildAnalytics(Carbon $today, $rates, $vaults): array
    {
        $startDate = $today->copy()->subDays(29)->startOfDay();

        $salesByDate = Invoice::query()
            ->where('date', '>=', $startDate->toDateString())
            ->where('status', '!=', 'CANCELLED')
            ->selectRaw('date, SUM(total_amount) as total, COUNT(id) as count')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->date)->toDateString());

        $collectionsByDate = Transaction::query()
            ->where('transactable_type', Customer::class)
            ->whereIn('type', ['PAYMENT', 'RECEIPT'])
            ->where('date', '>=', $startDate->toDateString())
            ->selectRaw('date, SUM(amount) as total')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->date)->toDateString());

        $expensesByDate = Expense::query()
            ->whereDate('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->date)->toDateString());

        $salesTrend = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i);
            $dateStr = $d->toDateString();
            $sale = (float) ($salesByDate[$dateStr]->total ?? 0);
            $count = (int) ($salesByDate[$dateStr]->count ?? 0);
            $coll = (float) ($collectionsByDate[$dateStr]->total ?? 0);
            $exp = (float) ($expensesByDate[$dateStr]->total ?? 0);

            $salesTrend[] = [
                'date' => $dateStr,
                'label' => $d->format('d M'),
                'short_label' => $d->format('D'),
                'sales' => $sale,
                'invoices_count' => $count,
                'collections' => $coll,
                'expenses' => $exp,
                'net_flow' => $coll - $exp,
            ];
        }

        // Bullion Rate History (Last 14 days)
        $rateStartDate = $today->copy()->subDays(13)->startOfDay();
        $rateRecords = DailyRate::query()
            ->where('date', '>=', $rateStartDate->toDateString())
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->date)->toDateString());

        $bullionTrend = [];
        $lastGoldSell = (float) ($rates->gold_sell ?? 0);
        $lastSilverSell = (float) ($rates->silver_sell ?? 0);

        for ($i = 13; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i);
            $dateStr = $d->toDateString();
            $record = $rateRecords[$dateStr] ?? null;

            $goldSell = $record && (float) $record->gold_sell > 0 ? (float) $record->gold_sell : $lastGoldSell;
            $goldBuy = $record && (float) $record->gold_buy > 0 ? (float) $record->gold_buy : ($goldSell > 0 ? round($goldSell * 0.98, 2) : 0);
            $silverSell = $record && (float) $record->silver_sell > 0 ? (float) $record->silver_sell : $lastSilverSell;

            if ($goldSell > 0) $lastGoldSell = $goldSell;
            if ($silverSell > 0) $lastSilverSell = $silverSell;

            $bullionTrend[] = [
                'date' => $dateStr,
                'label' => $d->format('d M'),
                'gold_sell' => $goldSell,
                'gold_buy' => $goldBuy,
                'silver_sell' => $silverSell,
            ];
        }

        // Category & Metal Breakdown from items (Last 30 Days)
        $goldSoldWeight = 0;
        $silverSoldWeight = 0;
        $categoryBreakdown = [];

        $invoiceItems = \App\Models\InvoiceItem::query()
            ->whereHas('invoice', fn ($q) => $q->where('date', '>=', $startDate->toDateString())->where('status', '!=', 'CANCELLED'))
            ->get();

        foreach ($invoiceItems as $item) {
            $net = (float) ($item->net_weight > 0 ? $item->net_weight : $item->weight);
            $price = (float) ($item->final_price ?? $item->total_price ?? 0);
            $isSilver = $item->silver_product_id !== null || str_contains(strtolower($item->purity ?? ''), 'silver');

            if ($isSilver) {
                $silverSoldWeight += $net;
            } else {
                $goldSoldWeight += $net;
            }

            $cat = !empty($item->category) ? $item->category : ($isSilver ? 'Silver Ornaments' : 'Gold Jewellery');
            if (!isset($categoryBreakdown[$cat])) {
                $categoryBreakdown[$cat] = ['label' => $cat, 'count' => 0, 'amount' => 0, 'weight' => 0];
            }
            $categoryBreakdown[$cat]['count'] += 1;
            $categoryBreakdown[$cat]['amount'] += $price;
            $categoryBreakdown[$cat]['weight'] += $net;
        }

        $topCategories = collect($categoryBreakdown)->sortByDesc('amount')->values()->take(6)->all();

        // Payment Modes Breakdown for Today
        $todayPayments = Transaction::query()
            ->where('transactable_type', Customer::class)
            ->whereIn('type', ['PAYMENT', 'RECEIPT'])
            ->whereDate('date', $today)
            ->get();

        $paymentModes = [
            'CASH' => (float) $todayPayments->where('payment_method', 'CASH')->sum('amount'),
            'CARD' => (float) $todayPayments->where('payment_method', 'CARD')->sum('amount'),
            'UPI' => (float) $todayPayments->where('payment_method', 'UPI')->sum('amount'),
            'BANK' => (float) $todayPayments->where('payment_method', 'BANK')->sum('amount'),
        ];

        // Month-over-Month Comparison
        $thisMonthSales = (float) Invoice::query()
            ->whereYear('date', $today->year)
            ->whereMonth('date', $today->month)
            ->where('status', '!=', 'CANCELLED')
            ->sum('total_amount');

        $lastMonthDate = $today->copy()->subMonth();
        $lastMonthSales = (float) Invoice::query()
            ->whereYear('date', $lastMonthDate->year)
            ->whereMonth('date', $lastMonthDate->month)
            ->where('status', '!=', 'CANCELLED')
            ->sum('total_amount');

        $growthPct = $lastMonthSales > 0 ? round((($thisMonthSales - $lastMonthSales) / $lastMonthSales) * 100, 1) : 0;

        // Live Valuations
        $goldBalance = (float) ($vaults['GOLD']->balance ?? 0);
        $silverBalance = (float) ($vaults['SILVER']->balance ?? 0);
        $goldRate = (float) ($rates->gold_sell ?? 0);
        $silverRate = (float) ($rates->silver_sell ?? 0);

        return [
            'sales_trend' => $salesTrend,
            'bullion_trend' => $bullionTrend,
            'metal_mix' => [
                'gold_weight' => round($goldSoldWeight, 3),
                'silver_weight' => round($silverSoldWeight, 3),
                'top_categories' => $topCategories,
            ],
            'payment_modes' => $paymentModes,
            'month_metrics' => [
                'this_month_sales' => $thisMonthSales,
                'last_month_sales' => $lastMonthSales,
                'growth_pct' => $growthPct,
            ],
            'valuations' => [
                'gold_value' => round($goldBalance * $goldRate, 2),
                'silver_value' => round($silverBalance * $silverRate, 2),
                'liquid_funds' => (float) (($vaults['CASH']->balance ?? 0) + ($vaults['BANK']->balance ?? 0)),
            ],
        ];
    }

    public function index()
    {
        $user = Auth::user();
        $today = Carbon::today();
        $customerReminders = $this->buildCustomerReminders($today);
        $lastClosedRegister = $this->lastClosedRegister();
        $openingExpectation = DayOpeningService::expectation($lastClosedRegister);
        $todayRegister = DailyRegister::query()
            ->whereDate('date', $today)
            ->latest('id')
            ->first();

        // 1. GET TODAY'S RATES (Or create default if missing)
        $rates = DailyRate::firstOrCreate(
            ['date' => $today],
            ['gold_buy' => 0, 'gold_sell' => 0, 'silver_sell' => 0]
        );

        // 2. CHECK IF DAY IS OPEN
        $isDayOpen = (bool) ($todayRegister && $todayRegister->closed_at === null);

        // --- ADMIN VIEW ---
        if ($user->hasRole('admin')) {

            $karigars = Karigar::all()->map(function ($k) {

                // Calculate Gold Balance using Polymorphic Relation
                // We assume transactions are saved as: party_type = 'App\Models\Karigar', party_id = 1
                $issued = $k->metalTransactions()->where('type', 'ISSUE')->sum('gross_weight');
                $received = $k->metalTransactions()->where('type', 'RECEIPT')->sum('gross_weight');

                return [
                    'id' => $k->id,
                    'name' => $k->name,
                    'phone' => $k->phone,
                    // Positive = They have your gold. Negative = You owe them (unlikely for metal).
                    'gold_due' => $issued - $received,
                    'status' => 'ACTIVE' // You can add a logic here
                ];
            })
                // Only show Karigars who actually have gold right now
                ->filter(fn($k) => $k['gold_due'] > 0.001)
                ->values();

            $vaults = Vault::all()->keyBy('type');

            $todaySales = Transaction::query()
                ->where('type', 'SALE')
                ->whereDate('date', $today)
                ->sum('amount');

            $todayCollections = Transaction::query()
                ->where('transactable_type', Customer::class)
                ->whereIn('type', ['PAYMENT', 'RECEIPT'])
                ->whereDate('date', $today)
                ->sum('amount');

            $todayExpenses = Expense::query()
                ->whereDate('created_at', $today)
                ->sum('amount');

            $orderMetrics = [
                'new' => OrderItem::where('status', 'NEW')->count(),
                'assigned' => OrderItem::where('status', 'ASSIGNED')->count(),
                'ready' => OrderItem::where('status', 'READY')->count(),
                'overdue' => OrderItem::whereIn('status', ['NEW', 'ASSIGNED'])
                    ->whereHas('order', fn ($query) => $query->whereDate('due_date', '<', $today))
                    ->count(),
            ];

            $recentInvoices = Invoice::query()
                ->with('customer')
                ->latest('date')
                ->take(5)
                ->get()
                ->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name' => $invoice->customer?->name ?? 'Walk-in',
                    'date' => $invoice->date,
                    'total_amount' => (float) $invoice->total_amount,
                ]);

            $recentExpenses = Expense::query()
                ->with('user')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (Expense $expense) => [
                    'id' => $expense->id,
                    'title' => $expense->title,
                    'category' => $expense->category,
                    'amount' => (float) $expense->amount,
                    'payment_method' => $expense->payment_method,
                    'time' => optional($expense->created_at)?->diffForHumans(),
                    'user' => $expense->user?->name ?? 'System',
                ]);

            $recentActivity = Transaction::with('transactable')
                ->latest()
                ->take(8)
                ->get()
                ->map(function ($txn) {
                    return [
                        'id' => $txn->id,
                        'desc' => $txn->description,
                        'amount' => $txn->amount,
                        'type' => $txn->type,
                        'time' => $txn->created_at->diffForHumans(),
                        'user' => $txn->transactable->name ?? 'System',
                    ];
                });

            $recentVaultMovements = VaultMovement::query()
                ->latest('recorded_at')
                ->take(10)
                ->get()
                ->map(fn (VaultMovement $movement) => [
                    'id' => $movement->id,
                    'vault_type' => $movement->vault_type,
                    'direction' => $movement->direction,
                    'amount' => (float) $movement->amount,
                    'gross_weight' => $movement->gross_weight !== null ? (float) $movement->gross_weight : null,
                    'fine_weight' => $movement->fine_weight !== null ? (float) $movement->fine_weight : null,
                    'purity_percent' => $movement->purity_percent !== null ? (float) $movement->purity_percent : null,
                    'balance_after' => (float) $movement->balance_after,
                    'reference' => $movement->reference,
                    'correlation_id' => $movement->correlation_id,
                    'note' => $movement->note,
                    'time' => optional($movement->recorded_at)?->diffForHumans(),
                ]);

            $analytics = $this->buildAnalytics($today, $rates, $vaults);

            return Inertia::render('dashboard/AdminDashboard', [
                'rates' => $rates,
                'isDayOpen' => $isDayOpen,
                'opening_expectation' => [
                    ...$openingExpectation,
                ],
                'vaults' => [
                    'cash' => $vaults['CASH']->balance ?? 0,
                    'gold' => $vaults['GOLD']->balance ?? 0,
                    'silver' => $vaults['SILVER']->balance ?? 0,
                    'bank' => $vaults['BANK']->balance ?? 0,
                ],
                'metrics' => [
                    'today_sales' => (float) $todaySales,
                    'today_collections' => (float) $todayCollections,
                    'today_expenses' => (float) $todayExpenses,
                    'new_orders' => $orderMetrics['new'],
                    'in_production' => $orderMetrics['assigned'],
                    'ready_items' => $orderMetrics['ready'],
                    'overdue_items' => $orderMetrics['overdue'],
                ],
                'analytics' => $analytics,
                'karigars' => $karigars,
                'activities' => $recentActivity,
                'recent_vault_movements' => $recentVaultMovements,
                'recent_invoices' => $recentInvoices,
                'recent_expenses' => $recentExpenses,
                'customer_reminders' => $customerReminders,
            ]);
        }

        // --- STAFF VIEW ---
        else {
            $mySales = Invoice::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $today)
                ->where('status', '!=', 'CANCELLED')
                ->sum('total_amount');

            $myCollections = Transaction::query()
                ->where('user_id', $user->id)
                ->whereIn('type', ['PAYMENT', 'RECEIPT'])
                ->whereDate('date', $today)
                ->sum('amount');

            $myInvoicesCount = Invoice::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $today)
                ->where('status', '!=', 'CANCELLED')
                ->count();

            // Month-to-date Personal Performance
            $myMonthSales = Invoice::query()
                ->where('user_id', $user->id)
                ->whereYear('date', $today->year)
                ->whereMonth('date', $today->month)
                ->where('status', '!=', 'CANCELLED')
                ->sum('total_amount');

            $myMonthInvoicesCount = Invoice::query()
                ->where('user_id', $user->id)
                ->whereYear('date', $today->year)
                ->whereMonth('date', $today->month)
                ->where('status', '!=', 'CANCELLED')
                ->count();

            // Store-wide today sales
            $storeTodaySales = Invoice::query()
                ->whereDate('date', $today)
                ->where('status', '!=', 'CANCELLED')
                ->sum('total_amount');

            // Ready for Customer Delivery (Pickup Alert Desk)
            $readyForDelivery = OrderItem::query()
                ->with(['order.customer'])
                ->where('status', 'READY')
                ->latest('updated_at')
                ->take(6)
                ->get()
                ->map(function (OrderItem $item) {
                    return [
                        'id' => $item->id,
                        'order_id' => $item->order_id,
                        'status' => $item->status,
                        'customer_name' => $item->order?->customer?->name ?? 'Walk-in Customer',
                        'customer_phone' => $item->order?->customer?->mobile ?? null,
                        'design_name' => $item->item_name,
                        'target_weight' => (float) ($item->target_weight ?? 0),
                        'finished_weight' => (float) ($item->finished_weight ?? 0),
                        'due_date' => $item->order?->due_date ? Carbon::parse($item->order->due_date)->toDateString() : null,
                    ];
                });

            // Workshop / In-Production Attention Items (New, Assigned, Overdue)
            $orderAttention = OrderItem::query()
                ->with(['order.customer'])
                ->whereIn('status', ['NEW', 'ASSIGNED'])
                ->orderByRaw("CASE WHEN status = 'ASSIGNED' THEN 1 ELSE 2 END")
                ->take(6)
                ->get()
                ->map(function (OrderItem $item) use ($today) {
                    $dueDate = $item->order?->due_date;

                    return [
                        'id' => $item->id,
                        'order_id' => $item->order_id,
                        'status' => $item->status,
                        'customer_name' => $item->order?->customer?->name ?? 'Walk-in',
                        'customer_phone' => $item->order?->customer?->mobile ?? null,
                        'design_name' => $item->item_name,
                        'target_weight' => (float) ($item->target_weight ?? 0),
                        'due_date' => $dueDate ? Carbon::parse($dueDate)->toDateString() : null,
                        'is_overdue' => $dueDate
                            ? Carbon::parse($dueDate)->lt($today)
                            : false,
                    ];
                });

            // Recent Invoices by this user (or latest store invoices if staff hasn't billed yet)
            $recentInvoices = Invoice::query()
                ->with('customer')
                ->where('user_id', $user->id)
                ->latest('id')
                ->take(6)
                ->get()
                ->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name' => $invoice->customer?->name ?? 'Walk-in',
                    'customer_phone' => $invoice->customer?->mobile ?? null,
                    'date' => $invoice->date,
                    'total_amount' => (float) $invoice->total_amount,
                    'status' => $invoice->status,
                ]);

            $orderMetrics = [
                'new' => OrderItem::query()->where('status', 'NEW')->count(),
                'assigned' => OrderItem::query()->where('status', 'ASSIGNED')->count(),
                'ready' => OrderItem::query()->where('status', 'READY')->count(),
                'overdue' => OrderItem::query()
                    ->whereIn('status', ['NEW', 'ASSIGNED'])
                    ->whereHas('order', fn ($query) => $query->whereDate('due_date', '<', $today))
                    ->count(),
            ];

            // Assigned / Showroom Tasks for Staff
            $myTasks = \App\Models\Task::query()
                ->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhereNull('assigned_to');
                })
                ->whereIn('status', ['TODO', 'IN_PROGRESS'])
                ->orderByDesc('is_pinned')
                ->orderBy('due_date')
                ->take(5)
                ->get()
                ->map(fn (\App\Models\Task $t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'description' => $t->description,
                    'priority' => $t->priority,
                    'category' => $t->category,
                    'status' => $t->status,
                    'due_date' => $t->due_date ? Carbon::parse($t->due_date)->toDateString() : null,
                    'is_overdue' => $t->is_overdue,
                    'checklist' => $t->checklist ?? [],
                    'checklist_progress' => $t->checklist_progress,
                    'total_subtasks' => $t->total_subtasks,
                    'completed_subtasks' => $t->completed_subtasks,
                ]);

            // Staff Attendance for today
            $myAttendance = \App\Models\StaffAttendance::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $today)
                ->first();

            $attendanceData = [
                'status' => $myAttendance ? $myAttendance->status : 'NOT_MARKED',
                'check_in_at' => $myAttendance?->check_in_at ? Carbon::parse($myAttendance->check_in_at)->format('h:i A') : null,
                'check_out_at' => $myAttendance?->check_out_at ? Carbon::parse($myAttendance->check_out_at)->format('h:i A') : null,
            ];

            return Inertia::render('dashboard/StaffDashboard', [
                'user' => $user,
                'rates' => $rates,
                'isDayOpen' => $isDayOpen,
                'opening_expectation' => [
                    ...$openingExpectation,
                ],
                'metrics' => [
                    'my_sales' => (float) $mySales,
                    'my_collections' => (float) $myCollections,
                    'my_invoices' => $myInvoicesCount,
                    'my_month_sales' => (float) $myMonthSales,
                    'my_month_invoices' => $myMonthInvoicesCount,
                    'store_today_sales' => (float) $storeTodaySales,
                    'new_orders' => $orderMetrics['new'],
                    'in_production' => $orderMetrics['assigned'],
                    'ready_items' => $orderMetrics['ready'],
                    'overdue_items' => $orderMetrics['overdue'],
                ],
                'recent_invoices' => $recentInvoices,
                'ready_for_delivery' => $readyForDelivery,
                'attention_items' => $orderAttention,
                'customer_reminders' => $customerReminders,
                'my_tasks' => $myTasks,
                'my_attendance' => $attendanceData,
            ]);
        }
    }

    // UPDATE LIVE RATES
    public function updateRates(Request $request)
    {
        $validated = $request->validate([
            'gold_sell' => 'required|numeric',
            'gold_buy' => 'required|numeric',
            'silver_sell' => 'required|numeric',
        ]);

        DailyRate::updateOrCreate(
            ['date' => Carbon::today()],
            $validated
        );

        return redirect()->back()->with('success', 'Market Rates Updated');
    }

    // OPEN THE DAY (Verify Cash/Gold/Silver)
    public function openDay(Request $request)
    {
        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'opening_gold' => ['required', 'numeric', 'min:0'],
            'opening_silver' => ['required', 'numeric', 'min:0'],
            'mismatch_reason' => 'nullable|string|max:500',
            'reopen_reason' => 'nullable|string|max:500',
        ]);

        $lastClosedRegister = $this->lastClosedRegister();
        $openingExpectation = DayOpeningService::expectation($lastClosedRegister);
        $expectedOpeningCash = $openingExpectation['cash'];
        $expectedOpeningGold = $openingExpectation['gold'];
        $expectedOpeningSilver = $openingExpectation['silver'];
        $hasExpectation = $openingExpectation['has_expectation'];

        $cashMatches = ! $hasExpectation || abs((float) $validated['opening_cash'] - $expectedOpeningCash) < 0.0001;
        $goldMatches = ! $hasExpectation || abs((float) $validated['opening_gold'] - $expectedOpeningGold) < 0.0001;
        $silverMatches = ! $hasExpectation || abs((float) $validated['opening_silver'] - $expectedOpeningSilver) < 0.0001;

        if ((! $cashMatches || ! $goldMatches || ! $silverMatches) && blank(trim((string) ($validated['mismatch_reason'] ?? '')))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'mismatch_reason' => 'Opening balances differ from the last closed day. Add a reason before opening the day.',
            ]);
        }

        $today = Carbon::today();
        $openRegister = DailyRegister::query()
            ->whereDate('date', $today)
            ->whereNull('closed_at')
            ->latest('id')
            ->first();

        if ($openRegister) {
            return redirect()->back()->with('success', 'Shop day is already open.');
        }

        $todayLastRegister = DailyRegister::query()
            ->whereDate('date', $today)
            ->latest('id')
            ->first();

        $isReopen = $todayLastRegister !== null;

        if ($isReopen && blank(trim((string) ($validated['reopen_reason'] ?? '')))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'reopen_reason' => 'Add a reason before reopening the day.',
            ]);
        }

        DB::transaction(function () use ($validated, $expectedOpeningCash, $expectedOpeningGold, $expectedOpeningSilver, $hasExpectation, $today, $todayLastRegister, $isReopen) {
            DailyRegister::create([
                'date' => $today,
                'session_number' => ($todayLastRegister?->session_number ?? 0) + 1,
                'opening_cash' => $validated['opening_cash'],
                'opening_gold' => $validated['opening_gold'],
                'opening_silver' => $validated['opening_silver'],
                'expected_opening_cash' => $hasExpectation ? $expectedOpeningCash : null,
                'expected_opening_gold' => $hasExpectation ? $expectedOpeningGold : null,
                'expected_opening_silver' => $hasExpectation ? $expectedOpeningSilver : null,
                'opening_mismatch_reason' => blank(trim((string) ($validated['mismatch_reason'] ?? ''))) ? null : trim((string) $validated['mismatch_reason']),
                'reopen_reason' => $isReopen ? trim((string) $validated['reopen_reason']) : null,
                'reopened_from_id' => $isReopen ? $todayLastRegister?->id : null,
                'opened_by' => Auth::id(),
            ]);
        });

        return redirect()->back()->with('success', 'Good Morning! Shop is Open.');
    }

    public function closeDay(Request $request)
    {
        $validated = $request->validate([
            'closing_cash' => 'required|numeric',
            'closing_gold' => 'required|numeric',
            'closing_silver' => 'required|numeric',
        ]);

        $today = Carbon::today();
        $register = DailyRegister::whereDate('date', $today)->latest('id')->firstOrFail();

        // 1. GET SYSTEM BALANCE (What the software thinks you have)
        $systemCash = VaultService::getBalance(VaultType::CASH);
        $systemGold = VaultService::getBalance(VaultType::GOLD);
        $systemSilver = VaultService::getBalance(VaultType::SILVER);

        // 2. CALCULATE DIFFERENCE (Physical - System)
        // If negative, money is missing. If positive, you have extra.
        $diffCash = $validated['closing_cash'] - $systemCash;
        $diffGold = round((float) $validated['closing_gold'] - $systemGold, 3);
        $diffSilver = round((float) $validated['closing_silver'] - $systemSilver, 3);

        // 3. CLOSE THE REGISTER
        $register->update([
            'closing_cash' => $validated['closing_cash'],
            'closing_gold' => $validated['closing_gold'],
            'closing_silver' => $validated['closing_silver'],
            'difference_cash' => $diffCash,
            'difference_gold' => $diffGold,
            'difference_silver' => $diffSilver,
            'closed_at' => now(),
            'closed_by' => Auth::id(),
        ]);

        // Optional: If difference is big, you might want to auto-create a "Shortage" expense here.

        return Inertia::location(route('dashboard'));
    }

    public function addFunds(Request $request)
    {
        $validated = $request->validate([
            'from_vault' => 'required|in:CASH,BANK',
            'to_vault' => 'required|in:CASH,BANK|different:from_vault',
            'amount' => 'required|numeric|gt:0',
            'note' => 'required|string|max:255',
            'date' => 'nullable|date',
        ]);

        $validated['note'] = trim((string) $validated['note']);

        if ($validated['note'] === '') {
            throw ValidationException::withMessages([
                'note' => 'Enter a note for this transfer.',
            ]);
        }

        $sourceLabels = [
            'CASH' => 'cash in hand',
            'BANK' => 'bank',
        ];

        $availableBalance = VaultService::getBalance(VaultType::from($validated['from_vault']));

        if ((float) $validated['amount'] > $availableBalance) {
            throw ValidationException::withMessages([
                'amount' => 'Transfer amount exceeds available ' . $sourceLabels[$validated['from_vault']] . ' balance of ' . number_format($availableBalance, 2, '.', '') . '.',
            ]);
        }

        try {
            DB::transaction(function () use ($validated) {
                $fromVault = VaultType::from($validated['from_vault']);
                $toVault = VaultType::from($validated['to_vault']);
                $reference = "Vault Transfer {$validated['from_vault']}->{$validated['to_vault']}";
                $recordedAt = !empty($validated['date']) ? Carbon::parse($validated['date']) : now();

                VaultService::debit($fromVault, (float) $validated['amount'], [
                    'source_type' => DailyRegister::class,
                    'reference' => $reference,
                    'user_id' => Auth::id(),
                    'recorded_at' => $recordedAt,
                    'note' => "Internal transfer out: {$validated['note']}",
                ]);

                VaultService::credit($toVault, (float) $validated['amount'], [
                    'source_type' => DailyRegister::class,
                    'reference' => $reference,
                    'user_id' => Auth::id(),
                    'recorded_at' => $recordedAt,
                    'note' => "Internal transfer in: {$validated['note']}",
                ]);
            });
        } catch (\Exception $exception) {
            throw ValidationException::withMessages([
                'amount' => $exception->getMessage(),
            ]);
        }

        return redirect()->back()->with('success', 'Vault transfer recorded successfully.');
    }

    private function lastClosedRegister(): ?DailyRegister
    {
        return DailyRegister::query()
            ->whereNotNull('closed_at')
            ->latest('date')
            ->latest('id')
            ->first();
    }
}
