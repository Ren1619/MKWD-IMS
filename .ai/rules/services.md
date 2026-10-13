---
paths:
  - 'app/Services/Inventory*{Count,Stock}*.php'
  - app/Services/InventoryReturnService.php
  - 'app/Services/Procurement*{Delivery,Request}*.php'
  - 'app/Services/InventoryMonthlyReport*.php'
  - app/Services/InventoryWarehouseService.php
  - app/Services/InventoryPropertyCardService.php
  - app/Services/InventoryTransferReportService.php
  - app/Services/InventoryPropertyTransferService.php
  - app/Services/InventoryAssetService.php
---

# Services

## Physical counts control stock cut-off and approved adjustments
Consumable physical counts freeze all normal stock movements through InventoryStockGuard; count services and stock services acquire the shared control lock before item locks. Emergency issues explicitly distinguish before/after counting; segregated receipts stay outside the count and cannot be issued during it. Only independent verification and approval post variances; keep count observations/actions immutable, preserve batch costs, and invalidate affected reservations for review. RPCI must use approved frozen observations, never substitute current book quantity for a physical count.

## Post RMS returns independently from liquidation references
Consumable RMS/MRS intake and inspection do not alter warehouse stock or issuance/liquidation totals. Only independent approval posts accepted quantities under InventoryStockGuard, restoring the linked issue allocation's original cost and expiry in a new batch. Submitted, verified, and approved commitments share the source quantity cap; rejected/cancelled slips release it. Legacy issues require an evidence reference and valuation basis. Keep physical return date separate from the approval posting date, preserve immutable decisions/postings, and exclude returns from supplier receipt reports.

## Keep physical shipments separate from acceptance postings
Use ProcurementDelivery for new PO shipment intake, partial inspection, supplier rejection/replacement, signed acceptance, and endorsement. Pending and accepted quantities share the ordered-quantity cap; rejected or cancelled commitments release it. Receipt and inspection never post usable stock; independent acceptance posts only conforming quantities and preserves per-shipment cost/expiry, classifies property by actual cost, and reassesses linked supply shortages. Require passed calibration or direct-use endorsement evidence; linked replenishment must enter stock. Keep physical receipt separate from posting date, retain immutable decisions, and reject legacy receipt/acceptance actions once a PO has shipment records. Acquire InventoryStockGuard before procurement/supply parent and item locks.

## Monthly packages preserve historical FIFO balances and signed snapshots
WI-012 packages snapshot ended months and preserve every refresh/revision and assigned signatory evidence. Reconstruct month-end quantities and values from dated FIFO receipts, allocations, returns and approved count adjustments; current on-hand must not replace historical balances. Require refreshed sign-off after source changes; approved packages use correction revisions. Classification/zero criteria apply to Stock Master; fund cluster is a reporting label, not a stock segregation filter. Monthly approval/transmittal never changes stock or posts an accounting JEV.

## Warehouse placement confirms the entire remaining receipt batch without posting stock
WI-001 put-away records are immutable InventoryBatchPlacement entries per receipt/return batch; latest placement identifies the whole remaining batch, and moves/corrections append history. Require observed remaining quantity to equal the locked batch balance plus source-document evidence and attestation. Use InventoryStockGuard before item/batch locks and reject changes during physical counts. Placement never edits receipt provenance, batch valuation, stock quantity, or release authorization.

## Property cards replay evidence rather than current custodian snapshots
WI-010/WI-011 property cards replay immutable events by date/ID, retain archived and derecognized property history, and use frozen PAR/ICS recipient data and event property snapshots. Preserve pre-period balances and show last recorded physical counts without inventing observations. Group related administrative events by source document so custody quantities are not counted twice. Semi-expendable issuance/reissue permanently expends accounting inventory; return/transfer must not re-recognize it. Explicitly label legacy metadata without transaction snapshots.

## Transfer registers preserve movement evidence and do not attest approval
PTR and ITR registers use only Transfer events, retain archived assets, and resolve outgoing/incoming officers from linked frozen PAR/ICS documents existing at the event date/posting time. Initial assignments are not transfers. Use recorded snapshots for historical metadata, filter by either recorded officer, and label legacy gaps. Existing assignment events do not prove transfer approval or joint handover; never print those controls as completed.

## Internal PTR decisions precede custody and location changes
Internal assigned-property transfers freeze the source PAR/ICS, property details, and receiving officer. Lock the asset before the PTR, allow one open request per asset, and require requestor/verifier/approver separation. Verify source and recipient snapshots at every positive decision; changed or archived sources require cancellation and a corrected request. Only approved completion with joint inspection and signed release/receipt references posts movement and superseding PAR/ICS atomically. Physical handover date is evidence; ledger posting uses completion date. Agency transfers and donations are not internal reassignments.

## Public assignment and editing cannot bypass internal PTR controls
The assignment endpoint is for unassigned property only; enforce initialAssignmentOnly against the locked asset. Reassignment uses InventoryPropertyTransferService with approved handover evidence. Assigned location changes also require PTR: reject generic Transfer events and location edits under the asset lock. Keep trusted legacy fixture/import history separate from the public assignment path.

## Agency PTR transfers require ownership and accounting evidence
Agency transfers and donations use a frozen external agency/officer snapshot and no HRIS recipient ID. Independent approval requires transfer authority and Accounting Unit clearance; completion requires permanent ownership confirmation, signed agency acceptance, and an accounting posting/confirmation reference. Close the old PAR/ICS and assignments, mark lifecycle transferred_out, and never create an internal recipient PAR/ICS. PPE records a linked Derecognition using the existing calculator; already-expensed semi-expendable property stays expensed. IMS records evidence and does not create a financial JEV.

## Permanent agency transfers leave local balances without becoming disposals
A Transfer event with server-owned transfer_evidence.external_ownership_transfer=true removes local card and inventory quantities, records physical quantity moved even if the old PAR/ICS already appeared in issuance, and shows the frozen receiving agency. Do not count it as disposal or create a replacement internal PAR/ICS. A following PPE Derecognition must not remove the quantity twice. Current physical registers exclude these events as of their posting date; earlier cards and source documents remain visible.
