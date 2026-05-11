# Project Summary

## Overview
- Jewellery POS/ERP for store operations.
- Core areas: inventory, billing, orders, CRM, ledger, vault, attendance.
- All money, stock, and metal changes must be auditable.

## Key Rules
- Keep POS routes private.
- Use public routes only for customer-facing website features.
- Never expose raw internal models to public users.
- Keep generated files out of Git.
- Use India timezone for store operations.
- Require shop day open for business mutations.
- Never delete financial history.
- Void by reversal, not by erasing.

## Data Structure
- `users`: login, roles, permissions, attendance access.
- `staff`: employee profile, salary, designation.
- `customers`: customer identity and CRM fields.
- `products`: gold stock.
- `silver_products`: silver stock.
- `invoices`: sale headers.
- `invoice_items`: sale lines.
- `orders` / `order_items`: custom production.
- `transactions`: customer/supplier/karigar ledger.
- `metal_transactions`: gold/silver movement ledger.
- `vaults`: current cash/metal balances.
- `daily_registers`: day open/close snapshots.
- `verification_tags`: NFC/QR authenticity tags.

## Flow
- Open day.
- Create/update business records.
- Bill only valid stock/order items.
- Record ledger, vault, and stock movement.
- Close day with counted balances.
- Preserve audit trail.

## Constraints
- No billing when day is closed.
- No deleting sold stock.
- No editing sold stock weight.
- No public POS links.
- No direct customer browser calls to private POS APIs.
- No AI direct-save for high-value records without confirmation.
