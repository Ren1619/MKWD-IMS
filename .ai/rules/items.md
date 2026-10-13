---
paths:
  - 'app/Http/{Controllers,Requests}/Inventory/InventoryItem*.php,resources/js/pages/Inventory/Items/**'
---

# Items

## Preserve hierarchical inventory category filters
Consumable inventory filtering supports Major → Class → Series category levels. Apply all selected levels server-side through InventoryItem.seriesCategory.classCategory, keep choices alphabetized, and clear class/series when major changes and series when class changes so stale child filters cannot conflict.
