<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem; // NEW MODEL
use App\Models\Karigar;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\MetalTransaction;
use App\Models\Transaction;
use App\Services\LedgerImpactService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            ->get()
            ->map(fn (OrderItem $item) => $this->transformOrderItem($item));

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
            'notes'               => 'nullable|string|max:500',
            // VALIDATE ARRAY OF ITEMS
            'items'               => 'required|array|min:1',
            'items.*.item_name'   => 'required|string|max:255',
            'items.*.target_weight' => 'required|numeric|min:0.001',
            'items.*.purity'      => 'required|numeric|min:0.01|max:100',
            'items.*.notes'       => 'nullable|string|max:500',
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

    public function updateItem(Request $request, OrderItem $orderItem)
    {
        if ($orderItem->status !== 'NEW') {
            return back()->withErrors([
                'item' => 'Only new items can be edited before production starts.',
            ]);
        }

        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'target_weight' => 'required|numeric|min:0.001',
            'purity' => 'required|numeric|min:0.01|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $orderItem->update($validated);

        return back()->with('success', 'Order item updated successfully.');
    }

    /**
     * ACTION: Assign specific ITEM to Karigar
     * Note: We use OrderItem model binding now
     */
    public function assign(Request $request, OrderItem $orderItem)
    {
        if ($orderItem->status !== 'NEW') {
            return back()->withErrors([
                'assign' => 'Only new items can be assigned to production.',
            ]);
        }

        $validated = $request->validate([
            'type'       => 'required|in:Karigar,Supplier',
            'id'         => 'required|integer',
            'issue_gold' => 'nullable|numeric|min:0',
        ]);

        $existsTable = $validated['type'] === 'Karigar' ? 'karigars' : 'suppliers';

        validator(
            ['id' => $validated['id']],
            ['id' => ['required', 'integer', Rule::exists($existsTable, 'id')]]
        )->validate();

        $modelClass = $validated['type'] === 'Karigar' ? Karigar::class : Supplier::class;
        $issueGold = (float) ($validated['issue_gold'] ?? 0);

        try {
            DB::transaction(function () use ($orderItem, $validated, $modelClass, $issueGold) {

            // 1. Assign the Item
            $orderItem->update([
                'status'        => 'ASSIGNED',
                'assignee_type' => $modelClass,
                'assignee_id'   => $validated['id'],
            ]);

            // 2. Issue Gold (Debit Stock, Credit Karigar)
            if ($issueGold > 0) {
                $metalTransaction = MetalTransaction::create([
                    'party_type'   => $modelClass,
                    'party_id'     => $validated['id'],
                    'type'         => 'ISSUE',
                    'gross_weight' => $issueGold,
                    // Logic: You might issue 24k (fine) or 22k (standard). 
                    // For simplicity assuming issue is same purity or pure. 
                    // Usually you issue Fine Gold or Standard Bars.
                    'fine_weight'  => $issueGold,
                    'date'         => now(),
                    'description'  => "Issued for Order {$orderItem->order->order_number} - {$orderItem->item_name}",
                    'entry_type_code' => 'ORDER_ISSUE_GOLD',
                ]);
                LedgerImpactService::applyMetalTransaction($metalTransaction);
            }
            });
        } catch (\Throwable $e) {
            return back()->withErrors([
                'assign' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Item assigned & Gold issued!');
    }

    /**
     * ACTION: Quick Transaction on a specific ITEM
     */
    public function addTransaction(Request $request, OrderItem $orderItem)
    {
        if ($orderItem->status !== 'ASSIGNED' || ! $orderItem->assignee_type || ! $orderItem->assignee_id) {
            return back()->withErrors([
                'transaction' => 'Transactions can only be added for assigned items in production.',
            ]);
        }

        $validated = $request->validate([
            'metal_weight' => 'required|numeric|min:0.001',
            'description'  => 'nullable|string|max:255',
            'date'         => 'required|date',
        ]);

        try {
            DB::transaction(function () use ($validated, $orderItem) {
            $party = $orderItem->assignee;

            $desc = "Ref: {$orderItem->order->order_number} ({$orderItem->item_name})";

            $metalTransaction = $party->metalTransactions()->create([
                'type'         => 'ISSUE',
                'gross_weight' => $validated['metal_weight'],
                'fine_weight'  => $validated['metal_weight'],
                'date'         => Carbon::parse($validated['date'])->format('Y-m-d'),
                'description'  => "Metal: " . $desc . (!empty($validated['description']) ? " - " . trim((string) $validated['description']) : ""),
                'entry_type_code' => 'ORDER_ISSUE_GOLD',
            ]);
            LedgerImpactService::applyMetalTransaction($metalTransaction);
            });
        } catch (\Throwable $e) {
            return back()->withErrors([
                'transaction' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Transaction Recorded Successfully');
    }

    /**
     * ACTION: Receive Finished Item
     */
    public function complete(Request $request, OrderItem $orderItem)
    {
        if ($orderItem->status !== 'ASSIGNED' || ! $orderItem->assignee_type || ! $orderItem->assignee_id) {
            return back()->withErrors([
                'complete' => 'Only assigned items can be received from production.',
            ]);
        }

        $validated = $request->validate([
            'received_weight' => 'required|numeric|min:0.001',
            'wastage'         => 'nullable|numeric|min:0',
            'extra_gold_added' => 'nullable|numeric|min:0',
            'extra_gold_source' => ['nullable', Rule::in(['SHOP', 'CUSTOMER', 'SUPPLIER', 'KARIGAR'])],
            'extra_gold_supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'extra_gold_karigar_id' => ['nullable', 'integer', 'exists:karigars,id'],
            'mismatch_note' => 'nullable|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($orderItem, $validated) {
            $receivedTotal = round((float) $validated['received_weight'] + (float) ($validated['wastage'] ?? 0), 3);
            $totalIssuedGold = $this->totalIssuedGoldForItem($orderItem);
            $requiredExtraGold = round(max($receivedTotal - $totalIssuedGold, 0), 3);
            $declaredExtraGold = round((float) ($validated['extra_gold_added'] ?? 0), 3);

            if ($requiredExtraGold > 0 && abs($declaredExtraGold - $requiredExtraGold) > 0.0001) {
                throw ValidationException::withMessages([
                    'extra_gold_added' => "Finished return exceeds issued gold by {$requiredExtraGold} g. Record that extra gold before receiving the item.",
                ]);
            }

            if ($requiredExtraGold <= 0 && $declaredExtraGold > 0) {
                throw ValidationException::withMessages([
                    'extra_gold_added' => 'Extra gold is not required for this receive entry.',
                ]);
            }

            if ($requiredExtraGold > 0 && blank($validated['extra_gold_source'] ?? null)) {
                throw ValidationException::withMessages([
                    'extra_gold_source' => 'Select where the extra gold came from before receiving this item.',
                ]);
            }

            if ($declaredExtraGold > 0) {
                if (blank(trim((string) ($validated['mismatch_note'] ?? '')))) {
                    throw ValidationException::withMessages([
                        'mismatch_note' => 'Add a note explaining why extra gold was added during production.',
                    ]);
                }

                $source = $validated['extra_gold_source'] ?? null;

                if ($source === 'SUPPLIER' && empty($validated['extra_gold_supplier_id'])) {
                    throw ValidationException::withMessages([
                        'extra_gold_supplier_id' => 'Select the supplier who provided the extra gold.',
                    ]);
                }

                if ($source === 'KARIGAR' && empty($validated['extra_gold_karigar_id'])) {
                    throw ValidationException::withMessages([
                        'extra_gold_karigar_id' => 'Select the karigar who provided the extra gold.',
                    ]);
                }

                $note = trim((string) $validated['mismatch_note']);

                if ($source === 'CUSTOMER') {
                    $customerReceipt = MetalTransaction::create([
                        'party_type' => Customer::class,
                        'party_id' => $orderItem->order->customer_id,
                        'type' => 'RECEIPT',
                        'gross_weight' => $declaredExtraGold,
                        'fine_weight' => $declaredExtraGold,
                        'date' => now(),
                        'description' => "Additional gold received for {$orderItem->item_name} ({$orderItem->order->order_number}) - {$note}",
                        'entry_type_code' => 'RECEIVE_GOLD',
                        'entry_source' => 'SYSTEM',
                    ]);
                    LedgerImpactService::applyMetalTransaction($customerReceipt);
                }

                if ($source === 'SUPPLIER') {
                    $supplierReceipt = MetalTransaction::create([
                        'party_type' => Supplier::class,
                        'party_id' => (int) $validated['extra_gold_supplier_id'],
                        'type' => 'RECEIPT',
                        'gross_weight' => $declaredExtraGold,
                        'fine_weight' => $declaredExtraGold,
                        'date' => now(),
                        'description' => "Additional gold supplied for {$orderItem->item_name} ({$orderItem->order->order_number}) - {$note}",
                        'entry_type_code' => 'RECEIVE_GOLD',
                        'entry_source' => 'SYSTEM',
                    ]);
                    LedgerImpactService::applyMetalTransaction($supplierReceipt);
                }

                if ($source === 'KARIGAR') {
                    $karigarReceipt = MetalTransaction::create([
                        'party_type' => Karigar::class,
                        'party_id' => (int) $validated['extra_gold_karigar_id'],
                        'type' => 'RECEIPT',
                        'gross_weight' => $declaredExtraGold,
                        'fine_weight' => $declaredExtraGold,
                        'date' => now(),
                        'description' => "Additional gold received from karigar for {$orderItem->item_name} ({$orderItem->order->order_number}) - {$note}",
                        'entry_type_code' => 'RECEIVE_GOLD',
                        'entry_source' => 'SYSTEM',
                    ]);
                    LedgerImpactService::applyMetalTransaction($karigarReceipt);
                }

                if ($orderItem->assignee) {
                    $sourceLabel = match ($source) {
                        'CUSTOMER' => 'customer',
                        'SUPPLIER' => 'supplier',
                        'KARIGAR' => 'karigar',
                        default => 'shop',
                    };

                    $extraIssue = MetalTransaction::create([
                        'party_type' => $orderItem->assignee_type,
                        'party_id' => $orderItem->assignee_id,
                        'type' => 'ISSUE',
                        'gross_weight' => $declaredExtraGold,
                        'fine_weight' => $declaredExtraGold,
                        'date' => now(),
                        'description' => "Additional issue from {$sourceLabel} for {$orderItem->item_name} ({$orderItem->order->order_number}) - {$note}",
                        'entry_type_code' => 'ORDER_ISSUE_GOLD',
                    ]);
                    LedgerImpactService::applyMetalTransaction($extraIssue);
                }
            }

            // 1. Credit the Karigar (He returned the metal)
            if ($orderItem->assignee) {
                $metalTransaction = MetalTransaction::create([
                    'party_type'   => $orderItem->assignee_type,
                    'party_id'     => $orderItem->assignee_id,
                    'type'         => 'RECEIPT',
                    'gross_weight' => $receivedTotal,
                    'fine_weight'  => $receivedTotal,
                    'date'         => now(),
                    'description'  => "Finished: {$orderItem->item_name} ({$orderItem->order->order_number})" . (!empty($validated['mismatch_note']) ? " - " . trim((string) $validated['mismatch_note']) : ''),
                    'entry_type_code' => 'ORDER_RECEIVE_GOLD',
                ]);
                LedgerImpactService::applyMetalTransaction($metalTransaction);
            }

            // 2. Update Item Status
            $orderItem->update([
                'status' => 'READY',
                'finished_weight' => $validated['received_weight'],
                'wastage' => $validated['wastage'] ?? 0,
            ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withErrors([
                'complete' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Item received & moved to Safe.');
    }

    private function totalIssuedGoldForItem(OrderItem $item): float
    {
        if (! $item->relationLoaded('order')) {
            $item->load('order');
        }

        $orderNumber = $item->order?->order_number;
        $itemName = $item->item_name;

        if (! $orderNumber || ! $itemName || ! $item->assignee_type || ! $item->assignee_id) {
            return 0.0;
        }

        return (float) MetalTransaction::query()
            ->where('party_type', $item->assignee_type)
            ->where('party_id', $item->assignee_id)
            ->where('type', 'ISSUE')
            ->where('description', 'like', '%' . $orderNumber . '%')
            ->where('description', 'like', '%' . $itemName . '%')
            ->sum('gross_weight');
    }

    private function transformOrderItem(OrderItem $item): array
    {
        $data = $item->toArray();

        $transactions = $this->buildItemTransactions($item);

        $data['transactions'] = $transactions;
        $data['issued_gold'] = $transactions
            ->where('type', 'ISSUE')
            ->sum('amount');

        return $data;
    }

    private function buildItemTransactions(OrderItem $item)
    {
        if (! $item->relationLoaded('order')) {
            $item->load('order');
        }

        $orderNumber = $item->order?->order_number;
        $itemName = $item->item_name;

        if (! $orderNumber || ! $itemName) {
            return collect();
        }

        $likeOrder = '%' . $orderNumber . '%';
        $likeItem = '%' . $itemName . '%';

        $cashTransactions = collect();
        $metalTransactions = collect();

        if ($item->assignee_type && $item->assignee_id) {
            $cashTransactions = Transaction::query()
                ->where('transactable_type', $item->assignee_type)
                ->where('transactable_id', $item->assignee_id)
                ->where('description', 'like', $likeOrder)
                ->where('description', 'like', $likeItem)
                ->get()
                ->toBase()
                ->map(fn (Transaction $txn) => [
                    'id' => 'cash-' . $txn->id,
                    'date' => $txn->date,
                    'category' => 'cash',
                    'type' => $txn->type,
                    'amount' => (float) $txn->amount,
                    'description' => $txn->description,
                    'sort_at' => optional($txn->created_at)->timestamp ?? strtotime($txn->date),
                ]);

            $metalTransactions = MetalTransaction::query()
                ->where('party_type', $item->assignee_type)
                ->where('party_id', $item->assignee_id)
                ->where('description', 'like', $likeOrder)
                ->where('description', 'like', $likeItem)
                ->get()
                ->toBase()
                ->map(fn (MetalTransaction $txn) => [
                    'id' => 'metal-' . $txn->id,
                    'date' => $txn->date,
                    'category' => 'metal',
                    'type' => $txn->type,
                    'amount' => (float) $txn->gross_weight,
                    'description' => $txn->description,
                    'sort_at' => optional($txn->created_at)->timestamp ?? strtotime($txn->date),
                ]);
        }

        return $cashTransactions
            ->merge($metalTransactions)
            ->sortBy('sort_at')
            ->values();
    }
}
