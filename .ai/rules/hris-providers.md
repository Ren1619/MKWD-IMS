---
paths:
  - 'app/{Services,Integrations/Hris,Providers}/**'
---

# Hris Providers

## Fail closed on HRIS credential rejection
Linked IMS users authenticate through HRIS when hybrid auth is enabled. Only connection failures, empty/malformed success responses, redirects, 204, or server errors may attempt the time-limited local fallback; HRIS 4xx responses must reject login. Unlinked break-glass accounts remain local-only, and the employee_id returned by HRIS must match the linked hris_reference.

## Fail closed on HRIS credential rejection
When enabled, IMS always verifies credentials with HRIS first and provisions/links a least-privilege local user from the synchronized employee reference after success. HRIS 4xx responses reject login. Local password fallback is permitted only for unavailable/malformed HRIS responses in local/testing, requires the explicit development fallback flag, and must never run in production.

## Development-only fallback supersedes the earlier linked-user rule
The earlier note describing linked-only, time-limited fallback is obsolete. Use the later HRIS-first rule: all credentials go to HRIS first, successful users are provisioned/linked locally, and IMS-password fallback exists only for HRIS unavailability during local/testing development.
