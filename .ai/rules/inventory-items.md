---
paths:
  - 'resources/js/pages/Inventory/Items/**'
---

# Inventory Items

## Gate category filters by parent selection
Keep the inventory filters progressive: disable Class until Major is selected and disable Series until Class is selected. Child dropdowns should only contain the selected parent's children and display the child name without repeating the parent label.

## Control hierarchical category filter values
Bind the Major, Class, and Series filter FormSelect values to the corresponding Inertia filter props. Do not use defaultValue for these preserved-state dependent filters; stale uncontrolled IDs can render as raw numbers after parent options change.
