---
paths:
  - 'app/Services/InventoryMonthlyReport*.php,resources/js/pages/Inventory/MonthlyReports/**'
---

# Monthly Reports

## Monthly packages support individual report selection without evidence text inputs
The user removed reconciliation-evidence and correction-reason inputs for now; neither may be required to prepare or revise a package. Retain automatic source readiness and sign-off checks. Persist individual report selection in snapshot payload.selected_report and reuse it for refresh, staleness checks and correction links; legacy snapshots without it retain all reports.

## Stock Master zero options mean monthly transaction inclusion
The user's definition supersedes closing-balance filtering: With zero includes every eligible item regardless of monthly activity; Without zero includes only items with receipts, issues, returns or approved adjustments in the month, even if closing balance is zero. Use transaction presence rather than net movement or closing quantity. Show radio options for Stock Master and All reports.
