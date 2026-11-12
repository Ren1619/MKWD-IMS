---
paths:
  - 'resources/js/pages/Inventory/{Requests,Returns}/**,app/{Services/InventoryReturnService.php,Http/Controllers/Inventory/InventoryReturnController.php,Http/Requests/Inventory/StoreInventoryReturnRequest.php}'
---

# Requests Inventory

## Supply-request Return Slip opens its scoped RMS dialog
ReturnSlipDialog loads the shared PrepareRmsDialog through the authorized returns create JSON response using useHttp and Wayfinder. Preserve the selected request during searches/pagination, prefill its issuing department, hide legacy source selection and all-request browsing, and send supply_request_id on submission. Server validation and InventoryReturnService reject legacy mode or allocations belonging to other requests when a request scope is supplied. Cancel requires confirmation; submission stays in the dialog without changing stock or liquidation totals.
