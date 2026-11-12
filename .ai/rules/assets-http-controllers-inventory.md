---
paths:
  - 'resources/js/pages/Inventory/Assets/**,app/Http/Controllers/Inventory/InventoryAssetController.php'
---

# Assets Http Controllers Inventory

## Keep only essential semi-expendable registry filters
The semi-expendable registry exposes only search, condition_status, accountability_status, ics_renewal, and active/archived records. Hide lifecycle, custody, value class, physical state, sort and direction controls only in this registry. Server queries and pagination must ignore removed filters from old URLs and default to newest record first. Preserve the PPE filter controls and behavior in the shared Assets page.

## Use dedicated property details pages with complete history
PPE and semi-expendable registry rows navigate to their own show routes instead of a details dialog. Show pages retain asset details, the full lifecycle ledger, all legacy borrowings/returns and accountability document history, with working registry breadcrumbs. Match classification to route, allow archived records, and enforce the same governance-or-current-custodian read access as the registry. This does not enable new borrowings.
