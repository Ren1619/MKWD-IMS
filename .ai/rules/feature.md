---
paths:
  - 'app/Services/SupplyRequestWorkflow.php,tests/Feature/SupplyRequestWorkflowTest.php'
---

# Feature

## Use requested-for employee for separation of duties
For approve, review, and release, self-authorization is determined by requester_reference_id (the employee named in Request for), not requester_user_id (the person who submitted). An inventory manager or super admin may authorize a request they entered on behalf of a different employee; requests without an HRIS reference fall back to requester_user_id.
