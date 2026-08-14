# Project Context

Use this file as the fast-start context before writing code in this project.
Read `AGENTS.md` first, then this file, then the domain doc that matches the change.

## What This Project Is
- Jewellery POS/ERP for daily store operations.
- Private staff-facing app built with Laravel 12, Inertia.js, Vue 3, PrimeVue, and MySQL.
- Main domains: inventory, billing, production orders, CRM, finance, attendance, verification tags, and limited website-facing APIs.

## Tech Stack
- Backend: PHP 8.2, Laravel 12
- Frontend: Vue 3, Inertia.js, PrimeVue, Tailwind CSS 4, Vite
- Auth: Laravel Fortify
- Permissions: Spatie Laravel Permission
- Testing: Pest
- PDF/print: DomPDF, TCPDF, Picqer barcode generator

## First Docs To Check
- `AGENTS.md`
- `docs/project-summary.md`
- `docs/schema.md`

Domain-specific docs:
- Billing: `docs/billing-rules.md`
- Day open/close: `docs/day-closing.md`
- Production/orders: `docs/production-flow.md`
- UI/layout: `docs/ui-guidelines.md`

## Non-Negotiable Rules
- Do not change billing formulas without checking `docs/billing-rules.md`.
- Do not change day open/close logic without checking `docs/day-closing.md`.
- Do not change order production logic without checking `docs/production-flow.md`.
- Do not change schema assumptions without checking `docs/schema.md`.
- Do not expose POS/private staff data on public routes.
- Do not delete audit, ledger, invoice, vault, or historical stock records.
- Do not use destructive git commands on user work.
- Do not commit generated cache files like `storage/fonts/`.

## Core Business Rules
- Business mutations generally require the shop day to be open through `day.open` middleware.
- Gold inventory is stored in `products`.
- Silver inventory is stored in `silver_products`.
- Gold barcode format: `G00001`
- Silver barcode format: `S00001`
- Sold stock must not be sold again.
- Invoices are reversed by cancellation/void flow, not erased.
- Orders move through controlled statuses: `NEW`, `ASSIGNED`, `READY`, `DELIVERED`.
- Public website product feed must only expose website-safe fields.

## Current Main Modules

### Dashboard
- Admin and staff dashboards are different.
- Day open/close, rate updates, vault snapshots, sales metrics, reminders, and activity are centered here.
- Main controller: `app/Http/Controllers/DashboardController.php`

### Gold Inventory
- Page: `resources/js/pages/products/Index.vue`
- Controller: `app/Http/Controllers/ProductController.php`
- Features:
  - filters
  - barcode printing
  - quick scan
  - bulk update
  - duplicate product
  - product history drawer
  - stable pagination
  - batch create

### Silver Inventory
- Page: `resources/js/pages/silver-products/Index.vue`
- Controller: `app/Http/Controllers/SilverProductController.php`
- Features mirror gold inventory closely:
  - filters
  - barcode printing
  - quick scan
  - bulk update
  - duplicate product
  - history drawer
  - stable pagination

### Gold Stock Count
- Page: `resources/js/pages/gold-stock-count/Index.vue`
- Controller: `app/Http/Controllers/GoldStockCountController.php`
- Models:
  - `app/Models/GoldStockCountSession.php`
  - `app/Models/GoldStockCountEntry.php`
- Purpose:
  - nightly or closing physical stock scan for gold items
  - count unsold gold items by barcode
  - track counted vs missing
- Permission: `manage_stock_counts`

### Website Product Catalog
- Internal management page: `resources/js/pages/website-products/Index.vue`
- Controller: `app/Http/Controllers/Website/ProductCatalogController.php`
- Public API controller: `app/Http/Controllers/Website/WebsiteApiController.php`
- Public endpoint:
  - `GET /api/website/products`
- Rule:
  - only unsold products with `is_visible_on_website = true` are exposed

### Billing / Invoices
- Pages:
  - `resources/js/pages/invoices/Create.vue`
  - `resources/js/pages/invoices/Index.vue`
