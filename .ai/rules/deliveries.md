---
paths:
  - 'app/Services/ProcurementDeliveryService.php,resources/js/pages/Inventory/Deliveries/**'
---

# Deliveries

## Postponement preserves receipt commitments and signed schedules
Inspection postponement appends immutable delivery actions with original/proposed schedules, reason, frozen HRIS signatories, signed authority evidence and supplier transmittal reference. Keep the shipment received and its quantity commitments intact; postponement does not accept or post stock. Repeated notices start from the prior proposed schedule, preserve every printable letter and enforce submission-key idempotency. Resumed inspection requires a supplier-availability/restored-team confirmation reference; actual correspondence and inspection remain staff actions.
