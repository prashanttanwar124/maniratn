# Billing Rules

## Overview
- Billing converts stock/order items into invoice records.
- Backend is the billing authority.
- Frontend totals must match backend formulas.

## Key Rules
- Use submitted row rate for each item.
- Validate stock live before final invoice.
- Sold gold stock cannot be billed again.
- Sold silver stock cannot be billed again.
- Silver piece quantity cannot exceed available quantity.
- Order item must be `READY`.
- Final invoice removes linked draft.
- Void invoice by reversing stock, ledger, and vault effects.

## Data Structure
- `invoices`: totals, customer, payment, status.
- `invoice_items`: line item, rate, making, final price.
- `products`: gold stock source.
- `silver_products`: silver stock source.
- `order_items`: custom order source.
- `transactions`: payment/customer ledger.
- `metal_transactions`: metal movement.
- `vault_movements`: vault audit.

## Flow
- Step 1: Select customer.
- Step 2: Add stock/order rows.
- Step 3: Enter row rates and making.
- Step 4: Validate draft/stock state.
- Step 5: Calculate subtotal.
- Step 6: Apply discount.
- Step 7: Apply GST if enabled.
- Step 8: Save invoice.
- Step 9: Mark stock/order delivered.
- Step 10: Record ledger/vault effects.

## Formulas
- **Gold Product Making Amount = Weight × Rate × Making% ÷ 100**
- **Gold Product Line Total = Weight × Rate + Gold Product Making Amount**
- **Silver Weight Line Total = Weight × (Rate + Making Per Gram)**
- **Silver Piece Line Total = Quantity × Piece Price + Weight × Making Per Gram**
- **Custom Order Line Total = Weight × (Rate + Making Per Gram)**
- **Subtotal = Sum(Line Totals)**
- **Percentage Discount = Subtotal × Discount% ÷ 100**
- **Taxable Total = Subtotal - Discount**
- **GST = Taxable Total × GST% ÷ 100**
- **Grand Total = Taxable Total + GST**
- **Balance Due = Grand Total - Paid Amount**

## Constraints
- Gold product making is percentage.
- Silver product making is per gram.
- Custom order making is per gram.
- Existing old gold making values above 100 must be corrected.
- Do not trust frontend `final_price` blindly.
- Recalculate totals server-side.
- Do not delete `invoice_items` on void.
