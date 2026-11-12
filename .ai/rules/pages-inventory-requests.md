---
paths:
  - 'app/Services/*Liquidation*.php,app/Models/DepartmentLiquidation.php,resources/js/pages/Inventory/Requests/**'
---

# Pages Inventory Requests

## Department liquidation supersedes per-slip liquidation
IS Liquidation consolidates all actual material issues to a department as of the reporting date, independent of any single ISF or issuance slip. It has its own MM-DD-NNN document sequence. Utilization is cumulative, must not fall below prior approved totals, and never changes warehouse stock. Keep old per-slip records as legacy evidence; the earlier rule requiring liquidation to reference one slip is superseded.

## IS liquidation links to one ISF and keeps returns separate
The corrected procedure supersedes the department-wide consolidation rule: every new IS liquidation links to exactly one Issuance Request Form and includes only actual releases against its lines. Balance is always issued minus utilized. An optional return-slip number/date is supporting evidence; do not subtract it from liquidation balance or post a stock return when saving liquidation. Preserve unlinked historical department records without inventing ISF links.

## RIS liquidation links an IS and requires its return slip reference
This supersedes the prior ISF-based liquidation rule. New DepartmentLiquidation records require issuance_slip_id, frozen is_no, and return_slip_no/date; leave direct supply_request_id/isf_no null. Resolve the request through IssuanceSlip; its independent ISF/IS daily counters and frozen ISF snapshot remain unchanged. Scope materials, prior utilization and pending uniqueness to the exact issuance slip using stock-out issuance_slip_id. Preserve historical request-linked documents without guessing links; prevent re-liquidating releases they cover. Returns are references and never reduce issued-minus-utilized balance or change stock.
