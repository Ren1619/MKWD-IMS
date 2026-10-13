---
paths:
  - 'app/Services/*Liquidation*.php,app/Models/DepartmentLiquidation.php,resources/js/pages/Inventory/Requests/**'
---

# Pages Inventory Requests

## Department liquidation supersedes per-slip liquidation
IS Liquidation consolidates all actual material issues to a department as of the reporting date, independent of any single ISF or issuance slip. It has its own MM-DD-NNN document sequence. Utilization is cumulative, must not fall below prior approved totals, and never changes warehouse stock. Keep old per-slip records as legacy evidence; the earlier rule requiring liquidation to reference one slip is superseded.

## IS liquidation links to one ISF and keeps returns separate
The corrected procedure supersedes the department-wide consolidation rule: every new IS liquidation links to exactly one Issuance Request Form and includes only actual releases against its lines. Balance is always issued minus utilized. An optional return-slip number/date is supporting evidence; do not subtract it from liquidation balance or post a stock return when saving liquidation. Preserve unlinked historical department records without inventing ISF links.
