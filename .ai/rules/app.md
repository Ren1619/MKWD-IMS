---
paths:
  - 'app/**'
---

# App

## Keep HRIS behind the API reference boundary
IMS owns inventory and asset transactions locally. Employee and organization data must be consumed through App\Contracts\HrisReferenceSource and cached in hris_references. Do not add direct relationships to HRIS tables or a shared HRIS database connection.

## Preserve the IMS role access matrix
Use UserRole as the single role source. Super admins manage users, audit logs, and inventory; inventory managers manage inventory but not administration; employees have read-only inventory access. Protect every inventory mutation with the manage-inventory ability and keep new accounts least-privilege employees.
