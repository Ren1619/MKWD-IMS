---
paths:
  - 'resources/js/{components/record-change-badge.tsx,lib/record-changes.ts,hooks/use-realtime-updates.ts}'
---

# Jscomponents

## Live record badges describe changes in the current view
New means a record newly appeared in the same view after a successful realtime refresh; Updated means an existing record changed. Compare authorized before/after Inertia props only for the same component and URL, never raw WebSocket data or normal pagination/search visits. Scope markers by user, component, collection and primary key, support custom inventory keys and nested categories, keep New precedence during its window, remove deleted markers, and expire badges after 60 seconds. Do not persist unread flags or modify workflow status.
