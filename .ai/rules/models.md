---
paths:
  - app/Models/InventoryCountEntry.php
---

# Models

## Preserve actual observations separately from cut-off reconciliation
Count entries store observed_quantity exactly as physically found, excluding segregated receipts. quantity is the reconciled count basis: observed_quantity plus after_count_issues_at_observation. Recounts after emergency withdrawals must compare that reconciled basis while retaining both the raw observation and movement adjustment. Never rewrite prior count entries.
