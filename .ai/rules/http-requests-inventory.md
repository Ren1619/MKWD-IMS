---
paths:
  - 'resources/js/pages/Inventory/Requests/**,app/Http/Requests/Inventory/PrintIssuanceSlipRequest.php'
---

# Http Requests Inventory

## Gate issuance slip preview to released requests and super admins
On released request details, show RIS Liquidation, Return Slip and Issuance Request to every authorized viewer, plus Issuance Slip only to super admins. Open slip preview and its employee signatory selectors only after the Issuance Slip button is clicked; keep per-release selection inside the dialog. Enforce super-admin and fully released status on the slip endpoint. Other viewers retain read-only liquidation/return access; do not grant stock mutations.
