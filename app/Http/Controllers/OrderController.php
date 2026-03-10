<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem; // NEW MODEL
use App\Models\Karigar;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\MetalTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderController extends Controller
{
    /**
     * Display the Kanban Board
     * NOW TRACKS ITEMS, NOT WHOLE ORDERS
     */
    public function index()
    {
        // Eager load the Parent Order and the Grandparent Customer
        $items = OrderItem::with(['order.customer', 'assignee'])
            ->latest()
            ->get();

        $groupedItems = [
            'NEW'       => $items->where('status', 'NEW')->values(),
            'ASSIGNED'  => $items->where('status', 'ASSIGNED')->values(),
            'READY'     => $items->where('status', 'READY')->values(),
            // Only show last 20 delivered items to keep page light
            'DELIVERED' => $items->where('status', 'DELIVERED')->take(20)->values(),
        ];

        return Inertia::render('orders/Index', [
            'orders'    => $groupedItems, // Variable name kept as 'orders' to match frontend prop
            'karigars'  => Karigar::all(),
            'suppliers' => Supplier::all(),
            'customers' => Customer::all(),
        ]);
    }

    /**
     * Create a New Order (Parent + Multiple Items)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'         => 'required|exists:customers,id',
            'due_date'            => 'required|date',
            'notes'               => 'nullable|string',
            // VALIDATE ARRAY OF ITEMS
            'items'               => 'required|array|min:1',
            'items.*.item_name'   => 'required|string',
            'items.*.target_weight' => 'required|numeric',
            'items.*.purity'      => 'required|numeric', // e.g., 91.60 or 75.00
            'items.*.notes'       => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated) {
            // 1. Create Parent Order
            $nextId = Order::max('id') + 1;
            $orderNumber = 'ORD-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_id'  => $validated['customer_id'],
                'due_date'     => Carbon::parse($validated['due_date'])->format('Y-m-d'),
                'notes'        => $validated['notes'] ?? null,
                // Status is removed from Parent, it lives on Children now
            ]);

            // 2. Create Child Items
            foreach ($validated['items'] as $item) {
                $order->items()->create([
                    'item_name'     => $item['item_name'],
                    'target_weight' => $item['target_weight'],
                    'purity'        => $item['purity'],
                    'notes'         => $item['notes'] ?? null,
                    'status'        => 'NEW',
                ]);
            }
        });

        return back()->with('success', 'Order Booked with ' . count($validated['items']) . ' items!');
    }

    /**
     * ACTION: Assign specific ITEM to Karigar
     * Note: We use OrderItem model binding now
     */
    public function assign(Request $request, OrderItem $orderItem)
    {
        $validated = $request->validate([
            'type'       => 'required|in:Karigar,Supplier',
            'id'         => 'required|integer',
            'issue_gold' => 'nullable|numeric|min:0',
        ]);

        $modelClass = $validated['type'] === 'Karigar' ? Karigar::class : Supplier::class;

        DB::transaction(function () use ($orderItem, $validated, $modelClass) {

            // 1. Assign the Item
            $orderItem->update([
                'status'        => 'ASSIGNED',
                'assignee_type' => $modelClass,
                'assignee_id'   => $validated['id'],
            ]);

            // 2. Issue Gold (Debit Stock, Credit Karigar)
            if (!empty($validated['issue_gold']) && $validated['issue_gold'] > 0) {
                MetalTransaction::create([
                    'party_type'   => $modelClass,
                    'party_id'     => $validated['id'],
                    'type'         => 'ISSUE',
                    'gross_weight' => $validated['issue_gold'],
                    // Logic: You might issue 24k (fine) or 22k (standard). 
                    // For simplicity assuming issue is same purity or pure. 
                    // Usually you issue Fine Gold or Standard Bars.
                    'fine_weight'  => $validated['issue_gold'],
                    'date'         => now(),
                    'description'  => "Issued for Order {$orderItem->order->order_number} - {$orderItem->item_name}",
                ]);
            }
        });

        return back()->with('success', 'Item assigned & Gold issued!');
    }

    /**
     * ACTION: Quick Transaction on a specific ITEM
     */
    public function addTransaction(Request $request, OrderItem $orderItem)
    {
        $validated = $request->validate([
            'cash_amount'  => 'nullable|numeric|min:0',
            'metal_weight' => 'nullable|numeric|min:0',
            'description'  => 'nullable|string',
            'date'         => 'required|date',
        ]);

        DB::transaction(function () use ($request, $orderItem) {

            // Logic: Is this with the Assignee (Karigar) or the Customer?
            // If the item is assigned, we deal with Karigar. If New/Ready, maybe Customer?
            // Usually, transactions on the Kanban board are for the Karigar/Assignee.

            $party = $orderItem->assignee;

            // Fallback: If no assignee (e.g., just taking advance from customer), use Customer
            if (!$party) {
                $party = $orderItem->order->customer;
            }

            $desc = "Ref: {$orderItem->order->order_number} ({$orderItem->item_name})";

            // 1. Handle Cash
            if ($request->cash_amount > 0) {
                $party->transactions()->create([
                    'type'   => 'PAYMENT',
                    'amount' => $request->cash_amount,
                    'date'   => Carbon::parse($request->date)->format('Y-m-d'),
                    'description' => $desc . ($request->description ? " - " . $request->description : ""),
                ]);
            }

            // 2. Handle Metal
            if ($request->metal_weight > 0) {
                // If it's a Karigar, usually we ISSUE metal. If Customer, we RECEIVE old gold.
                // Assuming Karigar context based on your previous code:
                $type = $orderItem->assignee ? 'ISSUE' : 'RECEIPT';

                $party->metalTransactions()->create([
                    'type'         => $type,
                    'gross_weight' => $request->metal_weight,
                    'fine_weight'  => $request->metal_weight, // Simplified
                    'date'         => $request->date,
                    'description'  => "Metal: " . $desc,
                ]);
            }
        });

        return back()->with('success', 'Transaction Recorded Successfully');
    }

    /**
     * ACTION: Receive Finished Item
     */
    public function complete(Request $request, OrderItem $orderItem)
    {
        $validated = $request->validate([
            'received_weight' => 'required|numeric|min:0.001',
            'wastage'         => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($orderItem, $validated) {

            // 1. Credit the Karigar (He returned the metal)
            if ($orderItem->assignee) {
                MetalTransaction::create([
                    'party_type'   => $orderItem->assignee_type,
                    'party_id'     => $orderItem->assignee_id,
                    'type'         => 'RECEIPT',
                    'gross_weight' => $validated['received_weight'] + ($validated['wastage'] ?? 0),
                    'fine_weight'  => $validated['received_weight'] + ($validated['wastage'] ?? 0),
                    'date'         => now(),
                    'description'  => "Finished: {$orderItem->item_name} ({$orderItem->order->order_number})",
                ]);
            }

            // 2. Update Item Status
            $orderItem->update([
                'status' => 'READY',
                'finished_weight' => $validated['received_weight'],
                'wastage' => $validated['wastage'] ?? 0,
            ]);
        });

        return back()->with('success', 'Item received & moved to Safe.');
    }
}
