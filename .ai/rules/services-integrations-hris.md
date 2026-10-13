---
paths:
  - 'app/{Services,Integrations/Hris}/**'
---

# Services Integrations Hris

## Treat HRIS roles as the login role source
On successful HRIS authentication, synchronize the IMS UserRole from the roles array. Map super_admin and inventory_manager by exact name; HRIS-only, unknown, or empty role sets receive Employee access. Missing or malformed roles make the HRIS response unavailable, and an unlinked super_admin must carry the super_admin role.
