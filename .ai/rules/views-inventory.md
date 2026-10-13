---
paths:
  - 'app/Services/ProcurementDeliveryService.php,resources/js/pages/Inventory/Deliveries/**,resources/views/inventory/{inspection-evaluation,supplier-return-notice}.blade.php'
---

# Views Inventory

## Evaluation and supplier-return forms preserve signed source facts
Joint inspection records an IER number, physical evaluation/reference dates, site, per-line non-conformance checklist, findings/comments and frozen evaluator/Inspectorate signatories with signed evidence. Rejected quantities require checklist reasons; inspection does not post stock. Supplier handover requires a separately recorded four-signatory notice (Supply Officer, End User, Procurement Head, Inspectorate Chair), its purpose/reason/action/date, and unchanged signed source facts. Printed IER/authorized supplier-return notices use immutable action snapshots, retain fully rejected and legacy cases, and label missing legacy evidence instead of inventing approval.
