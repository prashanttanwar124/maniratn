<?php

namespace App\Services\Ai\Actions;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Services\Ai\Contracts\AiActionInterface;
use Carbon\Carbon;

class SalesSummaryAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $period = strtolower(trim($args['period'] ?? 'today'));

        if ($period === 'yesterday') {
            $startDate = Carbon::yesterday()->startOfDay();
            $endDate = Carbon::yesterday()->endOfDay();
            $periodLabel = 'Yesterday (' . Carbon::yesterday()->format('d M Y') . ')';
        } elseif ($period === 'this_week' || $period === 'week') {
            $startDate = Carbon::now()->startOfWeek();
            $endDate = Carbon::now()->endOfWeek();
            $periodLabel = 'This Week (' . $startDate->format('d M') . ' - ' . $endDate->format('d M Y') . ')';
        } elseif ($period === 'this_month' || $period === 'month') {
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
            $periodLabel = 'This Month (' . Carbon::now()->format('F Y') . ')';
        } else {
            // Default Today
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();
            $periodLabel = 'Today (' . Carbon::today()->format('d M Y') . ')';
        }

        $invoices = Invoice::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('status', '!=', 'CANCELLED')
            ->get();

        $totalSales = (float) $invoices->sum('total_amount');
        $totalBills = $invoices->count();

        // Calculate gold vs silver weight sold
        $invoiceIds = $invoices->pluck('id')->toArray();
        $items = InvoiceItem::whereIn('invoice_id', $invoiceIds)->get();

        $goldWeight = 0.0;
        $silverWeight = 0.0;

        foreach ($items as $item) {
            $purity = strtoupper((string) $item->purity);
            $weight = (float) $item->weight;
            if (str_contains($purity, 'SILVER') || $item->silver_product_id) {
                $silverWeight += $weight;
            } else {
                $goldWeight += $weight;
            }
        }

        // Payments from transactions
        $payments = \App\Models\Transaction::whereIn('invoice_id', $invoiceIds)
            ->where('type', 'PAYMENT')
            ->get();

        $totalCollected = (float) $payments->sum('amount');

        $cashTotal = 0.0;
        $upiTotal = 0.0;
        $cardTotal = 0.0;
        $bankTotal = 0.0;

        foreach ($payments as $pmt) {
            $mode = strtoupper((string) ($pmt->method ?? ($pmt->payment_method ?? 'CASH')));
            $amt = (float) $pmt->amount;
            if (str_contains($mode, 'UPI')) {
                $upiTotal += $amt;
            } elseif (str_contains($mode, 'CARD')) {
                $cardTotal += $amt;
            } elseif (str_contains($mode, 'BANK')) {
                $bankTotal += $amt;
            } else {
                $cashTotal += $amt;
            }
        }

        if ($totalCollected == 0 && $totalSales > 0) {
            $totalCollected = $totalSales;
            $cashTotal = $totalSales;
        }

        $paymentsBreakdown = [
            'Cash' => '₹' . number_format($cashTotal, 2),
            'UPI' => '₹' . number_format($upiTotal, 2),
            'Card' => '₹' . number_format($cardTotal, 2),
            'Bank' => '₹' . number_format($bankTotal, 2),
        ];

        return [
            'found' => true,
            'period' => $period,
            'period_label' => $periodLabel,
            'total_bills' => $totalBills,
            'total_sales' => '₹' . number_format($totalSales, 2),
            'total_sales_numeric' => $totalSales,
            'total_collected' => '₹' . number_format($totalCollected, 2),
            'gold_weight_sold' => number_format($goldWeight, 3) . ' g',
            'silver_weight_sold' => number_format($silverWeight, 3) . ' g',
            'cash_collected' => '₹' . number_format($cashTotal, 2),
            'upi_collected' => '₹' . number_format($upiTotal, 2),
            'card_collected' => '₹' . number_format($cardTotal, 2),
            'bank_collected' => '₹' . number_format($bankTotal, 2),
            'payment_breakdown' => $paymentsBreakdown,
        ];
    }
}
