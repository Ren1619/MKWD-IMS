---
paths:
  - 'resources/js/pages/Inventory/Assets/**'
---

# Pages Inventory Assets

## Limit PPE registration extras to fields with another workflow consumer
WI-007 cleanup removes Manufacturer and Disbursement Voucher Number inputs from PPE registration only. Keep these inputs for semi-expendable records and preserve existing database values. Retain non-WI fields needed by property/accountability reports, receiving/procurement, depreciation/accounting, and the vehicle API; do not drop shared columns or historical evidence.
