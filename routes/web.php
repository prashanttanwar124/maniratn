<?php

use Inertia\Inertia;
use App\Models\Product;
use Laravel\Fortify\Features;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\MortgageController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\MetalTransactionController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// Utility API Route (Used by Frontend to scan barcodes)
Route::get('/api/products/{barcode}', function ($barcode) {
    return Product::with(['category', 'purity'])
        ->where('barcode', $barcode)
        ->firstOrFail();
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Requires Login)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    // --- 1. DASHBOARD (Smart Redirect) ---
    // The controller decides if user sees Admin or Staff view
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // --- 2. ADMIN ONLY ROUTES (Financial Control) ---
    Route::group(['middleware' => ['role:admin']], function () {

        // Start the Day & Verify Stock
        Route::post('/dashboard/open-day', [DashboardController::class, 'openDay'])
            ->name('dashboard.open-day');

        Route::post('/dashboard/close-day', [DashboardController::class, 'closeDay'])
            ->name('dashboard.close-day');


        // Update Gold/Silver Market Rates
        Route::post('/dashboard/update-rates', [DashboardController::class, 'updateRates'])
            ->name('dashboard.update-rates');

        // Add Funds (Mid-day cash injection)
        Route::post('/dashboard/add-funds', [DashboardController::class, 'addFunds'])
            ->name('dashboard.add-funds');



        Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'destroy']);
    });

    // --- 3. ORDER TRANSACTIONS (Issue / Settle / Receive) ---
    // Used by the "Transaction" button in the Order Table
    Route::post('/orders/{item}/transaction', [OrderController::class, 'storeTransaction'])
        ->name('orders.transaction');

    // // --- 4. EXPENSES (Tea, Petrol, Salary) ---
    // Route::post('/expenses', [ExpenseController::class, 'store'])
    //     ->name('expenses.store');

    // --- PRODUCTS ---
    // CRITICAL: Custom routes must come BEFORE Route::resource
    Route::get('products/print-barcodes', [ProductController::class, 'printBarcodes'])
        ->name('products.print_barcodes');

    Route::resource('products', ProductController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // --- CUSTOMERS ---
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::get('/customers/{id}', [CustomerController::class, 'show'])->name('customers.show');

    // --- MORTGAGES (Girvi) ---
    Route::post('mortgages/{mortgage}/payment', [MortgageController::class, 'addPayment'])
        ->name('mortgages.payment');

    Route::resource('mortgages', MortgageController::class)
        ->only(['index', 'store', 'update']);

    // --- INVOICES ---
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoice/store', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');

    // --- CATEGORIES ---
    Route::get('categories', function () {
        return Inertia::render('categories/Index');
    })->name('categories');


    // --- suppliers ---
    Route::resource('suppliers', SupplierController::class)
        ->only(['index', 'store', 'update', 'destroy']);



    Route::get('/{type}/ledger/{id}', [LedgerController::class, 'show'])
        ->where('type', 'suppliers|karigars|customers') // Limit to valid types
        ->name('ledger.show');

    Route::post('/ledger/store-entry', [LedgerController::class, 'storeEntry'])
        ->name('ledger.store-entry');


    // --- suppliers ---
    // Standard Resource Routes (Index, Store, etc.)
    Route::resource('orders', OrderController::class);

    // Custom Workflow Actions
    Route::post('orders/{orderItem}/assign', [OrderController::class, 'assign'])->name('orders.assign');
    Route::post('orders/{orderItem}/complete', [OrderController::class, 'complete'])->name('orders.complete');
    Route::post('orders/{orderItem}/deliver', [OrderController::class, 'deliver'])->name('orders.deliver');
    Route::post('orders/{orderItem}/transaction', [OrderController::class, 'addTransaction'])->name('orders.transaction');



    // 1. Route for CASH Entries (Money)
    Route::post('/transactions', [TransactionController::class, 'store'])
        ->name('transactions.store');

    // 2. Route for METAL Entries (Gold)
    Route::post('/metal-transactions', [MetalTransactionController::class, 'store'])
        ->name('metal-transactions.store');
});

require __DIR__ . '/settings.php';
