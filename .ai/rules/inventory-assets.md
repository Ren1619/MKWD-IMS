---
paths:
  - 'resources/js/components/asset-details.tsx,resources/js/pages/Inventory/Assets/Show.tsx'
---

# Inventory Assets

## Align accounting summary and detail card boundaries
Use the same three-column grid and gap-4 for accounting summary cards and desktop detail columns. The left detail stack spans two tracks; the right spans one. This aligns the rightmost summary card with the right detail stack; a separate 2fr/1fr grid has different gap math and visibly offsets their borders.

## Use matching card tables and independent stacks in Overview
Overview uses the same aligned three-track desktop grid as Accounting: Identification and acquisition source documents in a two-track left stack; location/custody, physical count, conditional loss/disposal, and record information in a one-track right stack. Render Overview fields in semantic label/value tables without Field/Value header rows, retain supplementary disclosures and highlight current identifiers/custody/condition. Use gap-4 for Overview summary cards too.
