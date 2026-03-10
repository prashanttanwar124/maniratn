<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Enums\VaultType;
use App\Services\VaultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function index()
    {
        return Inertia::render('expenses/Index', [
            'expenses' => Expense::with('user')
                ->latest()
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'category'       => 'required|string',
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:CASH,UPI,BANK',
            'date'           => 'required|date',
        ]);

        try {
            DB::transaction(function () use ($validated) {

                // 1. DEDUCT MONEY
                $vaultType = ($validated['payment_method'] === 'CASH') ? VaultType::CASH : VaultType::BANK;

                // This line throws the Exception if funds are low
                VaultService::debit($vaultType, $validated['amount']);

                // 2. CREATE RECORD
                Expense::create([
                    'title'          => $validated['title'],
                    'category'       => $validated['category'],
                    'amount'         => $validated['amount'],
                    'payment_method' => $validated['payment_method'],
                    'user_id'        => Auth::id(),
                    'created_at'     => $validated['date'],
                ]);
            });
        } catch (\Exception $e) {
            // CATCH THE ERROR AND SEND BACK TO FORM
            throw ValidationException::withMessages([
                'amount' => $e->getMessage() // Shows error under the "Amount" field
            ]);
        }

        return redirect()->back()->with('success', 'Expense Recorded');
    }

    public function destroy(Expense $expense)
    {
        DB::transaction(function () use ($expense) {
            // 1. REFUND MONEY BACK TO VAULT (Reverse the expense)
            $vaultType = ($expense->payment_method === 'CASH') ? VaultType::CASH : VaultType::BANK;
            VaultService::credit($vaultType, $expense->amount);

            // 2. DELETE RECORD
            $expense->delete();
        });

        return redirect()->back()->with('success', 'Expense Deleted & Money Refunded');
    }
}
