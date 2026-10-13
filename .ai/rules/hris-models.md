---
paths:
  - 'app/{Actions/Integrations,Integrations/Hris,Models}/**'
---

# Hris Models

## Treat HRIS reference data as a complete catalog snapshot
Sync employees from /api/v1/inventory/employees and departments, divisions, units, and role definitions from /api/v1/inventory/reference-data before mutating the local cache. Preserve department→division and division→unit parent IDs, deactivate missing records per type, and treat catalog roles as lookup data only; per-user IMS authorization still comes from the authenticated user's HRIS roles mapped to UserRole.
