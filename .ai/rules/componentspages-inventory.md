---
paths:
  - 'resources/js/{components,pages/Inventory}/**'
---

# Componentspages Inventory

## Group stock and storage under Consumable Inventory
Consumable Inventory uses Items and Storage & Batches navigation over the existing routes. Storage access remains gated by manage_inventory. Warehouse Management remains a separate Inventory Operations entry; do not restore a standalone Warehouse Storage sidebar entry.

## Manage stockrooms within Storage and Batches
Stockroom management is opened from the top-right Manage Stockrooms button in Storage & Batches, replacing the separate Stockroom Management sidebar entry. The management dialog closes only through its Close button; block Escape and outside dismissal. Retain registry routes and reuse existing server-side search, pagination, and mutation safeguards. This supersedes the previous rule requiring a separate Warehouse Management Inventory Operations entry.
