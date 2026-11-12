---
paths:
  - 'app/Models/Warehouse.php,app/Http/Requests/Inventory/SaveWarehouseRequest.php,resources/js/pages/Inventory/Warehouse/**'
---

# Inventory Warehouse

## Stockroom registration has no code
Stockrooms use their numeric id for relationships and expose only name, optional location/address (address field), and optional description. Name stays unique; code has been removed from the schema, forms, selectors, and registry search. Preserve stockroom IDs and immutable placement name snapshots.
