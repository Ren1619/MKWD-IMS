---
paths:
  - 'resources/js/pages/Inventory/Procurement/**'
---

# Procurement

## Select existing procurement items from an aligned checklist
In procurement item entry, existing catalog items must be presented as a visible checkbox list with On hand and Suggested reorder aligned in each row. Reserve the Add item/manual fields for the Entirely new catalog item mode.

## Allow mixed catalog lines on one PR
Keep the existing-item checklist available while new catalog-item fields are open. A single procurement request may contain both existing inventory lines and entirely new catalog lines; toggling new-item entry must preserve existing selections.

## Collapse new procurement item editors independently
Place Add item after the existing catalog checklist. New catalog-item editors open when added and can be independently collapsed; their collapsed summaries should identify the entered item by name.

## Place collapsible new-item editors after catalog selection
Render the Add item action below the existing catalog checklist. Each newly added catalog item opens by default, may be independently collapsed, and uses its entered name in the collapsed summary.
