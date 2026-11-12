---
paths:
  - 'app/{Events,Listeners,Providers}/**'
---

# Events Listeners Providers

## Broadcast only committed record invalidations
Realtime updates use RecordsChanged after commit, a queued unique-until-processing listener, and private RecordsInvalidated signals containing only scope. Never broadcast model contents, employee details or credentials on shared channels. Inventory is available to active authenticated users; administration is super-admin-only; account channels belong to their user. Existing HTTP controllers remain the authorization boundary for all refreshed data. Query-builder bulk changes must also save a tracked parent/action or dispatch RecordsChanged explicitly.
