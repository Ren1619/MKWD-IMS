---
paths:
  - 'app/Http/Controllers/Inventory/InventoryAssetController.php,resources/js/pages/Inventory/Assets/**,resources/js/components/app-sidebar.tsx,routes/inventory.php'
---

# Js Components

## Keep PPE and semi-expendable property in separate modules
Expose PPE and semi-expendable property through separate routes and sidebar entries. The PPE module must only list/register assets at or above PHP 50,000; the semi-expendable module must only list/register assets below PHP 50,000. Reuse the shared durable-property lifecycle and accountability implementation while preserving PAR/PPE/depreciation versus ICS/no-depreciation rules.
