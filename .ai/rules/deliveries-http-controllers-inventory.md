---
paths:
  - 'resources/js/pages/Inventory/Deliveries/**,app/Http/Controllers/Inventory/ProcurementDeliveryController.php'
---

# Deliveries Http Controllers Inventory

## Record shipment opens over the delivery list
The deliveries create route renders Deliveries/Index with shipmentPreparation props, including the selected purchase order and available lines. RecordShipmentDialog uses a fixed header/footer and scrolling body, with its footer submit button associated to record-shipment-form. Load preparation with the page response instead of a separate loading dialog. Cancel requires confirmation; preserve validation state and the existing successful redirect to the recorded shipment.
