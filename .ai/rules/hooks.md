---
paths:
  - 'resources/js/{hooks/use-realtime-updates.ts,lib/realtime-refresh.ts,layouts/app-layout.tsx}'
---

# Hooks

## Preserve work during realtime refresh
The authenticated layout owns one Echo connection and refreshes applicable Inertia screens using their current URL and filters. Batch change signals, retain events arriving during a refresh, catch up after reconnect/focus, and clean up on unmount. Defer refresh while a request, dirty form, hidden/offline page or dialog is active; resume when safe. Never reset form state/errors or populate records directly from a WebSocket payload.
