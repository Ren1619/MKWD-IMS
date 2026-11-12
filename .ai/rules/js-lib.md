---
paths:
  - 'resources/js/components/input-validation.tsx,resources/js/lib/input-validation.ts'
---

# Js Lib

## Reveal the first validation error in scrolling dialogs
After inline validation errors render, scroll the first invalid control in form order into view, including inside dialog scroll containers and collapsed details. Coalesce native invalid events to avoid jumping between fields. Focus on rejected submission/native invalid; do not steal typing focus on blur/input. Focus visible select/date-picker triggers instead of hidden proxies, respect reduced motion, and cancel pending animation frames on reset/unmount. Server validation responses reveal the first rendered error in the submitted form or active dialog.
