---
paths:
  - 'app/Services/{SupplyRequestWorkflow,SupplyShortageProcurementService,ProcurementRequestWorkflow}.php,resources/js/pages/Inventory/{Requests,Procurement}/**'
---

# Requests Procurement

## Link supply shortages through procurement acceptance
Reviewing an approved supply request must maintain one active procurement request for its unreserved shortages. Accepted procurement must post stock, link newly created catalog items back to their supply lines, and automatically reassess reservations so the originating request can proceed to release. Keep planning, PO, delivery, IAR, stock receipt, and workflow actions in the same auditable chain.
