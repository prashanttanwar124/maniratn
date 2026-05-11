# AGENTS

## Overview
- Use this file as the first project guide.
- Follow docs before changing business logic.
- Keep answers and patches small.

## Key Rules
- Read relevant docs before edits.
- Do not change billing formulas without checking `docs/billing-rules.md`.
- Do not change day open/close logic without checking `docs/day-closing.md`.
- Do not change order production logic without checking `docs/production-flow.md`.
- Do not change schema assumptions without checking `docs/schema.md`.
- Do not change layout/UI direction without checking `docs/ui-guidelines.md`.
- Do not expose POS internals to public routes.
- Do not delete audit or financial history.
- Do not commit generated cache files.

## Data Structure
- Project summary: `docs/project-summary.md`
- Schema rules: `docs/schema.md`
- Day closing rules: `docs/day-closing.md`
- Production rules: `docs/production-flow.md`
- Billing rules: `docs/billing-rules.md`
- UI rules: `docs/ui-guidelines.md`

## Flow
- Step 1: Identify touched domain.
- Step 2: Read matching doc file.
- Step 3: Inspect current code.
- Step 4: Make smallest safe change.
- Step 5: Run focused checks.
- Step 6: Summarize result and risk.

## Constraints
- Keep docs concise.
- Keep business rules centralized in docs.
- Keep user changes intact.
- Avoid destructive Git commands.
- Ask before broad refactors.
