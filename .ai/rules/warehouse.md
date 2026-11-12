---
paths:
  - 'app/{Services/InventoryWarehouseService.php,Http/Controllers/Inventory/WarehouseRegistryController.php},resources/js/pages/Inventory/Warehouse/**'
---

# Warehouse

## Use the warehouse registry without rewriting placement evidence
New batch placements require a non-deleted registered warehouse_id; snapshot its name in the immutable warehouse field. Renaming a registry record never rewrites placement history; filter current placements by warehouse_id. Soft-delete registry entries only after no remaining batch has its latest placement there, and lock the warehouse row in placement and deletion transactions to serialize those operations.
