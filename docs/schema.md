# Schema

## Overview
- Database stores inventory, sales, ledgers, production, CRM, and attendance.
- Source of truth is MySQL tables.
- All stock and financial records must remain traceable.

## Key Rules
- Use foreign keys for ownership where possible.
- Keep one row per sold invoice line.
- Keep one row per stock item unless silver piece stock is quantity-based.
- Store percentages as decimal values.
- Store weights in grams.
- Store money as decimal.

## Data Structure
- `business_settings`: store name, address, phone, email, website, logo.
- `categories`: product groups; `metal_type = GOLD|SILVER`.
- `purities`: gold purity options.
- `products`: gold items; barcode `G00001`.
- `silver_products`: silver items; barcode `S00001`.
- `customers`: customer profile, DOB, anniversary, membership ID.
- `suppliers`: supplier profile.
- `karigars`: production worker/vendor profile.
- `orders`: custom order header.
- `order_items`: custom order lines.
- `invoices`: invoice header, customer, totals, payment state.
- `invoice_items`: invoice lines.
- `invoice_drafts`: server-side invoice drafts.
- `transactions`: cash ledger.
- `metal_transactions`: metal ledger.
- `vaults`: current balances.
- `vault_movements`: vault audit trail.
- `daily_registers`: opening/closing balances.
- `daily_rates`: gold/silver daily rates.
- `expenses`: expense records.
- `mortgages`: girvi records.
- `customer_gold_schemes`: scheme accounts.
- `gold_scheme_installments`: scheme payments.
- `staff`: HR profile.
- `staff_attendances`: daily attendance.
- `staff_presence_events`: in/out/break movements.
- `attendance_reasons`: configurable out reasons.
- `verification_tags`: public verification tokens.

## Flow
- Master data created first.
- Stock uses category, supplier, purity.
- Invoice uses customer and stock/order item.
- Invoice item links to product, silver product, or order item.
- Ledger and vault movements follow invoice/payment actions.
- Daily register snapshots vault at open/close.

## Constraints
- `products.barcode` must remain unique.
- `silver_products.barcode` must remain unique.
- `verification_tags.token` must remain unique.
- `users.attendance_card_uid` must remain unique.
- `invoice_items.id` is required for tag linking.
- Avoid nullable links where business ownership is mandatory.
