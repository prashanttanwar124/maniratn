<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Product;
use App\Enums\VaultType;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use App\Services\VaultService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction; // <--- The Ledger Model

class InvoiceController extends Controller
{


    public function index()
    {
        // 1. Fetch Invoices
        // 'with('customer')' is CRITICAL. It gets the customer name for the table.
        // 'orderBy' ensures the newest bills appear at the top.
        $invoices = Invoice::with('customer')
            ->orderBy('created_at', 'desc') // or orderBy('date', 'desc')
            ->get();

        // 2. Send to the Vue Page
        return Inertia::render('invoices/Index', [
            'invoices' => $invoices
        ]);
    }

    public function create(Request $request)
    {
        $prefilledItems = [];
        $customer = null;

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
            }
        }

        return Inertia::render('invoices/Create', [
            'customers'      => \App\Models\Customer::all(),
            'products'       => \App\Models\Product::all(),
            // Pass the ready items to the frontend
            'prefilledItems' => $prefilledItems,
            'prefilledCustomer' => $customer,
        ]);
    }



    public function store(Request $request)
    {
        // 1. Validate: Accept a generic 'items' array containing mixed types
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'gold_rate'   => 'required|numeric',
            'date'        => 'required|date',

            // MIXED ITEMS LIST (Can be Product OR OrderItem)
            'items'       => 'required|array',
            'items.*.type' => 'required|in:product,order_item', // Identify the type
            'items.*.id'   => 'required|integer', // The ID of the Product or OrderItem
            'items.*.making_charges' => 'required|numeric|min:0', // We need this from frontend

            'payment_cash' => 'nullable|numeric|min:0',
            'payment_card' => 'nullable|numeric|min:0',
            'card_note'    => 'nullable|string|max:100',
        ]);

        return DB::transaction(function () use ($validated) {

            $totalBillAmount = 0;

            // 2. Create Invoice Header
            $invoice = Invoice::create([
                'invoice_number' => 'INV-' . time(),
                'customer_id'    => $validated['customer_id'],
                'gold_rate_applied' => $validated['gold_rate'],
                'date'           => $validated['date'],
                'total_amount'   => 0,
                'user_id'        => Auth::id(),
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
                        'weight'      => $weight,
                        'purity'      => $purity->name,
                        'making_charges' => $row['making_charges'],
                        'final_price' => ($weight * $validated['gold_rate']) + $row['making_charges']
                    ]);
                }

                // --- CASE B: IT IS A CUSTOM ORDER (Made by Karigar) ---
                elseif ($row['type'] === 'order_item') {
                    $orderItem = OrderItem::findOrFail($row['id']);

                    $weight = $orderItem->finished_weight; // Use actual finished weight
                    $purity = $orderItem->purity;
                    $itemName = $orderItem->item_name;

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
                        'weight'        => $weight,
                        'purity'        => $purity,
                        'making_charges' => $row['making_charges'],
                        'final_price'   => ($weight * $validated['gold_rate']) + $row['making_charges']
                    ]);
                }

                // Math: (Weight * Rate) + Making Charge
                $itemTotal = ($weight * $validated['gold_rate']) + $row['making_charges'];
                $totalBillAmount += $itemTotal;
            }

            // 4. Calculate Tax & Final Total
            $gst = $totalBillAmount * 0.03; // 3% GST
            $finalTotal = $totalBillAmount + $gst;

            $invoice->update([
                'total_amount' => $finalTotal,
                'tax_amount'   => $gst
            ]);

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
            ]);

            // B. CREDIT THE CUSTOMER (Cash Payment)
            if (!empty($validated['payment_cash']) && $validated['payment_cash'] > 0) {
                Transaction::create([
                    'transactable_type' => Customer::class,
                    'transactable_id'   => $validated['customer_id'],
                    'invoice_id'        => $invoice->id,
                    'type'              => 'PAYMENT',
                    'amount'            => $validated['payment_cash'],
                    'description'       => "Cash Payment",
                    'date'              => $validated['date'],
                    'user_id'           => Auth::id(),
                    'payment_method' => 'CASH'
                ]);


                $vaultType = VaultType::CASH;
                VaultService::credit($vaultType, $validated['payment_cash']);
            }

            // C. CREDIT THE CUSTOMER (Card Payment)
            if (!empty($validated['payment_card']) && $validated['payment_card'] > 0) {
                Transaction::create([
                    'transactable_type' => Customer::class,
                    'transactable_id'   => $validated['customer_id'],
                    'invoice_id'        => $invoice->id,
                    'type'              => 'PAYMENT',
                    'amount'            => $validated['payment_card'],
                    'description'       => "Card Payment " . ($validated['card_note'] ?? ''),
                    'date'              => $validated['date'],
                    'user_id'           => Auth::id(),
                    'payment_method' => 'CARD'

                ]);
                $vaultType = VaultType::BANK;
                VaultService::credit($vaultType, $validated['payment_cash']);
            }

            return $invoice;
        });
    }


    public function cancel($id)
    {
        $invoice = Invoice::with(['items.product', 'transactions'])->findOrFail($id);

        if ($invoice->status === 'CANCELLED') {
            return back()->with('error', 'This invoice is already cancelled.');
        }

        DB::transaction(function () use ($invoice) {
            // 1. Change Status
            $invoice->update(['status' => 'CANCELLED']);

            // 2. Return Products to Stock
            foreach ($invoice->items as $item) {
                if ($item->product) {
                    $item->product->update(['is_sold' => false]);
                }
            }

            // 3. Process Vault Reversals based on Transaction history
            foreach ($invoice->transactions as $transaction) {
                if ($transaction->type === 'PAYMENT') {
                    // Determine which vault to debit based on the payment method
                    $vaultType = ($transaction->payment_method === 'CARD') ? VaultType::BANK : VaultType::CASH;

                    // Debit the vault to remove the money
                    VaultService::debit(
                        $vaultType,
                        $transaction->amount,
                        "Reversal: Cancelled Invoice #{$invoice->id}"
                    );

                    // 4. Mark transaction as VOID so it's excluded from current totals
                    $transaction->update(['type' => 'VOID']);
                }
            }
        });

        return back()->with('success', 'Invoice cancelled. Inventory restored and vault balances adjusted.');
    }
}
