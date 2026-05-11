# UI Guidelines

## Overview
- UI must match Sakai/PrimeVue style.
- Prioritize speed, clarity, and low training cost.
- Avoid oversized custom components unless necessary.

## Key Rules
- Use existing layout, cards, tables, dialogs, drawers.
- Use PrimeVue `Paginator` for paginated tables.
- Keep controls near the action they affect.
- Show validation errors beside fields.
- Use `text-sm font-medium text-surface-700` for form labels.
- Keep destructive actions secondary and confirmed.
- Use clear labels over clever labels.
- Use India date/time formatting.
- Avoid hidden business state.
- Prefer one obvious primary action per section.

## Data Structure
- Shared layout: `SakaiLayout`.
- Header: `AppTopbar`.
- Sidebar: `AppMenu`.
- Global AI drawer: `AskAiDrawer`.
- Page content: cards, DataTable, Dialog, Drawer.

## Flow
- Step 1: Page header states purpose.
- Step 2: Summary cards show key state.
- Step 3: Main list/table shows records.
- Step 4: Actions sit in table/header.
- Step 5: Dialog/drawer handles create/edit.
- Step 6: Toast confirms result.
- Step 7: Inline errors show corrections.

## Constraints
- Do not use unrelated gradients.
- Do not over-round UI if theme is sharp.
- Do not place reset/cancel as top priority.
- Do not persist row selections unless user expects it.
- Do not show stale localStorage selections.
- Do not hide blocking errors in toast only.
- Keep mobile layout usable.
- Keep print/barcode actions explicit.
