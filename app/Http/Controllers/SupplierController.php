<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $suppliersQuery = Supplier::query();

        if ($search !== '') {
            $suppliersQuery->where(function ($query) use ($search) {
                $query->where('company_name', 'like', '%' . $search . '%')
                    ->orWhere('contact_person', 'like', '%' . $search . '%')
                    ->orWhere('mobile', 'like', '%' . $search . '%');
            });

        }

        $suppliers = $suppliersQuery->get()->map(fn (Supplier $supplier) => $this->transformSupplier($supplier));
        $recoveryDesk = $suppliers
            ->map(fn ($supplier) => [
                'id' => $supplier['id'],
                'name' => $supplier['company_name'],
                'cash_balance' => $supplier['cash_balance'],
                'metal_balance' => $supplier['metal_balance'],
                'priority_score' => abs($supplier['cash_balance']) + (abs($supplier['metal_balance']) * 10000),
            ])
            ->sortByDesc('priority_score')
            ->take(6)
            ->values();

        return Inertia::render('suppliers/Index', [
            'suppliers' => $suppliers,
            'recoveryDesk' => $recoveryDesk,
            'metrics' => [
                'supplier_count' => $suppliers->count(),
                'supplier_cash_exposure' => $suppliers->sum('cash_balance'),
                'supplier_gold_out' => $suppliers->sum('metal_balance'),
                'urgent_accounts' => $recoveryDesk->filter(fn ($row) => abs($row['cash_balance']) > 0 || abs($row['metal_balance']) > 0)->count(),
            ],
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Identity
            'company_name'   => 'required|string|max:255|unique:suppliers,company_name',
            'contact_person' => 'required|string|max:255',
            'mobile'         => 'required|regex:/^[0-9]{10}$/', // Strict 10-digit validation

            // Type Enforce
            'type'           => 'required|in:GOLD,SILVER,DIAMOND,PACKAGING',

            // Taxation (Indian Formats)
            'gst_number'     => [
                'nullable',
                'string',
                'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', // Valid GST format
                'unique:suppliers,gst_number'
            ],
            'pan_no'         => [
                'nullable',
                'string',
                'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/' // Valid PAN format
            ],

            // Banking
            'bank_name'      => 'nullable|string|max:100',
            'account_no'     => 'nullable|numeric',
            'ifsc_code'      => 'nullable|string|size:11|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/', // Standard IFSC
        ], [
            // Custom Error Messages
            'mobile.regex'     => 'Mobile number must be exactly 10 digits.',
            'gst_number.regex' => 'Invalid GST Number format.',
            'pan_no.regex'     => 'Invalid PAN Card format (e.g., ABCDE1234F).',
            'ifsc_code.regex'  => 'Invalid IFSC Code (must be 11 chars).',
            'type.in'          => 'Invalid Supplier Type selected.',
        ]);

        Supplier::create($validated);

        return redirect()->back()->with('message', 'Supplier Created Successfully');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            // Identity
            'company_name' => 'required|string|max:255|unique:suppliers,company_name,' . $supplier->id,
            'contact_person' => 'required|string|max:255',
            'mobile'         => 'required|regex:/^[0-9]{10}$/', // Strict 10-digit validation

            // Type Enforce
            'type'           => 'required|in:GOLD,SILVER,DIAMOND,PACKAGING',

            // Taxation (Indian Formats)
            'gst_number'     => [
                'nullable',
                'string',
                'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', // Valid GST format
                'unique:suppliers,gst_number,' . $supplier->id
            ],
            'pan_no'         => [
                'nullable',
                'string',
                'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/' // Valid PAN format
            ],

            // Banking
            'bank_name'      => 'nullable|string|max:100',
            'account_no'     => 'nullable|numeric',
            'ifsc_code'      => 'nullable|string|size:11|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/', // Standard IFSC
        ], [
            // Custom Error Messages
            'mobile.regex'     => 'Mobile number must be exactly 10 digits.',
            'gst_number.regex' => 'Invalid GST Number format.',
            'pan_no.regex'     => 'Invalid PAN Card format (e.g., ABCDE1234F).',
            'ifsc_code.regex'  => 'Invalid IFSC Code (must be 11 chars).',
            'type.in'          => 'Invalid Supplier Type selected.',
        ]);

        $supplier->update($validated);

        return redirect()->back()->with('message', 'Supplier Updated Successfully');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return redirect()->back()->with('message', 'Supplier Deleted');
    }

    private function transformSupplier(Supplier $supplier): array
    {
        $cashPaid = $supplier->transactions()->where('type', 'PAYMENT')->sum('amount');
        $cashReceived = $supplier->transactions()->where('type', 'RECEIPT')->sum('amount');

        return [
            'id' => $supplier->id,
            'company_name' => $supplier->company_name,
            'contact_person' => $supplier->contact_person,
            'mobile' => $supplier->mobile,
            'type' => $supplier->type,
            'gst_number' => $supplier->gst_number,
            'pan_no' => $supplier->pan_no,
            'bank_name' => $supplier->bank_name,
            'account_no' => $supplier->account_no,
            'ifsc_code' => $supplier->ifsc_code,
            'cash_balance' => (float) $cashPaid - (float) $cashReceived,
            'metal_balance' => (float) $supplier->metal_balance,
        ];
    }
}
