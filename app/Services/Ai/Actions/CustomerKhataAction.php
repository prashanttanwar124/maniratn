<?php

namespace App\Services\Ai\Actions;

use App\Models\Customer;
use App\Services\Ai\Contracts\AiActionInterface;

class CustomerKhataAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $rawSearch = trim($args['query'] ?? ($args['phone'] ?? ($args['mobile'] ?? ($args['customer_name'] ?? ($args['name'] ?? '')))));

        if (empty($rawSearch)) {
            return [
                'found' => false,
                'message' => 'Kripya customer ka naam ya mobile number batayein.',
            ];
        }

        // Clean 10-digit phone
        $cleanPhone = '';
        $digits = preg_replace('/[^0-9]/', '', $rawSearch);
        if (strlen($digits) >= 10) {
            $cleanPhone = substr($digits, -10);
        }

        // Clean customer name by removing noise words
        $noiseWords = ['customer', 'grahak', 'party', 'khata', 'udhar', 'balance', 'ka', 'ki', 'ke', 'ji', 'sahab', 'madam', 'sir', 'shree', 'mr', 'mrs', 'batao', 'dikhao', 'search', 'check'];
        $cleanName = $rawSearch;
        foreach ($noiseWords as $noise) {
            $cleanName = preg_replace('/\b' . preg_quote($noise, '/') . '\b/iu', '', $cleanName);
        }
        $cleanName = trim(preg_replace('/\s+/', ' ', $cleanName));

        // Search customer
        $customer = null;

        // 1. Phone Match
        if (! empty($cleanPhone)) {
            $customer = Customer::with(['invoices' => function ($q) {
                $q->where('status', '!=', 'CANCELLED')->with('transactions')->latest('date')->limit(5);
            }])
            ->where('mobile', 'LIKE', "%{$cleanPhone}%")
            ->first();
        }

        // 2. Cleaned Name & Token Match
        if (! $customer && ! empty($cleanName)) {
            $nameTokens = array_filter(explode(' ', $cleanName), fn($t) => strlen($t) >= 2);
            $customer = Customer::with(['invoices' => function ($q) {
                $q->where('status', '!=', 'CANCELLED')->with('transactions')->latest('date')->limit(5);
            }])
            ->where(function ($q) use ($cleanName, $nameTokens) {
                $q->where('name', 'LIKE', "%{$cleanName}%");
                foreach ($nameTokens as $token) {
                    $q->orWhere('name', 'LIKE', "%{$token}%");
                }
            })
            ->first();
        }

        // 3. Raw Search Fallback
        if (! $customer) {
            $customer = Customer::with(['invoices' => function ($q) {
                $q->where('status', '!=', 'CANCELLED')->with('transactions')->latest('date')->limit(5);
            }])
            ->where(function ($q) use ($rawSearch) {
                $q->where('mobile', 'LIKE', "%{$rawSearch}%")
                  ->orWhere('name', 'LIKE', "%{$rawSearch}%");
            })
            ->first();
        }

        if (! $customer) {
            return [
                'found' => false,
                'message' => "Customer '{$rawSearch}' showroom database me nahi mila.",
            ];
        }

        $allInvoices = $customer->invoices()->where('status', '!=', 'CANCELLED')->with('transactions')->get();
        $totalPurchases = (float) $allInvoices->sum('total_amount');
        $totalPaid = 0.0;

        foreach ($allInvoices as $inv) {
            $totalPaid += (float) $inv->transactions->where('type', 'PAYMENT')->sum('amount');
        }

        $pendingDue = max(0, $totalPurchases - $totalPaid);

        // Account status text
        if ($pendingDue > 0) {
            $statusText = '₹' . number_format($pendingDue, 2) . ' Udhar / Baaki Hai';
            $statusType = 'DUE';
        } else {
            $statusText = 'Hisaab Ekdum Barabar (No Due)';
            $statusType = 'SETTLED';
        }

        $recentBills = $customer->invoices->map(function ($inv) {
            $paid = (float) $inv->transactions->where('type', 'PAYMENT')->sum('amount');
            return [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'date' => date('d M Y', strtotime($inv->date)),
                'total' => '₹' . number_format((float) $inv->total_amount, 2),
                'paid' => '₹' . number_format($paid, 2),
                'pending' => '₹' . number_format(max(0, (float) $inv->total_amount - $paid), 2),
                'status' => $inv->status,
            ];
        })->toArray();

        $vaultUrl = $customer->vault_token ? url('/vault/' . $customer->vault_token) : null;

        return [
            'found' => true,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'mobile' => $customer->mobile ?: '—',
            'city' => $customer->city ?: '—',
            'vault_token' => $customer->vault_token,
            'vault_url' => $vaultUrl,
            'total_bills_count' => $allInvoices->count(),
            'total_purchases' => '₹' . number_format($totalPurchases, 2),
            'total_paid' => '₹' . number_format($totalPaid, 2),
            'pending_due' => '₹' . number_format($pendingDue, 2),
            'pending_due_numeric' => $pendingDue,
            'status_text' => $statusText,
            'status_type' => $statusType,
            'recent_bills' => $recentBills,
        ];
    }
}
