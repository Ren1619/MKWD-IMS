---
paths:
  - 'app/{Models/InventoryItem*,Services/InventoryStockService.php,Services/SupplyRequestWorkflow.php}'
---

# App Models

## Use FEFO for dated batches and distinguish issuable quantities from book stock
WI-001 rotation orders known expiries first (earliest expiry, receipt date, batch number), then undated FIFO batches. Release excludes expired batches as of both today and the issue date, and receipts dated after today or the issue date; future issues cannot be posted. Keep expired/future quantities and original batch costs in book inventory. Supply reservations use issuable quantity and release rechecks total outstanding quantities per item; ready requests may be reviewed again after expiry. Preserve count segregation and locks.
