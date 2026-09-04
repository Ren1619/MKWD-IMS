---
paths:
  - 'app/Http/Controllers/Inventory/SupplyRequestController.php,resources/js/pages/Inventory/Requests/Index.tsx'
---

# Requests

## Prefill supply request cost from weighted inventory value
For existing stocked items, prefill the editable estimated unit cost using the weighted-average cost of remaining receipt batches: sum(quantity_remaining × unit_cost) / sum(quantity_remaining). Leave it blank when no valued quantity remains.
