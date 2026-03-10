<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\MetalTransaction;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();

        if ($request->search) {
            $query->where('company_name', 'like', '%' . $request->search . '%')
                ->orWhere('mobile', 'like', '%' . $request->search . '%');
        }

        return Inertia::render('suppliers/Index', [
            'suppliers' => $query->paginate(10),
            'filters' => $request->only(['search']), // Pass back to keep input filled
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
}
