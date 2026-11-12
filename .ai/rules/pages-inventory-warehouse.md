---
paths:
  - 'resources/js/pages/Inventory/Warehouse/**'
---

# Pages Inventory Warehouse

## Open batch storage in a protected dialog
Storage & Batches rows open the reusable batch storage dialog without navigating away from the filtered list. Keep placement confirmation, history, and label printing available. Only Cancel followed by confirmation closes the dialog; block Escape, outside dismissal, and the corner close icon. Keep entered placement values when the user chooses Keep editing, and refresh batch details after a successful save.

## Validate stockroom fields on the server
Use Laravel Form Request validation and inline server-returned field errors for stockroom forms. Disable browser constraint-validation prompts with noValidate; do not add client-side field validation or disable submission based on field validity. Only disable submit while a request is processing.
