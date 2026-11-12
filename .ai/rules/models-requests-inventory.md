---
paths:
  - 'app/{Models/InventoryAsset.php,Http/Requests/Inventory/*InventoryAsset*.php,Services/*Property*Service.php}'
---

# Models Requests Inventory

## Use item_description for current property records
inventory_assets.name was renamed to item_description with a data-preserving reversible migration; registration/edit validation, frontend asset props, queries and new property creation use item_description. The separate description field remains supplementary details. Keep legacy name only as a nonserialized model compatibility accessor and in immutable historical document/snapshot or stable external API contracts. Transfer/disposal evidence must use historicalSnapshotAttributes() to preserve original field names and ordering for snapshot/hash comparisons; do not rewrite issued evidence.
