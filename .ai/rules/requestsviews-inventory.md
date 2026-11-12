---
paths:
  - 'app/{Models,Services,Http/Controllers/Inventory}/**,resources/{js/pages/Inventory/Requests,views/inventory}/**'
---

# Requestsviews Inventory

## Use IRF field names and show actual issuance slip numbers in request lists
IRF is the canonical request abbreviation: supply_requests uses irf_no/irf_date/irf_sequence; legacy liquidation references and issuance-slip snapshot keys use irf_no. Preserve number values, links and independent counters when migrating old isf fields. Supply Requests displays IRF No. and an IS No. column directly after Office; render every actual linked slip number, or N/A when none exists. Do not fabricate IS numbers at approval: the release workflow creates slips after the required head approvals.
