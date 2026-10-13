---
paths:
  - 'app/Services/*Liquidation*.php,app/Services/SupplyRequestWorkflow.php,resources/js/pages/Inventory/Requests/**'
---

# Inventory Requests

## Keep issuance slips distinct from ISF requests
ISF and issuance slip numbers use independent daily MM-DD-NNN sequences. Each new release creates a frozen slip for only that release; liquidation references that slip and records utilization and balance without changing warehouse stock. Remaining balance is not a stock return. Preserve existing releases without inventing historical slip numbers.
