<?php

use Inertia\Inertia;
use App\Models\Product;
use App\Models\SilverProduct;
use Laravel\Fortify\Features;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SilverProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\MortgageController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\KarigarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceTerminalController;
use App\Http\Controllers\VerificationTagController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\MetalTransactionController;
use App\Http\Controllers\GoldSchemeController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::redirect('/', '/dashboard')->name('home');

Route::get('/attendance-terminal', [AttendanceTerminalController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('attendance-terminal.show');
Route::post('/attendance-terminal/identify', [AttendanceTerminalController::class, 'identify'])
    ->middleware('throttle:120,1')
    ->name('attendance-terminal.identify');
Route::post('/attendance-terminal/act', [AttendanceTerminalController::class, 'act'])
    ->middleware('throttle:120,1')
    ->name('attendance-terminal.act');

Route::get('/api/inventory/{barcode}', function ($barcode) {
    $normalizedBarcode = strtoupper(trim($barcode));

    $goldQuery = function () use ($normalizedBarcode) {
        $product = Product::with(['category', 'purity'])
            ->where('barcode', $normalizedBarcode)
            ->first();

        if (! $product && preg_match('/^G(\d{5})$/', $normalizedBarcode, $matches)) {
            $product = Product::with(['category', 'purity'])->find((int) $matches[1]);
        }

        return $product;
    };

    $silverQuery = function () use ($normalizedBarcode) {
        $product = SilverProduct::with(['category', 'supplier'])
            ->where('barcode', $normalizedBarcode)
            ->first();

        if (! $product && preg_match('/^S(\d{5})$/', $normalizedBarcode, $matches)) {
            $product = SilverProduct::with(['category', 'supplier'])->find((int) $matches[1]);
        }

        return $product;
    };

    $record = null;
    $type = null;

    if (preg_match('/^S\d{5}$/', $normalizedBarcode)) {
        $record = $silverQuery();
        $type = $record ? 'silver_product' : null;
    } elseif (preg_match('/^G\d{5}$/', $normalizedBarcode)) {
        $record = $goldQuery();
        $type = $record ? 'product' : null;
    }

    if (! $record) {
        $record = $goldQuery();
        $type = $record ? 'product' : null;
    }

    if (! $record) {
        $record = $silverQuery();
        $type = $record ? 'silver_product' : null;
    }

    abort_unless($record && $type, 404);

    return response()->json([
        'inventory_type' => $type,
        'item' => $record,
    ]);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Requires Login)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    // --- 1. DASHBOARD (Smart Redirect) ---
    // The controller decides if user sees Admin or Staff view
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:view_dashboard')
        ->name('dashboard');

    // --- 2. ADMIN ONLY ROUTES (Financial Control) ---
    Route::group(['middleware' => ['permission:manage_vault|manage_daily_rates|manage_expenses|manage_users|manage_roles_permissions']], function () {

        // Start the Day & Verify Stock
        Route::post('/dashboard/open-day', [DashboardController::class, 'openDay'])
            ->middleware('permission:manage_vault')
            ->name('dashboard.open-day');

        Route::post('/dashboard/close-day', [DashboardController::class, 'closeDay'])
            ->middleware('permission:manage_vault')
            ->name('dashboard.close-day');


        // Update Gold/Silver Market Rates
        Route::post('/dashboard/update-rates', [DashboardController::class, 'updateRates'])
            ->middleware('permission:manage_daily_rates')
            ->name('dashboard.update-rates');

        // Add Funds (Mid-day cash injection)
        Route::post('/dashboard/add-funds', [DashboardController::class, 'addFunds'])
            ->middleware('permission:manage_vault')
            ->middleware('day.open')
            ->name('dashboard.add-funds');

        Route::resource('expenses', ExpenseController::class)->only(['index'])->middleware('permission:manage_expenses');
        Route::post('/expenses', [ExpenseController::class, 'store'])->middleware(['permission:manage_expenses', 'day.open'])->name('expenses.store');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware(['permission:manage_expenses', 'day.open'])->name('expenses.destroy');

        Route::get('/users', [UserManagementController::class, 'index'])->middleware('permission:manage_users')->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->middleware('permission:manage_users')->name('users.store');
        Route::patch('/users/{user}', [UserManagementController::class, 'update'])->middleware('permission:manage_users')->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->middleware('permission:manage_users')->name('users.destroy');
        Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('permission:manage_users')->name('attendance.index');
        Route::post('/attendance/{attendance}/reopen', [AttendanceController::class, 'reopen'])->middleware(['permission:manage_users', 'day.open'])->name('attendance.reopen');
        Route::post('/attendance/reasons', [AttendanceController::class, 'storeReason'])->middleware(['permission:manage_users', 'day.open'])->name('attendance.reasons.store');
        Route::patch('/attendance/reasons/{reason}', [AttendanceController::class, 'updateReason'])->middleware(['permission:manage_users', 'day.open'])->name('attendance.reasons.update');
        Route::delete('/attendance/reasons/{reason}', [AttendanceController::class, 'destroyReason'])->middleware(['permission:manage_users', 'day.open'])->name('attendance.reasons.destroy');
        Route::post('/roles', [UserManagementController::class, 'storeRole'])->middleware('permission:manage_roles_permissions')->name('roles.store');
        Route::patch('/roles/{role}', [UserManagementController::class, 'updateRole'])->middleware('permission:manage_roles_permissions')->name('roles.update');
        Route::delete('/roles/{role}', [UserManagementController::class, 'destroyRole'])->middleware('permission:manage_roles_permissions')->name('roles.destroy');
        Route::post('/permissions', [UserManagementController::class, 'storePermission'])->middleware('permission:manage_roles_permissions')->name('permissions.store');
        Route::patch('/permissions/{permission}', [UserManagementController::class, 'updatePermission'])->middleware('permission:manage_roles_permissions')->name('permissions.update');
        Route::delete('/permissions/{permission}', [UserManagementController::class, 'destroyPermission'])->middleware('permission:manage_roles_permissions')->name('permissions.destroy');
    });

    // // --- 4. EXPENSES (Tea, Petrol, Salary) ---
    // Route::post('/expenses', [ExpenseController::class, 'store'])
    //     ->name('expenses.store');

    // --- PRODUCTS ---
    // CRITICAL: Custom routes must come BEFORE Route::resource
    Route::get('products/print-barcodes', [ProductController::class, 'printBarcodes'])
        ->middleware('permission:manage_products')
        ->name('products.print_barcodes');

    Route::resource('products', ProductController::class)
        ->only(['index'])
        ->middleware('permission:manage_products');
    Route::post('/products', [ProductController::class, 'store'])->middleware(['permission:manage_products', 'day.open'])->name('products.store');
    Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])->middleware(['permission:manage_products', 'day.open'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware(['permission:manage_products', 'day.open'])->name('products.destroy');

    Route::get('silver-products/print-barcodes', [SilverProductController::class, 'printBarcodes'])
        ->middleware('permission:manage_products')
        ->name('silver-products.print_barcodes');

    Route::resource('silver-products', SilverProductController::class)
        ->only(['index'])
        ->middleware('permission:manage_products');
    Route::post('/silver-products', [SilverProductController::class, 'store'])->middleware(['permission:manage_products', 'day.open'])->name('silver-products.store');
    Route::match(['put', 'patch'], '/silver-products/{silverProduct}', [SilverProductController::class, 'update'])->middleware(['permission:manage_products', 'day.open'])->name('silver-products.update');
    Route::delete('/silver-products/{silverProduct}', [SilverProductController::class, 'destroy'])->middleware(['permission:manage_products', 'day.open'])->name('silver-products.destroy');

    // --- CUSTOMERS ---
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:manage_customers')->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware(['permission:manage_customers', 'day.open'])->name('customers.store');
    Route::post('/customers/quick-store', [CustomerController::class, 'quickStore'])->middleware(['permission:manage_customers|manage_invoices', 'day.open'])->name('customers.quick-store');
    Route::get('/customers/search', [CustomerController::class, 'search'])->middleware('permission:manage_customers|manage_invoices')->name('customers.search');
    Route::get('/customers/{id}', [CustomerController::class, 'show'])->middleware('permission:manage_customers')->name('customers.show');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware(['permission:manage_customers', 'day.open'])->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware(['permission:manage_customers', 'day.open'])->name('customers.destroy');

    // --- GOLD SCHEMES ---
    Route::get('/gold-schemes', [GoldSchemeController::class, 'index'])->middleware('permission:manage_gold_schemes')->name('gold-schemes.index');
    Route::get('/gold-schemes/{goldScheme}/print', [GoldSchemeController::class, 'print'])->middleware('permission:manage_gold_schemes')->name('gold-schemes.print');
    Route::get('/gold-schemes/{goldScheme}', [GoldSchemeController::class, 'show'])->middleware('permission:manage_gold_schemes')->name('gold-schemes.show');
    Route::post('/gold-schemes/enroll', [GoldSchemeController::class, 'enroll'])->middleware(['permission:manage_gold_schemes', 'day.open'])->name('gold-schemes.enroll');
    Route::match(['put', 'patch'], '/gold-schemes/{goldScheme}', [GoldSchemeController::class, 'update'])->middleware(['permission:manage_gold_schemes', 'day.open'])->name('gold-schemes.update');
    Route::post('/gold-schemes/{goldScheme}/cancel', [GoldSchemeController::class, 'cancel'])->middleware(['permission:manage_gold_schemes', 'day.open'])->name('gold-schemes.cancel');
    Route::post('/gold-schemes/installments/{goldSchemeInstallment}/pay', [GoldSchemeController::class, 'payInstallment'])->middleware(['permission:manage_gold_schemes', 'day.open'])->name('gold-schemes.installments.pay');
    Route::post('/gold-schemes/installments/{goldSchemeInstallment}/void', [GoldSchemeController::class, 'voidInstallment'])->middleware(['permission:manage_gold_schemes', 'day.open'])->name('gold-schemes.installments.void');

    // --- MORTGAGES (Girvi) ---
    Route::post('mortgages/{mortgage}/payment', [MortgageController::class, 'addPayment'])
        ->middleware(['permission:manage_mortgages', 'day.open'])
        ->name('mortgages.payment');

    Route::resource('mortgages', MortgageController::class)
        ->only(['index'])
        ->middleware('permission:manage_mortgages');
    Route::post('/mortgages', [MortgageController::class, 'store'])->middleware(['permission:manage_mortgages', 'day.open'])->name('mortgages.store');
    Route::match(['put', 'patch'], '/mortgages/{mortgage}', [MortgageController::class, 'update'])->middleware(['permission:manage_mortgages', 'day.open'])->name('mortgages.update');

    // --- INVOICES ---
    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:manage_invoices')->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->middleware('permission:manage_invoices')->name('invoices.create');
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->middleware('permission:manage_invoices')->name('invoices.print');
    Route::post('/invoices/drafts', [InvoiceController::class, 'saveDraft'])->middleware('permission:manage_invoices')->name('invoices.drafts.store');
    Route::post('/invoices/drafts/validate', [InvoiceController::class, 'validateDraftItems'])->middleware('permission:manage_invoices')->name('invoices.drafts.validate');
    Route::delete('/invoices/drafts/{invoiceDraft}', [InvoiceController::class, 'destroyDraft'])->middleware('permission:manage_invoices')->name('invoices.drafts.destroy');
    Route::post('/invoice/store', [InvoiceController::class, 'store'])->middleware(['permission:manage_invoices', 'day.open'])->name('invoices.store');
    Route::post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel'])->middleware(['permission:manage_invoices', 'day.open'])->name('invoices.cancel');
    Route::get('/verification-tags', [VerificationTagController::class, 'index'])->middleware('permission:manage_invoices')->name('verification-tags.index');
    Route::post('/verification-tags', [VerificationTagController::class, 'store'])->middleware(['permission:manage_invoices', 'day.open'])->name('verification-tags.store');
    Route::get('/verification-tags/{verificationTag}/qr', [VerificationTagController::class, 'qr'])->middleware('permission:manage_invoices')->name('verification-tags.qr');
    Route::get('/verification-tags/{verificationTag}/writer', [VerificationTagController::class, 'writer'])->middleware('permission:manage_invoices')->name('verification-tags.writer');
    Route::post('/verification-tags/{verificationTag}/confirm-written', [VerificationTagController::class, 'confirmWritten'])->middleware(['permission:manage_invoices', 'day.open'])->name('verification-tags.confirm-written');
    Route::patch('/verification-tags/{verificationTag}/written', [VerificationTagController::class, 'markWritten'])->middleware(['permission:manage_invoices', 'day.open'])->name('verification-tags.written');
    Route::patch('/verification-tags/{verificationTag}/lock', [VerificationTagController::class, 'lock'])->middleware(['permission:manage_invoices', 'day.open'])->name('verification-tags.lock');
    Route::patch('/verification-tags/{verificationTag}/deactivate', [VerificationTagController::class, 'deactivate'])->middleware(['permission:manage_invoices', 'day.open'])->name('verification-tags.deactivate');

    // --- CATEGORIES ---
    Route::get('categories', [CategoryController::class, 'index'])->middleware('permission:manage_categories')->name('categories');
    Route::post('categories', [CategoryController::class, 'store'])->middleware(['permission:manage_categories', 'day.open'])->name('categories.store');
    Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update'])->middleware(['permission:manage_categories', 'day.open'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware(['permission:manage_categories', 'day.open'])->name('categories.destroy');


    // --- suppliers ---
    Route::resource('suppliers', SupplierController::class)
        ->only(['index'])
        ->middleware('permission:manage_suppliers');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware(['permission:manage_suppliers', 'day.open'])->name('suppliers.store');
    Route::match(['put', 'patch'], '/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware(['permission:manage_suppliers', 'day.open'])->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware(['permission:manage_suppliers', 'day.open'])->name('suppliers.destroy');

    Route::get('/karigars', [KarigarController::class, 'index'])->middleware('permission:manage_orders|settle_karigar')->name('karigars.index');
    Route::post('/karigars', [KarigarController::class, 'store'])->middleware(['permission:manage_orders|settle_karigar', 'day.open'])->name('karigars.store');
    Route::put('/karigars/{karigar}', [KarigarController::class, 'update'])->middleware(['permission:manage_orders|settle_karigar', 'day.open'])->name('karigars.update');
    Route::delete('/karigars/{karigar}', [KarigarController::class, 'destroy'])->middleware(['permission:manage_orders|settle_karigar', 'day.open'])->name('karigars.destroy');

    Route::get('/staff', [StaffController::class, 'index'])->middleware('permission:manage_users')->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->middleware(['permission:manage_users', 'day.open'])->name('staff.store');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->middleware(['permission:manage_users', 'day.open'])->name('staff.update');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->middleware(['permission:manage_users', 'day.open'])->name('staff.destroy');



    Route::get('/{type}/ledger/{id}', [LedgerController::class, 'show'])
        ->middleware('permission:manage_ledgers')
        ->where('type', 'suppliers|karigars|customers') // Limit to valid types
        ->name('ledger.show');

    Route::post('/ledger/store-entry', [LedgerController::class, 'storeEntry'])
        ->middleware(['permission:manage_ledgers', 'day.open'])
        ->name('ledger.store-entry');

    Route::patch('/ledger/entry/{category}/{id}', [LedgerController::class, 'updateEntry'])
        ->middleware(['permission:manage_ledgers', 'day.open'])
        ->where('category', 'cash|metal')
        ->name('ledger.update-entry');


    // --- suppliers ---
    // Standard Resource Routes (Index, Store, etc.)
    Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:create_order|manage_orders')->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->middleware(['permission:create_order|manage_orders', 'day.open'])->name('orders.store');

    // Custom Workflow Actions
    Route::put('orders/item/{orderItem}', [OrderController::class, 'updateItem'])->middleware(['permission:create_order|manage_orders', 'day.open'])->name('orders.update-item');
    Route::post('orders/{orderItem}/assign', [OrderController::class, 'assign'])->middleware(['permission:create_order|manage_orders', 'day.open'])->name('orders.assign');
    Route::post('orders/{orderItem}/complete', [OrderController::class, 'complete'])->middleware(['permission:create_order|manage_orders', 'day.open'])->name('orders.complete');
    Route::post('orders/{orderItem}/transaction', [OrderController::class, 'addTransaction'])->middleware(['permission:create_order|manage_orders', 'day.open'])->name('orders.transaction');



    // 1. Route for CASH Entries (Money)
    Route::post('/transactions', [TransactionController::class, 'store'])
        ->middleware(['permission:manage_ledgers', 'day.open'])
        ->name('transactions.store');

    // 2. Route for METAL Entries (Gold)
    Route::post('/metal-transactions', [MetalTransactionController::class, 'store'])
        ->middleware(['permission:manage_ledgers', 'day.open'])
        ->name('metal-transactions.store');
});

require __DIR__ . '/settings.php';
