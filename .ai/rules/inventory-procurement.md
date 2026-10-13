---
paths:
  - 'app/Services/ProcurementRequestWorkflow.php,app/Models/ProcurementRequestLine.php,resources/js/pages/Inventory/Procurement/**'
---

# Inventory Procurement

## Procurement lines branch at acceptance
Keep one PR/PO/delivery/IAR workflow, but classify each line as consumable or property. Acceptance posts consumables to InventoryItem stock and creates one InventoryAsset per received property unit through InventoryAssetLifecycleService. Semi-expendable versus PPE classification must derive from actual unit cost at delivery, not the estimate; property issuance later uses ICS or PAR.
