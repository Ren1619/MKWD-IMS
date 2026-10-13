---
paths:
  - 'app/{Models,Services,Http/Controllers,Http/Requests}/**/*InventoryAsset*.php'
  - 'app/{Models,Services,Http/Controllers,Http/Requests}/**/*SemiExpendable*.php'
---

# Controllers Http Requests

## PPE changes use the event ledger and as-of calculator
Treat inventory_assets as the current operational snapshot. Record PPE capital additions, transfers, maintenance, impairment, counts, loss, unserviceable status, disposal, derecognition, custody actions, and accounting-estimate changes as append-only InventoryAssetEvent rows through InventoryAssetLifecycleService. Never duplicate depreciation formulas: all current and historical valuation must use PpeDepreciationCalculator with an explicit as-of date for reports. Disposal/loss may not be set through the generic state endpoint.

## Keep semi-expendable accounting and accountability separate
Semi-expendable records share inventory_assets, immutable inventory_asset_events, and accountability documents with PPE, but never inherit PPE depreciation. Issuance sets accounting_status to issued_and_expensed while physical and accountability tracking continue. Only low-value items at or below the configured ₱5,000 threshold may auto-expire accountability; high-value semi-expendable items above ₱5,000 and below ₱50,000 remain controlled until return, transfer, relief, or disposal. Returns do not re-recognize the item as accounting inventory.
