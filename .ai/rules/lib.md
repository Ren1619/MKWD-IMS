---
paths:
  - 'app/Http/Requests/**,app/Rules/InputFormat.php,resources/js/components/input-validation.tsx,resources/js/lib/input-validation.ts,resources/input-formats.json'
---

# Lib

## Share field format validation across modules
Application Form Requests extend ValidatedFormRequest, which adds InputFormat checks to declared numeric/integer/date-format rules and the established stock-number, UACS and property-prefix fields. Regex patterns/messages live in resources/input-formats.json and are also used by the app-wide InputValidation component. Preserve Unicode and punctuation in free text and existing password policies; use existing Laravel email, URL, date, relational and workflow validation rather than blanket alphanumeric restrictions. The user's all-module inline-validation requirement supersedes the earlier stockroom-only prohibition on client validation. Show inline errors, retain entered values, and keep Laravel authoritative.
