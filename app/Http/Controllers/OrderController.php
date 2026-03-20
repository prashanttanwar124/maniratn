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
            'items.*.metal_type'  => ['required', Rule::in(['GOLD', 'SILVER'])],
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
                    'metal_type'    => $item['metal_type'],
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
            'metal_type' => ['required', Rule::in(['GOLD', 'SILVER'])],
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
                $metalType = $orderItem->metal_type ?: 'GOLD';
                $metalTransaction = MetalTransaction::create([
                    'party_type'   => $modelClass,
                    'party_id'     => $validated['id'],
                    'type'         => 'ISSUE',
                    'metal_type'   => $metalType,
                    'gross_weight' => $issueGold,
                    'fine_weight'  => $this->makeFineWeight($issueGold, (float) $orderItem->purity),
                    'date'         => now(),
                    'description'  => "Issued {$this->metalLabel($metalType)} for Order {$orderItem->order->order_number} - {$orderItem->item_name}",
                    'entry_type_code' => $this->orderIssueEntryCode($metalType),
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
            $metalType = $orderItem->metal_type ?: 'GOLD';

            $desc = "Ref: {$orderItem->order->order_number} ({$orderItem->item_name})";

            $metalTransaction = $party->metalTransactions()->create([
                'type'         => 'ISSUE',
                'metal_type'   => $metalType,
                'gross_weight' => $validated['metal_weight'],
                'fine_weight'  => $this->makeFineWeight((float) $validated['metal_weight'], (float) $orderItem->purity),
                'date'         => Carbon::parse($validated['date'])->format('Y-m-d'),
                'description'  => "{$this->metalLabel($metalType)}: " . $desc . (!empty($validated['description']) ? " - " . trim((string) $validated['description']) : ""),
                'entry_type_code' => $this->orderIssueEntryCode($metalType),
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
            $metalType = $orderItem->metal_type ?: 'GOLD';
            $metalLabel = $this->metalLabel($metalType);
            $receivedTotal = round((float) $validated['received_weight'] + (float) ($validated['wastage'] ?? 0), 3);
            $totalIssuedGold = $this->totalIssuedGoldForItem($orderItem);
            $requiredExtraGold = round(max($receivedTotal - $totalIssuedGold, 0), 3);
            $declaredExtraGold = round((float) ($validated['extra_gold_added'] ?? 0), 3);

            if ($requiredExtraGold > 0 && abs($declaredExtraGold - $requiredExtraGold) > 0.0001) {
                throw ValidationException::withMessages([
                    'extra_gold_added' => "Finished return exceeds issued {$metalLabel} by {$requiredExtraGold} g. Record that extra {$metalLabel} before receiving the item.",
                ]);
            }

            if ($requiredExtraGold <= 0 && $declaredExtraGold > 0) {
                throw ValidationException::withMessages([
                    'extra_gold_added' => "Extra {$metalLabel} is not required for this receive entry.",
                ]);
            }

            if ($requiredExtraGold > 0 && blank($validated['extra_gold_source'] ?? null)) {
                throw ValidationException::withMessages([
                    'extra_gold_source' => "Select where the extra {$metalLabel} came from before receiving this item.",
                ]);
            }

            if ($declaredExtraGold > 0) {
                if (blank(trim((string) ($validated['mismatch_note'] ?? '')))) {
                    throw ValidationException::withMessages([
                        'mismatch_note' => "Add a note explaining why extra {$metalLabel} was added during production.",
                    ]);
                }

                $source = $validated['extra_gold_source'] ?? null;

                if ($source === 'SUPPLIER' && empty($validated['extra_gold_supplier_id'])) {
                    throw ValidationException::withMessages([
                        'extra_gold_supplier_id' => "Select the supplier who provided the extra {$metalLabel}.",
                    ]);
                }

                if ($source === 'KARIGAR' && empty($validated['extra_gold_karigar_id'])) {
                    throw ValidationException::withMessages([
                        'extra_gold_karigar_id' => "Select the karigar who provided the extra {$metalLabel}.",
                    ]);
                }

                $note = trim((string) $validated['mismatch_note']);

                if ($source === 'CUSTOMER') {
                    $customerReceipt = MetalTransaction::create([
                        'party_type' => Customer::class,
                        'party_id' => $orderItem->order->customer_id,
                        'type' => 'RECEIPT',
                        'metal_type' => $metalType,
                        'gross_weight' => $declaredExtraGold,
                        'fine_weight' => $this->makeFineWeight($declaredExtraGold, (float) $orderItem->purity),
                        'date' => now(),
                        'description' => "Additional {$metalLabel} received for {$orderItem->item_name} ({$orderItem->order->order_number}) - {$note}",
                        'entry_type_code' => $this->receiveEntryCode($metalType),
                        'entry_source' => 'SYSTEM',
                    ]);
                    LedgerImpactService::applyMetalTransaction($customerReceipt);
                }

                if ($source === 'SUPPLIER') {
                    $supplierReceipt = MetalTransaction::create([
                        'party_type' => Supplier::class,
                        'party_id' => (int) $validated['extra_gold_supplier_id'],
                        'type' => 'RECEIPT',
                        'metal_type' => $metalType,
                        'gross_weight' => $declaredExtraGold,
                        'fine_weight' => $this->makeFineWeight($declaredExtraGold, (float) $orderItem->purity),
                        'date' => now(),
                        'description' => "Additional {$metalLabel} supplied for {$orderItem->item_name} ({$orderItem->order->order_number}) - {$note}",
                        'entry_type_code' => $this->receiveEntryCode($metalType),
                        'entry_source' => 'SYSTEM',
                    ]);
                    LedgerImpactService::applyMetalTransaction($supplierReceipt);
                }

                if ($source === 'KARIGAR') {
                    $karigarReceipt = MetalTransaction::create([
                        'party_type' => Karigar::class,
                        'party_id' => (int) $validated['extra_gold_karigar_id'],
                        'type' => 'RECEIPT',
                        'metal_type' => $metalType,
                        'gross_weight' => $declaredExtraGold,
                        'fine_weight' => $this->makeFineWeight($declaredExtraGold, (float) $orderItem->purity),
                        'date' => now(),
                        'description' => "Additional {$metalLabel} received from karigar for {$orderItem->item_name} ({$orderItem->order->order_number}) - {$note}",
                        'entry_type_code' => $this->receiveEntryCode($metalType),
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
                        'metal_type' => $metalType,
                        'gross_weight' => $declaredExtraGold,
                        'fine_weight' => $this->makeFineWeight($declaredExtraGold, (float) $orderItem->purity),
                        'date' => now(),
                        'description' => "Additional {$metalLabel} issue from {$sourceLabel} for {$orderItem->item_name} ({$orderItem->order->order_number}) - {$note}",
                        'entry_type_code' => $this->orderIssueEntryCode($metalType),
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
                    'metal_type'   => $metalType,
                    'gross_weight' => $receivedTotal,
                    'fine_weight'  => $this->makeFineWeight($receivedTotal, (float) $orderItem->purity),
                    'date'         => now(),
                    'description'  => "Finished {$metalLabel}: {$orderItem->item_name} ({$orderItem->order->order_number})" . (!empty($validated['mismatch_note']) ? " - " . trim((string) $validated['mismatch_note']) : ''),
                    'entry_type_code' => $this->orderReceiveEntryCode($metalType),
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

    private function metalLabel(string $metalType): string
    {
        return strtoupper($metalType) === 'SILVER' ? 'silver' : 'gold';
    }

    private function orderIssueEntryCode(string $metalType): string
    {
        return strtoupper($metalType) === 'SILVER' ? 'ORDER_ISSUE_SILVER' : 'ORDER_ISSUE_GOLD';
    }

    private function orderReceiveEntryCode(string $metalType): string
    {
        return strtoupper($metalType) === 'SILVER' ? 'ORDER_RECEIVE_SILVER' : 'ORDER_RECEIVE_GOLD';
    }

    private function receiveEntryCode(string $metalType): string
    {
        return strtoupper($metalType) === 'SILVER' ? 'RECEIVE_SILVER' : 'RECEIVE_GOLD';
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
                    'sort_date' => strtotime((string) $txn->date) ?: 0,
                    'sort_created_at' => optional($txn->created_at)->timestamp ?? 0,
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
                    'sort_date' => strtotime((string) $txn->date) ?: 0,
                    'sort_created_at' => optional($txn->created_at)->timestamp ?? 0,
                ]);
        }

        return $cashTransactions
            ->merge($metalTransactions)
            ->sort(function (array $left, array $right) {
                $dateCompare = ($left['sort_date'] ?? 0) <=> ($right['sort_date'] ?? 0);
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                $createdCompare = ($left['sort_created_at'] ?? 0) <=> ($right['sort_created_at'] ?? 0);
                if ($createdCompare !== 0) {
                    return $createdCompare;
                }

                return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
            })
            ->values();
    }

    private function makeFineWeight(float $grossWeight, float $purityPercent): float
    {
        return round(($grossWeight * $purityPercent) / 100, 3);
    }
}
