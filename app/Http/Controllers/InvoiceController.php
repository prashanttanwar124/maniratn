<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SilverProduct;
use App\Models\DailyRate;
use App\Enums\VaultType;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use App\Services\VaultService;
use App\Services\LedgerImpactService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction; // <--- The Ledger Model
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{


    public function index()
    {
        $invoices = Invoice::with('customer')
            ->with(['items', 'transactions', 'cancelledBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('invoices/Index', [
            'invoices' => $invoices->map(function (Invoice $invoice) {
                $paidAmount = (float) $invoice->transactions
                    ->where('type', 'PAYMENT')
                    ->sum('amount');

                $pendingAmount = $invoice->status === 'CANCELLED'
                    ? 0
                    : max((float) $invoice->total_amount - $paidAmount, 0);

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer' => [
                        'id' => $invoice->customer?->id,
                        'name' => $invoice->customer?->name,
                    ],
                    'date' => $invoice->date,
                    'status' => $invoice->status,
                    'total_amount' => (float) $invoice->total_amount,
                    'discount_type' => $invoice->discount_type,
                    'discount_value' => (float) ($invoice->discount_value ?? 0),
                    'discount_amount' => (float) ($invoice->discount_amount ?? 0),
                    'tax_amount' => (float) ($invoice->tax_amount ?? 0),
                    'paid_amount' => $paidAmount,
                    'pending_amount' => $pendingAmount,
                    'void_amount' => $invoice->status === 'CANCELLED' ? $paidAmount : 0,
                    'item_count' => $invoice->items->count(),
                    'cancellation_mode' => $invoice->cancellation_mode,
                    'cancellation_reason' => $invoice->cancellation_reason,
                    'cancelled_at' => optional($invoice->cancelled_at)?->toDateTimeString(),
                    'cancelled_by' => $invoice->cancelledBy?->name,
                ];
            })->values(),
        ]);
    }

    public function create(Request $request)
    {
        $prefilledItems = [];
        $customer = null;
        $lockCustomer = false;
        $todayRate = DailyRate::query()
            ->whereDate('date', now()->toDateString())
            ->first();

        // If we are coming from the Kanban Board with an Order ID
        if ($request->has('order_id')) {

            // 1. Find ALL sibling items for this Order that are READY
            $prefilledItems = \App\Models\OrderItem::where('order_id', $request->order_id)
                ->where('status', 'READY')
                // Optional: Ensure item hasn't been billed yet
                // ->whereDoesntHave('invoiceItems') 
                ->with('order') // Get purity/weight info
                ->get();

            // 2. Get the Customer details from the first item
            if ($prefilledItems->isNotEmpty()) {
                $customer = $prefilledItems->first()->order->customer;
                $lockCustomer = true;
            }
        }

        if (!$customer && $request->filled('customer_id')) {
            $customer = Customer::find($request->integer('customer_id'));
        }

        return Inertia::render('invoices/Create', [
            'customers'      => \App\Models\Customer::all(),
            'defaultGoldRate' => (float) ($todayRate?->gold_sell ?? 0),
            'defaultSilverRate' => (float) ($todayRate?->silver_sell ?? 0),
            // Pass the ready items to the frontend
            'prefilledItems' => $prefilledItems,
            'prefilledCustomer' => $customer,
            'lockCustomer' => $lockCustomer,
        ]);
    }

    public function print(Invoice $invoice)
    {
        $invoice->load([
            'customer',
            'items.product',
            'items.silverProduct',
            'items.orderItem',
            'transactions',
            'user',
        ]);

        $paidAmount = (float) $invoice->transactions
            ->where('type', 'PAYMENT')
            ->sum('amount');

        $balanceDue = $invoice->status === 'CANCELLED'
            ? 0
            : max((float) $invoice->total_amount - $paidAmount, 0);

        return view('print.invoice', [
            'invoice' => $invoice,
            'paidAmount' => $paidAmount,
            'balanceDue' => $balanceDue,
        ]);
    }



    public function store(Request $request)
    {
        // 1. Validate: Accept a generic 'items' array containing mixed types
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'gold_rate'   => 'nullable|numeric|min:0',
            'date'        => 'required|date',
            'discount_type' => 'nullable|in:amount,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'silver_rate' => 'nullable|numeric|min:0',

            // MIXED ITEMS LIST (Can be Product OR OrderItem)
            'items'       => 'required|array',
            'items.*.type' => 'required|in:product,order_item,silver_product', // Identify the type
            'items.*.id'   => 'required|integer', // The ID of the Product or OrderItem
            'items.*.making_charges' => 'required|numeric|min:0', // We need this from frontend
            'items.*.quantity' => 'nullable|integer|min:1',

            'payment_cash' => 'nullable|numeric|min:0',
            'payment_card' => 'nullable|numeric|min:0',
            'card_note'    => 'nullable|string|max:100',
        ]);

        return DB::transaction(function () use ($validated) {

            $totalBillAmount = 0;
            $totalVaultGoldSoldWeight = 0;
            $totalVaultSilverSoldWeight = 0;
            $items = collect($validated['items']);

            $hasGoldPricedItems = $items->contains(function ($item) {
                if (($item['type'] ?? null) === 'product') {
                    return true;
                }

                if (($item['type'] ?? null) !== 'order_item') {
                    return false;
                }

                return strtoupper((string) optional(OrderItem::find($item['id']))->metal_type) !== 'SILVER';
            });

            $hasSilverPricedItems = $items->contains(function ($item) {
                if (($item['type'] ?? null) === 'silver_product') {
                    return true;
                }

                if (($item['type'] ?? null) !== 'order_item') {
                    return false;
                }

                return strtoupper((string) optional(OrderItem::find($item['id']))->metal_type) === 'SILVER';
            });

            if ($hasGoldPricedItems && empty($validated['gold_rate'])) {
                throw ValidationException::withMessages([
                    'gold_rate' => "Today's Gold Rate is required for gold invoice items.",
                ]);
            }

            if ($hasSilverPricedItems && empty($validated['silver_rate'])) {
                $hasWeightSilverItems = $items->contains(function ($item) {
                    if (($item['type'] ?? null) === 'order_item') {
                        return strtoupper((string) optional(OrderItem::find($item['id']))->metal_type) === 'SILVER';
                    }

                    if (($item['type'] ?? null) !== 'silver_product') {
                        return false;
                    }

                    $silverProduct = SilverProduct::find($item['id']);

                    return $silverProduct?->pricing_mode === 'WEIGHT';
                });

                if ($hasWeightSilverItems) {
                    throw ValidationException::withMessages([
                        'silver_rate' => "Today's Silver Rate is required for weight-based silver invoice items.",
                    ]);
                }
            }

            // 2. Create Invoice Header
            $invoice = Invoice::create([
                'invoice_number' => 'TMP-' . Str::uuid(),
                'customer_id'    => $validated['customer_id'],
                'gold_rate_applied' => (float) ($validated['gold_rate'] ?? 0),
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_value' => (float) ($validated['discount_value'] ?? 0),
                'discount_amount' => 0,
                'date'           => $validated['date'],
                'total_amount'   => 0,
                'user_id'        => Auth::id(),
            ]);

            $invoice->update([
                'invoice_number' => sprintf('INV-%s-%06d', now()->format('Ymd'), $invoice->id),
            ]);

            // 3. LOOP THROUGH MIXED ITEMS
            foreach ($validated['items'] as $row) {

                $weight = 0;
                $purity = 0;
                $itemName = '';

                // --- CASE A: IT IS A STOCK PRODUCT (Ring from Showcase) ---
                if ($row['type'] === 'product') {
                    $product = Product::findOrFail($row['id']);

                    // Validation: Check if already sold
                    if ($product->is_sold) {
                        abort(400, "Product {$product->name} is already sold!");
                    }

                    $weight = $product->net_weight;
                    $purity = $product->purity; // e.g., 91.6
                    $itemName = $product->name;

                    // Mark Stock as SOLD
                    $product->update(['is_sold' => true]);

                    // Save to InvoiceItems
                    InvoiceItem::create([
                        'invoice_id'  => $invoice->id,
                        'product_id'  => $product->id, // Link Product
                        'description' => $itemName,
                        'quantity'    => 1,
                        'weight'      => $weight,
                        'purity'      => $purity->name,
                        'rate'        => $validated['gold_rate'],
                        'making_charges' => $row['making_charges'],
                        'final_price' => ($weight * $validated['gold_rate']) + $row['making_charges']
                    ]);
                }

                elseif ($row['type'] === 'silver_product') {
                    $silverProduct = SilverProduct::findOrFail($row['id']);

                    if ($silverProduct->is_sold) {
                        abort(400, "Silver product {$silverProduct->name} is already sold!");
                    }

                    $saleQuantity = max(1, (int) ($row['quantity'] ?? 1));

                    if ($silverProduct->pricing_mode === 'PIECE') {
                        if ($saleQuantity > (int) $silverProduct->quantity) {
                            throw ValidationException::withMessages([
                                'items' => "Requested quantity for {$silverProduct->name} exceeds available stock.",
                            ]);
                        }

                        $weight = (float) ($silverProduct->net_weight ?? 0) * $saleQuantity;
                        $itemName = $silverProduct->name;
                        $itemTotal = ((float) ($silverProduct->piece_price ?? 0) * $saleQuantity) + (float) $row['making_charges'];

                        $remainingQuantity = (int) $silverProduct->quantity - $saleQuantity;
                        $silverProduct->update([
                            'quantity' => $remainingQuantity,
                            'is_sold' => $remainingQuantity <= 0,
                        ]);

                        InvoiceItem::create([
                            'invoice_id' => $invoice->id,
                            'silver_product_id' => $silverProduct->id,
                            'description' => $itemName,
                            'quantity' => $saleQuantity,
                            'weight' => $weight,
                            'purity' => 'Silver',
                            'rate' => (float) ($silverProduct->piece_price ?? 0),
                            'making_charges' => $row['making_charges'],
                            'final_price' => $itemTotal,
                        ]);

                        $totalBillAmount += $itemTotal;
                        continue;
                    }

                    $weight = (float) $silverProduct->net_weight;
                    $itemName = $silverProduct->name;
                    $silverRate = (float) ($validated['silver_rate'] ?? 0);
                    $itemTotal = ($weight * $silverRate) + (float) $row['making_charges'];

                    $silverProduct->update([
                        'quantity' => 0,
                        'is_sold' => true,
                    ]);

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'silver_product_id' => $silverProduct->id,
                        'description' => $itemName,
                        'quantity' => 1,
                        'weight' => $weight,
                        'purity' => 'Silver',
                        'rate' => $silverRate,
                        'making_charges' => $row['making_charges'],
                        'final_price' => $itemTotal,
                    ]);

                    $totalBillAmount += $itemTotal;
                    continue;
                }

                // --- CASE B: IT IS A CUSTOM ORDER (Made by Karigar) ---
                elseif ($row['type'] === 'order_item') {
                    $orderItem = OrderItem::findOrFail($row['id']);

                    if ($orderItem->status !== 'READY') {
                        throw ValidationException::withMessages([
                            'items' => "Order item {$orderItem->item_name} is not ready for billing.",
                        ]);
                    }

                    if (! $orderItem->finished_weight || (float) $orderItem->finished_weight <= 0) {
                        throw ValidationException::withMessages([
                            'items' => "Order item {$orderItem->item_name} does not have a finished weight yet.",
                        ]);
                    }

                    $weight = $orderItem->finished_weight; // Use actual finished weight
                    $purity = $orderItem->purity;
                    $itemName = $orderItem->item_name;
                    $orderItemMetalType = strtoupper((string) ($orderItem->metal_type ?? 'GOLD'));
                    $rateApplied = $orderItemMetalType === 'SILVER'
                        ? (float) ($validated['silver_rate'] ?? 0)
                        : (float) ($validated['gold_rate'] ?? 0);

                    // Mark Item as DELIVERED
                    $orderItem->update([
                        'status' => 'DELIVERED',
                        // Optional: You can save invoice_id here if you added that column to order_items
                        // 'invoice_id' => $invoice->id 
                    ]);

                    // Save to InvoiceItems
                    InvoiceItem::create([
                        'invoice_id'    => $invoice->id,
                        'order_item_id' => $orderItem->id, // Link Order Item
                        'description'   => $itemName . " (Order #" . $orderItem->order->order_number . ")",
                        'quantity'      => 1,
                        'weight'        => $weight,
                        'purity'        => $purity,
                        'rate'          => $rateApplied,
                        'making_charges' => $row['making_charges'],
                        'final_price'   => ($weight * $rateApplied) + $row['making_charges']
                    ]);

                    if ($orderItemMetalType === 'SILVER') {
                        $totalVaultSilverSoldWeight += (float) $weight;
                    } else {
                        $totalVaultGoldSoldWeight += (float) $weight;
                    }
                }

                // Math: (Weight * Rate) + Making Charge
                $rateForItem = 0;

                if ($row['type'] === 'product') {
                    $rateForItem = (float) ($validated['gold_rate'] ?? 0);
                } elseif ($row['type'] === 'order_item') {
                    $rateForItem = strtoupper((string) ($orderItem->metal_type ?? 'GOLD')) === 'SILVER'
                        ? (float) ($validated['silver_rate'] ?? 0)
                        : (float) ($validated['gold_rate'] ?? 0);
                }

                $itemTotal = ($weight * $rateForItem) + $row['making_charges'];
                $totalBillAmount += $itemTotal;
            }

            // 4. Calculate Discount, Tax & Final Total
            $discountType = $validated['discount_type'] ?? null;
            $discountValue = round((float) ($validated['discount_value'] ?? 0), 2);
            $discountAmount = 0;

            if ($discountType === 'percentage') {
                if ($discountValue > 100) {
                    throw ValidationException::withMessages([
                        'discount_value' => 'Percentage discount cannot be greater than 100.',
                    ]);
                }

                $discountAmount = round($totalBillAmount * ($discountValue / 100), 2);
            } elseif ($discountType === 'amount') {
                $discountAmount = $discountValue;
            }

            if ($discountAmount > $totalBillAmount) {
                throw ValidationException::withMessages([
                    'discount_value' => 'Discount cannot be greater than the item subtotal.',
                ]);
            }

            $taxableAmount = round($totalBillAmount - $discountAmount, 2);
            $gst = round($taxableAmount * 0.03, 2); // 3% GST after discount
            $finalTotal = round($taxableAmount + $gst, 2);
            $totalPaid = (float) ($validated['payment_cash'] ?? 0) + (float) ($validated['payment_card'] ?? 0);

            if ($totalPaid > $finalTotal) {
                throw ValidationException::withMessages([
                    'payment_cash' => 'Received amount cannot be greater than the invoice total.',
                ]);
            }

            $invoice->update([
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'total_amount' => $finalTotal,
                'tax_amount'   => $gst
            ]);

            if ($totalVaultGoldSoldWeight > 0) {
                VaultService::debit(VaultType::GOLD, $totalVaultGoldSoldWeight, [
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                    'reference' => $invoice->invoice_number,
                    'user_id' => Auth::id(),
                    'note' => "Gold sold in {$invoice->invoice_number}",
                ]);
            }

            if ($totalVaultSilverSoldWeight > 0) {
                VaultService::debit(VaultType::SILVER, $totalVaultSilverSoldWeight, [
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                    'reference' => $invoice->invoice_number,
                    'user_id' => Auth::id(),
                    'note' => "Silver sold in {$invoice->invoice_number}",
                ]);
            }

            // 5. ACCOUNTING (Ledger Entries)

            // A. DEBIT THE CUSTOMER (Sale Entry)
            Transaction::create([
                'transactable_type' => Customer::class,
                'transactable_id'   => $validated['customer_id'],
                'invoice_id'        => $invoice->id,
                'type'              => 'SALE',
                'amount'            => $finalTotal,
                'description'       => "Bill #" . $invoice->invoice_number,
                'date'              => $validated['date'],
                'user_id'           => Auth::id(),
                'entry_type_code'   => 'INVOICE_SALE',
            ]);

            // B. CREDIT THE CUSTOMER (Cash Payment)
            if (!empty($validated['payment_cash']) && $validated['payment_cash'] > 0) {
                $transaction = Transaction::create([
                    'transactable_type' => Customer::class,
                    'transactable_id'   => $validated['customer_id'],
                    'invoice_id'        => $invoice->id,
                    'type'              => 'PAYMENT',
                    'amount'            => $validated['payment_cash'],
                    'description'       => "Cash Payment",
                    'date'              => $validated['date'],
                    'user_id'           => Auth::id(),
                    'payment_method' => 'CASH',
                    'entry_type_code' => 'INVOICE_PAYMENT',
                ]);
                LedgerImpactService::applyCashTransaction($transaction);
            }

            // C. CREDIT THE CUSTOMER (Card Payment)
            if (!empty($validated['payment_card']) && $validated['payment_card'] > 0) {
                $transaction = Transaction::create([
                    'transactable_type' => Customer::class,
                    'transactable_id'   => $validated['customer_id'],
                    'invoice_id'        => $invoice->id,
                    'type'              => 'PAYMENT',
                    'amount'            => $validated['payment_card'],
                    'description'       => "Card Payment " . ($validated['card_note'] ?? ''),
                    'date'              => $validated['date'],
                    'user_id'           => Auth::id(),
                    'payment_method' => 'CARD',
                    'entry_type_code' => 'INVOICE_PAYMENT',

                ]);
                LedgerImpactService::applyCashTransaction($transaction);
            }

            return redirect()
                ->route('invoices.index')
                ->with('success', "Invoice {$invoice->invoice_number} generated successfully.");
        });
    }


    public function cancel(Request $request, $id)
    {
        $validated = $request->validate([
            'mode' => 'required|in:keep_advance,refund',
            'reason' => 'required|string|max:500',
        ]);

        $invoice = Invoice::with(['items.product', 'items.silverProduct', 'items.orderItem', 'transactions'])->findOrFail($id);

        if ($invoice->status === 'CANCELLED') {
            return back()->with('error', 'This invoice is already cancelled.');
        }

        try {
            DB::transaction(function () use ($invoice, $validated) {
            $invoice->update([
                'status' => 'CANCELLED',
                'cancellation_mode' => $validated['mode'],
                'cancellation_reason' => $validated['reason'],
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
            ]);

            $restoredGoldWeight = (float) $invoice->items
                ->filter(fn ($item) => $item->order_item_id !== null && strtoupper((string) ($item->orderItem?->metal_type ?? 'GOLD')) !== 'SILVER')
                ->sum('weight');

            if ($restoredGoldWeight > 0) {
                VaultService::credit(VaultType::GOLD, $restoredGoldWeight, [
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                    'reference' => $invoice->invoice_number,
                    'user_id' => Auth::id(),
                    'note' => "Gold restored after voiding {$invoice->invoice_number}",
                ]);
            }

            $restoredSilverWeight = (float) $invoice->items
                ->filter(fn ($item) => $item->order_item_id !== null && strtoupper((string) ($item->orderItem?->metal_type ?? 'GOLD')) === 'SILVER')
                ->sum('weight');

            if ($restoredSilverWeight > 0) {
                VaultService::credit(VaultType::SILVER, $restoredSilverWeight, [
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                    'reference' => $invoice->invoice_number,
                    'user_id' => Auth::id(),
                    'note' => "Silver restored after voiding {$invoice->invoice_number}",
                ]);
            }

            foreach ($invoice->items as $item) {
                if ($item->product) {
                    $item->product->update(['is_sold' => false]);
                }

                if ($item->silverProduct) {
                    if ($item->silverProduct->pricing_mode === 'PIECE') {
                        $item->silverProduct->update([
                            'quantity' => (int) $item->silverProduct->quantity + (int) ($item->quantity ?? 1),
                            'is_sold' => false,
                        ]);
                    } else {
                        $item->silverProduct->update([
                            'quantity' => max(1, (int) ($item->quantity ?? 1)),
                            'is_sold' => false,
                        ]);
                    }
                }

                if ($item->orderItem) {
                    $item->orderItem->update(['status' => 'READY']);
                }
            }

            foreach ($invoice->transactions as $transaction) {
                if ($transaction->type === 'SALE') {
                    $transaction->update([
                        'type' => 'VOID',
                        'description' => "Voided sale for {$invoice->invoice_number}",
                        'entry_type_code' => 'VOID_INVOICE_SALE',
                    ]);
                    continue;
                }

                if ($transaction->type !== 'PAYMENT') {
                    continue;
                }

                if ($validated['mode'] === 'refund') {
                    LedgerImpactService::reverseCashTransaction($transaction);

                    $transaction->update([
                        'type' => 'VOID',
                        'description' => "Refunded payment for {$invoice->invoice_number}",
                        'entry_type_code' => 'INVOICE_REFUND',
                    ]);
                } else {
                    $transaction->update([
                        'description' => trim(($transaction->description ?: 'Payment') . " | Kept as customer advance after void {$invoice->invoice_number}"),
                    ]);
                }
            }
            });
        } catch (\Throwable $e) {
            return back()->withErrors([
                'reason' => $e->getMessage(),
            ]);
        }

        $message = $validated['mode'] === 'refund'
            ? 'Invoice voided, stock restored, and paid amount refunded.'
            : 'Invoice voided, stock restored, and paid amount kept as customer advance.';

        return back()->with('success', $message);
    }
}
