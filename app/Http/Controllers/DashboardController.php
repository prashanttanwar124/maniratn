<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Order;
use App\Models\Vault;
use App\Models\Karigar;
use App\Enums\VaultType;
use App\Models\DailyRate;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\DailyRegister;
use App\Services\VaultService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // 1. GET TODAY'S RATES (Or create default if missing)
        $rates = DailyRate::firstOrCreate(
            ['date' => Carbon::today()],
            ['gold_buy' => 0, 'gold_sell' => 0, 'silver_sell' => 0]
        );

        // 2. CHECK IF DAY IS OPEN
        $isDayOpen = DailyRegister::where('date', Carbon::today())->exists();

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

            // --- 2. RECENT ACTIVITY (No Change) ---
            $recentActivity = Transaction::with('transactable')
                ->latest()
                ->take(5)
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
            $vaults = Vault::all()->keyBy('type');

            return Inertia::render('dashboard/AdminDashboard', [
                'rates' => $rates,
                'isDayOpen' => $isDayOpen,
                'vaults' => [
                    'cash' => $vaults['CASH']->balance ?? 0,
                    'gold' => $vaults['GOLD']->balance ?? 0,
                    'bank' => $vaults['BANK']->balance ?? 0,
                ],
                'karigars' => $karigars,
                'activities' => $recentActivity,
                'pending_tasks' => 3,
            ]);
        }

        // --- STAFF VIEW ---
        else {
            $mySales = Transaction::where('user_id', $user->id)
                ->where('type', 'RECEIPT')
                ->whereDate('created_at', Carbon::today())
                ->sum('amount');

            return Inertia::render('dashboard/StaffDashboard', [
                'user' => $user,
                'rates' => $rates,
                'metrics' => [
                    'my_sales' => $mySales,
                    'pending_orders' => 3
                ]
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

    // OPEN THE DAY (Verify Cash/Gold)
    public function openDay(Request $request)
    {
        $validated = $request->validate([
            'opening_cash' => 'required|numeric',
            'opening_gold' => 'required|numeric',
        ]);

        DB::transaction(function () use ($validated) {
            // 1. Record Register
            DailyRegister::create([
                'date' => Carbon::today(),
                'opening_cash' => $validated['opening_cash'],
                'opening_gold' => $validated['opening_gold'],
                'opened_by' => Auth::id(),
            ]);

            // 2. Adjust Vaults if needed (Simplified: We just set them for now)
            Vault::where('type', 'CASH')->update(['balance' => $validated['opening_cash']]);
            Vault::where('type', 'GOLD')->update(['balance' => $validated['opening_gold']]);
        });

        return redirect()->back()->with('success', 'Good Morning! Shop is Open.');
    }

    public function closeDay(Request $request)
    {
        $validated = $request->validate([
            'closing_cash' => 'required|numeric',
            'closing_gold' => 'required|numeric',
        ]);

        $today = Carbon::today();
        $register = DailyRegister::where('date', $today)->firstOrFail();

        // 1. GET SYSTEM BALANCE (What the software thinks you have)
        $systemCash = VaultService::getBalance(VaultType::CASH);

        // 2. CALCULATE DIFFERENCE (Physical - System)
        // If negative, money is missing. If positive, you have extra.
        $diffCash = $validated['closing_cash'] - $systemCash;

        // 3. CLOSE THE REGISTER
        $register->update([
            'closing_cash' => $validated['closing_cash'],
            'closing_gold' => $validated['closing_gold'],
            'difference_cash' => $diffCash,
            'closed_at' => now(),
            'closed_by' => Auth::id(),
        ]);

        // Optional: If difference is big, you might want to auto-create a "Shortage" expense here.

        return Inertia::location(route('dashboard'));
    }
}
