<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Customer;
use App\Models\Mortgage;
use Illuminate\Http\Request;
use App\Models\MortgagePayment;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class MortgageController extends Controller
{
    // 1. Show List (With total owed calculation)
    public function index()
    {
        return Inertia::render('mortgages/Index', [
            'mortgages' => Mortgage::with(['customer', 'payments']) // 👈 Load payments
                ->where('status', 'ACTIVE')
                ->latest()
                ->paginate(10),
            'customers' => Customer::select('id', 'name', 'mobile')->get()
        ]);
    }

    // 2. Store (Create New Loan)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'item_name'   => 'required|string',
            'gross_weight' => 'required|numeric',
            'loan_amount' => 'required|numeric',
            'interest_rate' => 'required|numeric',
            'start_date'  => 'required|date',
            'item_image'  => 'nullable|image|max:2048', // Max 2MB file
        ]);

        // 📸 HANDLE IMAGE UPLOAD
        $imagePath = null;
        if ($request->hasFile('item_image')) {
            // Saves to: storage/app/public/mortgages/filename.jpg
            $imagePath = $request->file('item_image')->store('mortgages', 'public');
        }

        Mortgage::create([
            'customer_id'   => $request->customer_id,
            'item_name'     => $request->item_name,
            'gross_weight'  => $request->gross_weight,
            'loan_amount'   => $request->loan_amount,
            'interest_rate' => $request->interest_rate,
            'start_date'    => Carbon::parse($request->start_date)->format('Y-m-d'),
            'image_path'    => $imagePath, // Save path to DB
            'notes'         => $request->notes ?? '',
        ]);

        return redirect()->route('mortgages.index');
    }

    // 3. Update (Release the Item / Close Loan)
    public function update(Request $request, Mortgage $mortgage)
    {
        // If we are "Releasing" the item (Customer pays back)
        if ($request->action === 'RELEASE') {
            $mortgage->update([
                'status' => 'RELEASED',
                'end_date' => now(),
            ]);
        }

        return back();
    }

    public function addPayment(Request $request, Mortgage $mortgage)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'type'   => 'required|in:INTEREST,PRINCIPAL',
            'date'   => 'required|date',
        ]);

        MortgagePayment::create([
            'mortgage_id' => $mortgage->id,
            'amount'      => $request->amount,
            'type'        => $request->type,
            'date'        => $request->date,
            'note'        => $request->note
        ]);

        // Auto-Close: If Pending Amount is 0, mark as RELEASED
        if ($mortgage->pending_amount <= 0) {
            $mortgage->update(['status' => 'RELEASED', 'end_date' => now()]);
        }

        return back();
    }
}