- Controller: `app/Http/Controllers/InvoiceController.php`
- Important:
  - gold, silver, and ready order items can feed invoices
  - totals must be recalculated server-side
  - cancelled invoice flow must reverse stock and ledger impact

### Orders / Production
- Page: `resources/js/pages/orders/Index.vue`
- Controller: `app/Http/Controllers/OrderController.php`
- Supports:
  - customer orders
  - assignment to karigar/supplier
  - production status tracking
  - metal issue/receipt audit

### Verification Tags
- Page: `resources/js/pages/verification-tags/Index.vue`
- Controller: `app/Http/Controllers/VerificationTagController.php`
- Purpose:
  - create and manage authenticity tags tied to sold items
  - track written/locked/deactivated state

### CRM / People
- Customers: `CustomerController`
- Suppliers: `SupplierController`
- Karigars: `KarigarController`
- Staff: `StaffController`
- Users and roles: `UserManagementController`

### Attendance
- Attendance admin page: `resources/js/pages/attendance/Index.vue`
- Attendance terminal page: `resources/js/pages/attendance/Terminal.vue`
- Controllers:
  - `AttendanceController`
  - `AttendanceTerminalController`
- Uses passcode/card based attendance support.

### Finance
- Expenses: `ExpenseController`
- Ledger: `LedgerController`
- Mortgages: `MortgageController`
- Gold schemes: `GoldSchemeController`
- Vault and daily register logic is tied closely to dashboard/day closing flow.

## Permissions Model
- Seeder registry: `database/seeders/RolePermissionRegistry.php`
- Seeder reset/rebuild: `database/seeders/RolesAndPermissionsSeeder.php`
- Important:
  - seeding roles/permissions deletes current managed roles, permissions, and pivot mappings, then recreates them from the registry
  - if a new page needs a permission, add it to the registry and reseed

Current seeded permissions:
- `manage_users`
- `manage_roles_permissions`
- `view_dashboard`
- `manage_daily_rates`
- `view_vault`
- `manage_vault`
- `manage_products`
- `manage_stock_counts`
- `manage_categories`
- `manage_customers`
- `manage_suppliers`
- `create_order`
- `manage_orders`
- `settle_karigar`
- `manage_invoices`
- `manage_gold_schemes`
- `manage_ledgers`
- `manage_expenses`
- `manage_mortgages`
- `view_all_sales`

## Important Routes

Public routes:
- `/attendance-terminal`
- `/api/website/products`
- `/api/inventory/{barcode}`

Protected routes of note:
- `/dashboard`
- `/products`
- `/silver-products`
- `/gold-stock-count`
- `/website-products`
- `/invoices`
- `/orders`
- `/verification-tags`
- `/customers`
- `/suppliers`
- `/staff`
- `/attendance`
- `/users`

## Shared UI Pattern
- Layout: Sakai-based layout with `AppMenu` sidebar
- Inertia shared permissions come from `app/Http/Middleware/HandleInertiaRequests.php`
- Menu items should be hidden with `visible: Boolean(can.some_permission)`
- Routes should always be protected server-side with matching `permission:*` middleware

## Common Change Workflow
1. Identify touched domain.
2. Read matching doc.
3. Inspect controller, page, model, and route.
4. Make smallest safe change.
5. Run focused tests or syntax checks.
6. Summarize any risk or follow-up step.

## Common Commands
```bash
php artisan test
php artisan test tests/Feature/SomeFocusedTest.php
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php -l app/Http/Controllers/SomeController.php
npm run dev
npm run build
```

## When Adding A New Page
- Add the route with correct permission middleware.
- Add menu visibility check using `auth.can`.
- Add or reuse a permission in `RolePermissionRegistry.php`.
- If it needs its own access control, prefer a dedicated permission.
- Add focused tests when business logic changes.
- Keep public and private data separated.

## Known Sensitive Areas
- Invoice total calculations
- Stock sold/available transitions
- Daily register open/close flow
- Vault balances and movements
- Ledger and mortgage payments
- Order status transitions
- Public website/API exposure
- Role/permission seeding

## Practical Reminder
- If a change touches money, stock, metal, permissions, or public APIs, slow down and verify twice.
