---
paths:
  - 'database/migrations/*property_accountability*.php'
---

# Migrations

## Name accountability foreign keys explicitly
The property_accountability table and column names exceed MySQL's 64-character identifier limit when Laravel derives foreign-key names. Always provide short explicit indexName values (pa_docs_*_fk and pa_actions_*_fk) for these migrations.
