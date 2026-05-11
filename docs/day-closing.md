# Day Closing

## Overview
- Day register controls store open/close state.
- Business writes need an open day.
- Closing stores counted cash, gold, and silver.

## Key Rules
- Open day before billing, expenses, ledgers, orders, inventory mutations.
- Close day after physical cash/gold/silver count.
- Record mismatch reason when counted opening differs from expected opening.
- Record reopen reason when reopening an existing day.
- Do not bypass `day.open` middleware for business mutations.

## Data Structure
- `daily_registers.date`
- `daily_registers.opened_at`
- `daily_registers.closed_at`
- `daily_registers.opening_cash`
- `daily_registers.opening_gold`
- `daily_registers.opening_silver`
- `daily_registers.closing_cash`
- `daily_registers.closing_gold`
- `daily_registers.closing_silver`
- `daily_registers.expected_opening_cash`
- `daily_registers.expected_opening_gold`
- `daily_registers.expected_opening_silver`
- `daily_registers.mismatch_reason`
- `vaults.cash_balance`
- `vaults.gold_balance`
- `vaults.silver_balance`

## Flow
- Step 1: Check last closed register.
- Step 2: Show expected opening cash/gold/silver.
- Step 3: User enters counted opening cash/gold/silver.
- Step 4: Require reason if mismatch exists.
- Step 5: Create/open register.
- Step 6: Allow day business operations.
- Step 7: User enters counted closing cash/gold/silver.
- Step 8: Save closing snapshot.
- Step 9: Mark day closed.

## Formulas
- **Cash Difference = Counted Cash - Expected Cash**
- **Gold Difference = Counted Gold - Expected Gold**
- **Silver Difference = Counted Silver - Expected Silver**
- **Expected Opening = Previous Closed Balance**

## Constraints
- One active open register at a time.
- Closing values are physical counts.
- First-time setup initializes vault balances.
- Close-day errors must show inside modal.
- Do not delete daily registers.
- Use India date for register day.
