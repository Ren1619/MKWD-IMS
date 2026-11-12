---
paths:
  - 'resources/js/pages/Inventory/{Requests,Returns}/**'
---

# Requests Returns

## Keep RMS preparation mounted while its batches load
ReturnSlipDialog must always render PrepareRmsDialog, seeded with the selected request and empty source data. Show the skeleton, loading errors, and retries only in the materials section; never replace the form with a second loading dialog. Preserve entered fields and cancellation confirmation across responses, keep the request scope in every load and submit, and avoid overwriting manually edited departments.
