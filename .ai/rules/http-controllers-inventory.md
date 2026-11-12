---
paths:
  - 'resources/js/pages/Inventory/Returns/**,app/Http/Controllers/Inventory/InventoryReturnController.php'
---

# Http Controllers Inventory

## Prepare RMS stays in a protected dialog
The returns create route renders Returns/Index with preparation props; PrepareRmsDialog retains form state during source searches and pagination. Disable outside dismissal, cancel Escape close requests, and omit the close icon. Only Cancel followed by confirmation leaves preparation; successful dialog submissions return back and keep the dialog open, with saved RMS records preserved when closing.
