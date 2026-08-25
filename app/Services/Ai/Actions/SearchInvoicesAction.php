<?php

namespace App\Services\Ai\Actions;

use App\Models\Invoice;
use App\Services\Ai\Contracts\AiActionInterface;

class SearchInvoicesAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $rawQuery = trim($args['query'] ?? ($args['phone'] ?? ($args['customer_name'] ?? ($args['invoice_number'] ?? ''))));
        $phone = trim($args['phone'] ?? ($args['mobile'] ?? ''));
        $customerName = trim($args['customer_name'] ?? ($args['name'] ?? ''));
        $invoiceNo = trim($args['invoice_number'] ?? '');
        $date = trim($args['date'] ?? '');

        // Extract clean 10-digit phone if present
        $cleanPhone = '';
        if (! empty($phone)) {
            $digits = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($digits) >= 10) {
                $cleanPhone = substr($digits, -10);
            }
        } elseif (! empty($rawQuery)) {
            $digits = preg_replace('/[^0-9]/', '', $rawQuery);
            if (strlen($digits) >= 10) {
                $cleanPhone = substr($digits, -10);
            }
        }

        // Clean customer name by removing common conversational noise words
        $noiseWords = ['customer', 'grahak', 'party', 'bill', 'invoice', 'pichla', 'last', 'ka', 'ki', 'ke', 'ji', 'sahab', 'madam', 'sir', 'shree', 'mr', 'mrs', 'batao', 'dikhao', 'search', 'check', 'nikalo'];
        $cleanName = $customerName ?: $rawQuery;
        foreach ($noiseWords as $noise) {
            $cleanName = preg_replace('/\b' . preg_quote($noise, '/') . '\b/iu', '', $cleanName);
        }
        $cleanName = trim(preg_replace('/\s+/', ' ', $cleanName));

        $invoices = collect();

        // Strategy A: Search by Clean 10-digit Phone
        if (! empty($cleanPhone)) {
            $invoices = Invoice::with(['customer', 'items', 'transactions'])
                ->whereHas('customer', function ($q) use ($cleanPhone) {
                    $q->where('mobile', 'LIKE', "%{$cleanPhone}%");
                })
                ->latest('date')->latest('id')->limit(5)->get();
        }

        // Strategy B: Search by Invoice Number
        if ($invoices->isEmpty() && ! empty($invoiceNo)) {
            $invoices = Invoice::with(['customer', 'items', 'transactions'])
                ->where('invoice_number', 'LIKE', "%{$invoiceNo}%")
                ->latest('date')->latest('id')->limit(5)->get();
        }

        // Strategy C: Search by Cleaned Customer Name & Name Tokens
        if ($invoices->isEmpty() && ! empty($cleanName)) {
            $nameTokens = array_filter(explode(' ', $cleanName), fn($t) => strlen($t) >= 2);
            $invoices = Invoice::with(['customer', 'items', 'transactions'])
                ->whereHas('customer', function ($q) use ($cleanName, $nameTokens) {
                    $q->where('name', 'LIKE', "%{$cleanName}%");
                    foreach ($nameTokens as $token) {
                        $q->orWhere('name', 'LIKE', "%{$token}%");
                    }
                })
                ->latest('date')->latest('id')->limit(5)->get();
        }

        // Strategy D: Search by Date
        if ($invoices->isEmpty() && ! empty($date)) {
            $invoices = Invoice::with(['customer', 'items', 'transactions'])
                ->whereDate('date', $date)
                ->latest('date')->latest('id')->limit(5)->get();
        }

        // Strategy E: Fallback to Raw Query across Invoice No, Customer Mobile, Customer Name
        if ($invoices->isEmpty() && ! empty($rawQuery)) {
            $invoices = Invoice::with(['customer', 'items', 'transactions'])
                ->where(function ($q) use ($rawQuery) {
                    $q->where('invoice_number', 'LIKE', "%{$rawQuery}%")
                      ->orWhereHas('customer', function ($cq) use ($rawQuery) {
                          $cq->where('mobile', 'LIKE', "%{$rawQuery}%")
                             ->orWhere('name', 'LIKE', "%{$rawQuery}%");
                      });
                })
                ->latest('date')->latest('id')->limit(5)->get();
        }

        // Strategy F: If general bill inquiry (e.g. "recent bills", "pichle bills dikhao", "aaj ke bills"), return latest invoices
        if ($invoices->isEmpty() && empty($cleanPhone) && empty($cleanName) && empty($invoiceNo)) {
            $invoices = Invoice::with(['customer', 'items', 'transactions'])
                ->latest('date')->latest('id')->limit(5)->get();
        }

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
                'status' => $inv->status === 'CANCELLED' ? 'CANCELLED' : ($pending <= 0 ? 'COMPLETED' : 'DUE'),
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
