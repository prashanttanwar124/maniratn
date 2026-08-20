<?php

namespace Database\Seeders;

use App\Enums\VaultType;
use App\Models\Customer;
use App\Models\DailyRate;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Karigar;
use App\Models\MetalTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultMovement;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();
        $user = User::first() ?? User::factory()->create();

        // 1. DAILY RATES (Last 14 Days)
        $baseGold = 7200;
        $baseSilver = 88;
        for ($i = 14; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i);
            $variation = ($i * 15) - 40;
            DailyRate::updateOrCreate(
                ['date' => $d->toDateString()],
                [
                    'gold_sell' => $baseGold + $variation,
                    'gold_buy' => ($baseGold + $variation) - 150,
                    'silver_sell' => $baseSilver + round($i * 0.3, 2),
                ]
            );
        }

        // 2. SAFE VAULTS & MOVEMENTS
        $vaultMap = [
            'CASH' => 68500.00,
            'BANK' => 345000.00,
            'GOLD' => 285.450,
            'SILVER' => 1450.000,
        ];

        foreach ($vaultMap as $type => $balance) {
            $vault = Vault::firstOrCreate(['type' => $type], ['name' => ucfirst(strtolower($type)).' Safe', 'balance' => 0]);
            $vault->update(['balance' => $balance]);
        }

        // Recent Vault Movements
        $sampleMovements = [
            ['vault_type' => 'CASH', 'direction' => 'CREDIT', 'amount' => 25000, 'balance_before' => 43500, 'balance_after' => 68500, 'note' => 'Counter sales cash settlement', 'recorded_at' => $today->copy()->subHours(2)],
            ['vault_type' => 'BANK', 'direction' => 'CREDIT', 'amount' => 48200, 'balance_before' => 296800, 'balance_after' => 345000, 'note' => 'HDFC UPI settlement batch', 'recorded_at' => $today->copy()->subHours(3)],
            ['vault_type' => 'GOLD', 'direction' => 'CREDIT', 'amount' => 45.200, 'balance_before' => 240.250, 'balance_after' => 285.450, 'note' => 'Old Gold purchase exchange', 'recorded_at' => $today->copy()->subHours(5)],
            ['vault_type' => 'CASH', 'direction' => 'DEBIT', 'amount' => 3500, 'balance_before' => 72000, 'balance_after' => 68500, 'note' => 'Showroom packaging & supplies', 'recorded_at' => $today->copy()->subDay()],
            ['vault_type' => 'SILVER', 'direction' => 'CREDIT', 'amount' => 250.000, 'balance_before' => 1200.000, 'balance_after' => 1450.000, 'note' => 'Silver coin stock replenishment', 'recorded_at' => $today->copy()->subDays(2)],
        ];

        foreach ($sampleMovements as $mov) {
            $v = Vault::where('type', $mov['vault_type'])->first();
            VaultMovement::create([
                'vault_id' => $v ? $v->id : 1,
                'vault_type' => $mov['vault_type'],
                'direction' => $mov['direction'],
                'amount' => $mov['amount'],
                'balance_before' => $mov['balance_before'],
                'balance_after' => $mov['balance_after'],
                'note' => $mov['note'],
                'recorded_at' => $mov['recorded_at'],
                'created_at' => $mov['recorded_at'],
                'user_id' => $user->id,
            ]);
        }

        // 3. CUSTOMERS WITH BIRTHDAYS & ANNIVERSARIES
        $customersData = [
            ['name' => 'Priya Sharma', 'mobile' => '9820112233', 'dob' => $today->copy()->format('Y-m-d'), 'city' => 'Virar West'],
            ['name' => 'Rajesh Mehta', 'mobile' => '9833445566', 'anniversary_date' => $today->copy()->addDays(2)->format('Y-m-d'), 'city' => 'Vasai'],
            ['name' => 'Anjali Desai', 'mobile' => '9892778899', 'dob' => $today->copy()->addDays(4)->format('Y-m-d'), 'city' => 'Nallasopara'],
            ['name' => 'Vikram Singhania', 'mobile' => '9819001122', 'anniversary_date' => $today->copy()->addDays(6)->format('Y-m-d'), 'city' => 'Borivali'],
            ['name' => 'Sunita Patwardhan', 'mobile' => '9869223344', 'dob' => $today->copy()->addDays(1)->format('Y-m-d'), 'city' => 'Virar East'],
        ];

        $seededCustomers = [];
        foreach ($customersData as $c) {
            $seededCustomers[] = Customer::updateOrCreate(['mobile' => $c['mobile']], $c);
        }

        // 4. KARIGARS WITH METAL DUE
        $karigars = Karigar::all();
        if ($karigars->count() < 3) {
            $karigars = collect([
                Karigar::create(['name' => 'Ramesh Goldsmith Workshop', 'phone' => '9820011223', 'metal_type' => 'GOLD']),
                Karigar::create(['name' => 'Sunil Verma Artisan', 'phone' => '9833022334', 'metal_type' => 'GOLD']),
                Karigar::create(['name' => 'Gopal Jewellers Bench', 'phone' => '9892033445', 'metal_type' => 'GOLD']),
            ]);
        }

        $dueWeights = [18.450, 24.800, 12.250];
        foreach ($karigars->take(3) as $idx => $k) {
            $wt = $dueWeights[$idx] ?? 10.0;
            MetalTransaction::create([
                'party_type' => get_class($k),
                'party_id' => $k->id,
                'metal_type' => 'GOLD',
                'type' => 'ISSUE',
                'gross_weight' => $wt + 5.0,
                'fine_weight' => ($wt + 5.0) * 0.916,
                'date' => $today->copy()->subDays(3)->toDateString(),
                'description' => 'Gold bar issued for bangle making',
            ]);
            MetalTransaction::create([
                'party_type' => get_class($k),
                'party_id' => $k->id,
                'metal_type' => 'GOLD',
                'type' => 'RECEIPT',
                'gross_weight' => 5.0,
                'fine_weight' => 5.0 * 0.916,
                'date' => $today->copy()->subDay()->toDateString(),
                'description' => 'Partial sample returned',
            ]);
        }

        // 5. ORDERS & PRODUCTION LOAD
        $orderConfigs = [
            ['status' => 'NEW', 'name' => 'Royal Heritage Choker', 'due_days' => 5, 'cust' => $seededCustomers[0]],
            ['status' => 'NEW', 'name' => 'Men 22K Gold Kada', 'due_days' => 7, 'cust' => $seededCustomers[1]],
            ['status' => 'ASSIGNED', 'name' => 'Diamond Solitaire Ring 18K', 'due_days' => 3, 'cust' => $seededCustomers[2]],
            ['status' => 'ASSIGNED', 'name' => 'Antique Temple Necklace', 'due_days' => 4, 'cust' => $seededCustomers[3]],
            ['status' => 'READY', 'name' => 'Floral Jhumka Earrings', 'due_days' => 1, 'cust' => $seededCustomers[4]],
            ['status' => 'READY', 'name' => 'Silver Payal Pair (925)', 'due_days' => 2, 'cust' => $seededCustomers[0]],
            ['status' => 'ASSIGNED', 'name' => 'Bridal Mangalsutra Custom', 'due_days' => -2, 'cust' => $seededCustomers[1]], // Overdue
        ];

        foreach ($orderConfigs as $idx => $oc) {
            $orderNum = 'ORD-'.date('Ymd').'-'.str_pad($idx + 100, 4, '0', STR_PAD_LEFT);
            $order = Order::firstOrCreate(
                ['order_number' => $orderNum],
                [
                    'customer_id' => $oc['cust']->id,
                    'due_date' => $today->copy()->addDays($oc['due_days'])->toDateString(),
                    'notes' => 'Customer custom order - Priority delivery',
                ]
            );

            OrderItem::firstOrCreate(
                ['order_id' => $order->id, 'item_name' => $oc['name']],
                [
                    'target_weight' => 12.500,
                    'purity' => 91.60,
                    'status' => $oc['status'],
                    'assignee_type' => in_array($oc['status'], ['ASSIGNED', 'READY']) ? get_class($karigars->first()) : null,
                    'assignee_id' => in_array($oc['status'], ['ASSIGNED', 'READY']) ? $karigars->first()->id : null,
                    'finished_weight' => in_array($oc['status'], ['READY']) ? 12.480 : null,
                    'wastage' => 0.200,
                    'notes' => 'Hallmark certification required',
                ]
            );
        }

        // 6. INVOICES ACROSS LAST 7 DAYS
        $salesPattern = [
            ['daysAgo' => 6, 'amount' => 42500, 'item' => 'Gold Ring 22K (4.5g)'],
            ['daysAgo' => 5, 'amount' => 68200, 'item' => 'Lightweight Bangle Pair (9.2g)'],
            ['daysAgo' => 4, 'amount' => 115000, 'item' => 'Bridal Chain & Pendant (15.8g)'],
            ['daysAgo' => 3, 'amount' => 84000, 'item' => 'Antique Jhumkas 916 (11.5g)'],
            ['daysAgo' => 2, 'amount' => 54500, 'item' => 'Silver Dinner Set & Coin (500g)'],
            ['daysAgo' => 1, 'amount' => 92000, 'item' => 'Men Gold Bracelet 22K (12.4g)'],
            ['daysAgo' => 0, 'amount' => 78500, 'item' => 'Diamond Studded Gold Pendant (10.6g)'],
        ];

        foreach ($salesPattern as $sIdx => $sp) {
            $invDate = $today->copy()->subDays($sp['daysAgo']);
            $cust = $seededCustomers[$sIdx % count($seededCustomers)];
            $invNum = 'INV-'.$invDate->format('Ymd').'-'.str_pad($sIdx + 100, 4, '0', STR_PAD_LEFT);

            $inv = Invoice::firstOrCreate(
                ['invoice_number' => $invNum],
                [
                    'customer_id' => $cust->id,
                    'user_id' => $user->id,
                    'date' => $invDate->toDateString(),
                    'gold_rate_applied' => 7250,
                    'tax_amount' => $sp['amount'] * 0.03,
                    'total_amount' => $sp['amount'],
                    'created_at' => $invDate,
                ]
            );

            InvoiceItem::firstOrCreate(
                ['invoice_id' => $inv->id, 'description' => $sp['item']],
                [
                    'purity' => '22K',
                    'weight' => round($sp['amount'] / 7400, 3),
                    'rate' => 7250,
                    'making_charges' => 500,
                    'final_price' => $sp['amount'],
                ]
            );

            Transaction::firstOrCreate(
                [
                    'transactable_type' => Customer::class,
                    'transactable_id' => $cust->id,
                    'description' => 'Invoice #'.$inv->invoice_number,
                ],
                [
                    'user_id' => $user->id,
                    'type' => 'SALE',
                    'amount' => $sp['amount'],
                    'date' => $invDate->toDateString(),
                    'payment_method' => 'CASH',
                    'created_at' => $invDate,
                ]
            );

            Transaction::firstOrCreate(
                [
                    'transactable_type' => Customer::class,
                    'transactable_id' => $cust->id,
                    'description' => 'Payment for Invoice #'.$inv->invoice_number,
                ],
                [
                    'user_id' => $user->id,
                    'type' => 'PAYMENT',
                    'amount' => $sp['amount'],
                    'date' => $invDate->toDateString(),
                    'payment_method' => $sIdx % 2 === 0 ? 'UPI' : 'CASH',
                    'created_at' => $invDate,
                ]
            );
        }

        // 7. EXPENSES
        Expense::create([
            'title' => 'Workshop Tool Sharpening & Polishing',
            'category' => 'Repair',
            'amount' => 1200,
            'payment_method' => 'CASH',
            'user_id' => $user->id,
            'created_at' => $today->copy()->subHours(4),
        ]);
        Expense::create([
            'title' => 'Packaging Velvet Boxes Batch',
            'category' => 'Utility',
            'amount' => 4500,
            'payment_method' => 'UPI',
            'user_id' => $user->id,
            'created_at' => $today->copy()->subHours(6),
        ]);
        Expense::create([
            'title' => 'Staff Tea & Refreshments',
            'category' => 'Food',
            'amount' => 450,
            'payment_method' => 'CASH',
            'user_id' => $user->id,
            'created_at' => $today->copy()->subHours(1),
        ]);
    }
}
