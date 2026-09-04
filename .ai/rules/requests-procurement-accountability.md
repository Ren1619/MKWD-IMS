---
paths:
  - 'resources/js/pages/Inventory/{Requests,Procurement,Accountability}/**'
---

# Requests Procurement Accountability

## Keep workflow controls record-scoped and state-aware
Render only transitions allowed by the backend state machine. Collect remarks, attestations, and action-specific evidence in a per-record confirmation dialog; never reuse page-global decision inputs. Preserve mobile card views, labelled fields, queue filters, and pagination on these workflow screens.
