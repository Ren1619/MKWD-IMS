---
paths:
  - app/Http/Controllers/Inventory/SupplyRequestController.php
---

# App Http Controllers Inventory

## Supply request actors retain record visibility
Supply-request list visibility includes the submitting user, the requested-for employee's linked account, and any user recorded as an action actor, regardless of the request's current status or their current head assignment. Keep these alternatives grouped under the same search/status/queue filters. Historical participation grants read access only; approval stages, jurisdiction, separation of duties and stock-management authorization still apply.
