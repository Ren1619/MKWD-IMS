---
paths:
  - 'resources/js/pages/Inventory/Procurement/**,resources/js/components/app-sidebar.tsx,app/Http/Controllers/Inventory/ProcurementRequestController.php'
---

# Controllers Inventory

## Separate purchase requests and job orders under Procurement
Procurement is a sidebar group containing Purchase Requests and Job Orders, with separate server-scoped routes and pages for PR and JO records. Reject cross-kind creation and mutations; retain existing workflows until further procedure instructions arrive. Delete only unused unlinked intake/draft records without supporting attachments; soft deletion and terminal-record archival preserve evidence and history and allow restoration.
