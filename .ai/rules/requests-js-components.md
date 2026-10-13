---
paths:
  - 'app/Http/Controllers/Inventory/SupplyRequestController.php,resources/js/pages/Inventory/Requests/**,resources/js/components/responsibility-center-combobox.tsx'
---

# Requests Js Components

## Source responsibility centers from finance
Responsibility-center choices in supply requests must come from the planned finance-system API. Until that integration exists, keep the combobox capable of manual code entry and pass its option list into the page as a replaceable prop; do not hard-code RC values.
