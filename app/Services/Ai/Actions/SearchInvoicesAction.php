<?php

namespace App\Services\Ai\Actions;

use App\Models\Invoice;
use App\Services\Ai\Contracts\AiActionInterface;

class SearchInvoicesAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $query = trim($args['query'] ?? ($args['phone'] ?? ($args['customer_name'] ?? ($args['invoice_number'] ?? ''))));
        $phone = trim($args['phone'] ?? ($args['mobile'] ?? ''));
        $customerName = trim($args['customer_name'] ?? ($args['name'] ?? ''));
        $invoiceNo = trim($args['invoice_number'] ?? '');
        $date = trim($args['date'] ?? '');

        $builder = Invoice::with(['customer', 'items', 'transactions']);

        if (! empty($phone)) {
            $builder->whereHas('customer', function ($q) use ($phone) {
                $q->where('mobile', 'LIKE', "%{$phone}%");
            });
        } elseif (! empty($invoiceNo)) {
            $builder->where('invoice_number', 'LIKE', "%{$invoiceNo}%");
        } elseif (! empty($customerName)) {
            $builder->whereHas('customer', function ($q) use ($customerName) {
                $q->where('name', 'LIKE', "%{$customerName}%");
            });
        } elseif (! empty($date)) {
            $builder->whereDate('date', $date);
        } elseif (! empty($query)) {
            $builder->where(function ($q) use ($query) {
                $q->where('invoice_number', 'LIKE', "%{$query}%")
                  ->orWhereHas('customer', function ($cq) use ($query) {
                      $cq->where('mobile', 'LIKE', "%{$query}%")
                         ->orWhere('name', 'LIKE', "%{$query}%");
                  });
            });
        }

        $invoices = $builder->latest('date')->latest('id')->limit(5)->get();

        if ($invoices->isEmpty()) {
            return [
                'found' => false,
                'message' => 'Di gayi details se koi pichla bill / invoice nahi mila.',
                'invoices' => [],
            ];
        }

        $formatted = $invoices->map(function ($inv) {
            $itemsList = $inv->items->map(function ($item) {
                $weight = (float) $item->weight > 0 ? number_format((float) $item->weight, 3) . 'g' : '';
                return trim("{$item->description} {$weight} {$item->purity}");
            })->implode(', ');

            $paid = (float) $inv->transactions->where('type', 'PAYMENT')->sum('amount');
            $pending = max(0, (float) $inv->total_amount - $paid);

            return [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_name' => $inv->customer?->name ?? 'Walk-in Customer',
                'customer_mobile' => $inv->customer?->mobile ?? '—',
                'date' => date('d M Y', strtotime($inv->date)),
                'total_amount' => '₹' . number_format((float) $inv->total_amount, 2),
                'paid_amount' => '₹' . number_format($paid, 2),
                'pending_amount' => '₹' . number_format($pending, 2),
                'status' => $pending <= 0 ? 'COMPLETED' : $inv->status,
                'payment_method' => $inv->payment_method ?? 'CASH',
                'items_summary' => $itemsList ?: 'Jewellery Items',
                'print_url' => "/invoices/{$inv->id}/print",
            ];
        })->toArray();

        return [
            'found' => true,
            'count' => count($formatted),
            'invoices' => $formatted,
        ];
    }
}
