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
            'category'       => 'required|string|max:100',
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:CASH,UPI,BANK',
            'date'           => 'required|date',
        ]);

        $validated['title'] = trim((string) $validated['title']);
        $validated['category'] = trim((string) $validated['category']);

        if ($validated['title'] === '') {
            throw ValidationException::withMessages([
                'title' => 'Enter an expense title.',
            ]);
        }

        if ($validated['category'] === '') {
            throw ValidationException::withMessages([
                'category' => 'Select a valid expense category.',
            ]);
        }

        $vaultType = ($validated['payment_method'] === 'CASH') ? VaultType::CASH : VaultType::BANK;
        $availableBalance = VaultService::getBalance($vaultType);

        if ((float) $validated['amount'] > $availableBalance) {
            throw ValidationException::withMessages([
                'amount' => 'Expense amount exceeds available ' . strtolower($vaultType->value === VaultType::CASH->value ? 'cash' : 'bank') . ' balance of ' . number_format($availableBalance, 2, '.', '') . '.',
            ]);
        }

        try {
            DB::transaction(function () use ($validated, $vaultType) {

                // 1. DEDUCT MONEY
                // This line throws the Exception if funds are low
                VaultService::debit($vaultType, $validated['amount'], [
                    'source_type' => Expense::class,
                    'reference' => $validated['title'],
                    'user_id' => Auth::id(),
                    'recorded_at' => $validated['date'],
                    'note' => "Expense paid: {$validated['title']}",
                ]);

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
            VaultService::credit($vaultType, $expense->amount, [
                'source_type' => Expense::class,
                'source_id' => $expense->id,
                'reference' => $expense->title,
                'user_id' => Auth::id(),
                'note' => "Expense reversed: {$expense->title}",
            ]);

            // 2. DELETE RECORD
            $expense->delete();
        });

        return redirect()->back()->with('success', 'Expense Deleted & Money Refunded');
    }
}
