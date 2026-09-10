---
paths:
  - 'routes/api.php,app/Console/Commands/*VehicleApiTokenCommand.php'
---

# Commands

## Protect the vehicle API with scoped Sanctum tokens
The vehicle API is token-only: require auth:sanctum plus the vehicles:read ability and an active token-owning user. Issue a separately named, expiring token per recipient with ims:issue-vehicle-api-token; rotate with --replace and revoke with ims:revoke-vehicle-api-token.
