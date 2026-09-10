---
paths:
  - 'app/Integrations/Hris/**'
---

# Hris

## Use the HRIS inventory employee contract
The inventory employee feed uses employee_id as the stable synchronization key, never id (which may identify a position). Fetch all cursor pages through meta.next_cursor before replacing/deactivating cached references. Employee names use name_extension; preserve position and organization details in reference metadata.
