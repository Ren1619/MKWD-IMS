---
paths:
  - 'app/Services/*procurement*.php,app/Services/ProcurementRequestWorkflow.php,resources/js/pages/Inventory/Procurement/**'
---

# Pages Inventory Procurement

## Keep PMMD and PWSD distinct in pre-procurement
PMMD and PWSD are separate divisions, each with its own Division Manager. Their responsibility assignment between the process manual and WI-008 remains unresolved; do not merge or rename them. Assign preparing division and signatories per record. New requisitions require document verification before generating PR/JO numbers; legacy records retain their prior workflow. Job orders end at endorsement until their downstream process is specified.
