---
paths:
  - 'app/Models/InventoryAsset.php,app/Http/Controllers/Inventory/PropertyTagController.php,resources/views/inventory/property-tag.blade.php'
---

# Inventory Views Inventory

## Property tags use class-specific documents and prescribed colors
WI-006 semi-expendable tags are yellow and show the latest ICS; WI-007 PPE tags are green and show the latest PAR. Keep the permanent property_tag_uuid and public-scan privacy boundary unchanged. Scope document_type inside the ofMany aggregation so a newer document of another type cannot hide the correct PAR/ICS; display document status to distinguish returned/closed records from active accountability.
