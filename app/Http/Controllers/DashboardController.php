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

    public function index()
    {
        $user = Auth::user();
        $today = Carbon::today();
        $customerReminders = $this->buildCustomerReminders($today);
        $lastClosedRegister = $this->lastClosedRegister();
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
                    'balance_after' => (float) $movement->balance_after,
                    'reference' => $movement->reference,
                    'note' => $movement->note,
                    'time' => optional($movement->recorded_at)?->diffForHumans(),
                ]);

            return Inertia::render('dashboard/AdminDashboard', [
                'rates' => $rates,
                'isDayOpen' => $isDayOpen,
                'opening_expectation' => [
                    'cash' => (float) ($lastClosedRegister?->closing_cash ?? 0),
                    'gold' => (float) ($lastClosedRegister?->closing_gold ?? 0),
                    'silver' => (float) ($lastClosedRegister?->closing_silver ?? 0),
                    'date' => optional($lastClosedRegister?->date)?->toDateString(),
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

            $orderAttention = OrderItem::query()
                ->with(['order.customer'])
                ->whereIn('status', ['NEW', 'ASSIGNED', 'READY'])
                ->orderByRaw("CASE status WHEN 'READY' THEN 1 WHEN 'ASSIGNED' THEN 2 ELSE 3 END")
                ->take(6)
                ->get()
                ->map(function (OrderItem $item) {
                    $dueDate = $item->order?->due_date;

                    return [
                        'id' => $item->id,
                        'status' => $item->status,
                        'customer_name' => $item->order?->customer?->name ?? 'Walk-in',
                        'design_name' => $item->item_name,
                        'due_date' => $dueDate ? Carbon::parse($dueDate)->toDateString() : null,
                        'is_overdue' => $dueDate
                            ? Carbon::parse($dueDate)->lt($today) && $item->status !== 'READY'
                            : false,
                    ];
                });

            $recentInvoices = Invoice::query()
                ->with('customer')
                ->where('user_id', $user->id)
                ->latest('date')
                ->take(5)
                ->get()
                ->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name' => $invoice->customer?->name ?? 'Walk-in',
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

            return Inertia::render('dashboard/StaffDashboard', [
                'user' => $user,
                'rates' => $rates,
                'isDayOpen' => $isDayOpen,
                'opening_expectation' => [
                    'cash' => (float) ($lastClosedRegister?->closing_cash ?? 0),
                    'gold' => (float) ($lastClosedRegister?->closing_gold ?? 0),
                    'silver' => (float) ($lastClosedRegister?->closing_silver ?? 0),
                    'date' => optional($lastClosedRegister?->date)?->toDateString(),
                ],
                'metrics' => [
                    'my_sales' => (float) $mySales,
                    'my_collections' => (float) $myCollections,
                    'my_invoices' => $myInvoicesCount,
                    'new_orders' => $orderMetrics['new'],
                    'in_production' => $orderMetrics['assigned'],
                    'ready_items' => $orderMetrics['ready'],
                    'overdue_items' => $orderMetrics['overdue'],
                ],
                'recent_invoices' => $recentInvoices,
                'attention_items' => $orderAttention,
                'customer_reminders' => $customerReminders,
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
        $isInitialSetup = ! DailyRegister::query()->exists();

        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', $isInitialSetup ? 'min:0' : 'gt:0'],
            'opening_gold' => ['required', 'numeric', $isInitialSetup ? 'min:0' : 'gt:0'],
            'opening_silver' => ['required', 'numeric', $isInitialSetup ? 'min:0' : 'gt:0'],
            'mismatch_reason' => 'nullable|string|max:500',
            'reopen_reason' => 'nullable|string|max:500',
        ]);

        $lastClosedRegister = $this->lastClosedRegister();
        $expectedOpeningCash = (float) ($lastClosedRegister?->closing_cash ?? 0);
        $expectedOpeningGold = (float) ($lastClosedRegister?->closing_gold ?? 0);
        $expectedOpeningSilver = (float) ($lastClosedRegister?->closing_silver ?? 0);
        $hasExpectation = $lastClosedRegister !== null;

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

        DB::transaction(function () use ($validated, $expectedOpeningCash, $expectedOpeningGold, $expectedOpeningSilver, $today, $todayLastRegister, $isReopen, $isInitialSetup) {
            $today = Carbon::today();
            DailyRegister::create([
                'date' => $today,
                'session_number' => ($todayLastRegister?->session_number ?? 0) + 1,
                'opening_cash' => $validated['opening_cash'],
                'opening_gold' => $validated['opening_gold'],
                'opening_silver' => $validated['opening_silver'],
                'expected_opening_cash' => $expectedOpeningCash ?: null,
                'expected_opening_gold' => $expectedOpeningGold ?: null,
                'expected_opening_silver' => $expectedOpeningSilver ?: null,
                'opening_mismatch_reason' => blank(trim((string) ($validated['mismatch_reason'] ?? ''))) ? null : trim((string) $validated['mismatch_reason']),
                'reopen_reason' => $isReopen ? trim((string) $validated['reopen_reason']) : null,
                'reopened_from_id' => $isReopen ? $todayLastRegister?->id : null,
                'opened_by' => Auth::id(),
            ]);

            $cashVault = Vault::firstOrCreate(['type' => 'CASH'], ['name' => 'CASH', 'balance' => 0]);
            $goldVault = Vault::firstOrCreate(['type' => 'GOLD'], ['name' => 'GOLD', 'balance' => 0]);
            $silverVault = Vault::firstOrCreate(['type' => 'SILVER'], ['name' => 'SILVER', 'balance' => 0]);

            if ($isInitialSetup) {
                $cashBefore = (float) $cashVault->balance;
                $goldBefore = (float) $goldVault->balance;
                $silverBefore = (float) $silverVault->balance;
                $cashAfter = round((float) $validated['opening_cash'], 2);
                $goldAfter = round((float) $validated['opening_gold'], 3);
                $silverAfter = round((float) $validated['opening_silver'], 3);

                $cashVault->update(['balance' => $cashAfter]);
                $goldVault->update(['balance' => $goldAfter]);
                $silverVault->update(['balance' => $silverAfter]);

                VaultMovement::create([
                    'vault_id' => $cashVault->id,
                    'vault_type' => VaultType::CASH->value,
                    'direction' => 'CREDIT',
                    'amount' => $cashAfter,
                    'balance_before' => $cashBefore,
                    'balance_after' => $cashAfter,
                    'source_type' => DailyRegister::class,
                    'reference' => 'Initial Opening Balance',
                    'note' => 'Initial cash balance created during first-time system setup',
                    'user_id' => Auth::id(),
                    'recorded_at' => now(),
                ]);

                VaultMovement::create([
                    'vault_id' => $goldVault->id,
                    'vault_type' => VaultType::GOLD->value,
                    'direction' => 'CREDIT',
                    'amount' => $goldAfter,
                    'balance_before' => $goldBefore,
                    'balance_after' => $goldAfter,
                    'source_type' => DailyRegister::class,
                    'reference' => 'Initial Opening Balance',
                    'note' => 'Initial gold balance created during first-time system setup',
                    'user_id' => Auth::id(),
                    'recorded_at' => now(),
                ]);

                VaultMovement::create([
                    'vault_id' => $silverVault->id,
                    'vault_type' => VaultType::SILVER->value,
                    'direction' => 'CREDIT',
                    'amount' => $silverAfter,
                    'balance_before' => $silverBefore,
                    'balance_after' => $silverAfter,
                    'source_type' => DailyRegister::class,
                    'reference' => 'Initial Opening Balance',
                    'note' => 'Initial silver balance created during first-time system setup',
                    'user_id' => Auth::id(),
                    'recorded_at' => now(),
                ]);

                return;
            }

            VaultMovement::create([
                'vault_id' => $cashVault->id,
                'vault_type' => VaultType::CASH->value,
                'direction' => 'CREDIT',
                'amount' => 0,
                'balance_before' => (float) $cashVault->balance,
                'balance_after' => (float) $cashVault->balance,
                'source_type' => DailyRegister::class,
                'reference' => 'Day Open Snapshot',
                'note' => blank(trim((string) ($validated['mismatch_reason'] ?? '')))
                    ? 'Opening cash verified for the day without changing live vault balance'
                    : 'Opening cash verified with mismatch reason: ' . trim((string) $validated['mismatch_reason']),
                'user_id' => Auth::id(),
                'recorded_at' => now(),
            ]);

            VaultMovement::create([
                'vault_id' => $goldVault->id,
                'vault_type' => VaultType::GOLD->value,
                'direction' => 'CREDIT',
                'amount' => 0,
                'balance_before' => (float) $goldVault->balance,
                'balance_after' => (float) $goldVault->balance,
                'source_type' => DailyRegister::class,
                'reference' => 'Day Open Snapshot',
                'note' => blank(trim((string) ($validated['mismatch_reason'] ?? '')))
                    ? 'Opening gold verified for the day without changing live vault balance'
                    : 'Opening gold verified with mismatch reason: ' . trim((string) $validated['mismatch_reason']),
                'user_id' => Auth::id(),
                'recorded_at' => now(),
            ]);

            VaultMovement::create([
                'vault_id' => $silverVault->id,
                'vault_type' => VaultType::SILVER->value,
                'direction' => 'CREDIT',
                'amount' => 0,
                'balance_before' => (float) $silverVault->balance,
                'balance_after' => (float) $silverVault->balance,
                'source_type' => DailyRegister::class,
                'reference' => 'Day Open Snapshot',
                'note' => blank(trim((string) ($validated['mismatch_reason'] ?? '')))
                    ? 'Opening silver verified for the day without changing live vault balance'
                    : 'Opening silver verified with mismatch reason: ' . trim((string) $validated['mismatch_reason']),
                'user_id' => Auth::id(),
                'recorded_at' => now(),
            ]);
        });

        return redirect()->back()->with('success', $isInitialSetup
            ? 'Initial opening balance saved and shop day opened.'
            : 'Good Morning! Shop is Open.');
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
