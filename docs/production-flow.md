# Production Flow

## Overview
- Production handles custom customer orders.
- Items move from request to assignment to ready to delivered.
- Metal issue/return must be auditable.

## Key Rules
- Custom order items use statuses only.
- Assign only valid order items.
- Ready item requires finished weight.
- Invoice can bill only `READY` items.
- Delivered items must not return to production except via controlled reversal.

## Data Structure
- `orders`: customer order header.
- `order_items`: production line.
- `order_items.status`: `NEW|ASSIGNED|READY|DELIVERED`.
- `order_items.metal_type`: `GOLD|SILVER`.
- `order_items.finished_weight`: final billable weight.
- `order_items.assignee_type`: karigar/supplier.
- `order_items.assignee_id`: worker/vendor ID.
- `metal_transactions`: issued/received metal audit.

## Flow
- Step 1: Create order item.
- Step 2: Status = `NEW`.
- Step 3: Assign to karigar/supplier.
- Step 4: Status = `ASSIGNED`.
- Step 5: Issue metal if required.
- Step 6: Receive completed item.
- Step 7: Save finished weight.
- Step 8: Status = `READY`.
- Step 9: Invoice the ready item.
- Step 10: Status = `DELIVERED`.

## Constraints
- `NEW` item is not billable.
- `ASSIGNED` item is not billable.
- `READY` item is billable.
- `DELIVERED` item is historical.
- Finished weight must be greater than zero.
- Public order tracking must hide internal assignee/cost/metal ledger.
