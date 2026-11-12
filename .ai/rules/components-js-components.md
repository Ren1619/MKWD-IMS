---
paths:
  - 'resources/js/pages/Inventory/Assets/**,resources/js/components/asset-details.tsx,resources/js/components/property-history.tsx'
---

# Components Js Components

## Keep property details compact and prioritize current information
Property show pages use Overview, Borrowing & Accountability, Accounting and Activity tabs, preserving main/history tab choices in the URL with client-side Inertia visits. Keep the compact identity/custody/location/value header visible; emphasize current custody, current accountability and valuation with larger primary metrics and clear status alerts. Keep supplementary details collapsed, render field values at readable sizes, and show searchable/filterable history in five-record pages with one-click disclosures. Preserve all original fields and records and PPE versus semi-expendable accounting distinctions.

## Keep history records within two clicks
Show borrowing and accountability history together in the custody tab, side by side on wide screens, rather than adding a nested history tab. Preserve only main tab selection in the URL; tab switches reset scroll to surface priority content. This supersedes the earlier main/history-tab URL instruction. Record disclosures expand across the table width so snapshots remain readable.
