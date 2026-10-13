---
paths:
  - 'app/{Services/InventoryReportService.php,Services/SupplyRequestWorkflow.php,Models/InventoryItemStockOut.php}'
---

# App Services

## Stock cards use exact issue-slip links and preserve legacy provenance
WI-009 stock cards include supplier/source or recipient, purpose, unit, reference, and running balances. Supply releases associate only newly created stock movements with their actual IssuanceSlip inside the existing stock-guard transaction; keep issuance_slip_id server-owned and never infer links for legacy releases. Stock-card and monthly issue registers prefer the linked IS number while preserving RIS separately where applicable. Use exact allocation costs and stable date/posting/ID ordering for ledger amounts and balances.
