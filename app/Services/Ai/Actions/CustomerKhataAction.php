<?php

namespace App\Services\Ai\Actions;

use App\Models\Customer;
use App\Services\Ai\Contracts\AiActionInterface;

class CustomerKhataAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $search = trim($args['query'] ?? ($args['phone'] ?? ($args['mobile'] ?? ($args['customer_name'] ?? ($args['name'] ?? '')))));

        if (empty($search)) {
            return [
                'found' => false,
                'message' => 'Kripya customer ka naam ya mobile number batayein.',
            ];
        }

        // Search customer by exact mobile or name
        $customer = Customer::with(['invoices' => function ($q) {
            $q->where('status', '!=', 'CANCELLED')->with('transactions')->latest('date')->limit(5);
        }])
        ->where('mobile', $search)
        ->orWhere('mobile', 'LIKE', "%{$search}%")
        ->orWhere('name', 'LIKE', "%{$search}%")
        ->first();

        if (! $customer) {
            return [
                'found' => false,
                'message' => "Customer '{$search}' showroom database me nahi mila.",
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

        return [
            'found' => true,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'mobile' => $customer->mobile ?: '—',
            'city' => $customer->city ?: '—',
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
