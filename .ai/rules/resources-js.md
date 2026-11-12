---
paths:
  - 'resources/js/**/*.tsx'
  - 'resources/js/**'
---

# Resources Js

## Use the shared table primitives
Render interactive React tables with the primitives exported by @/components/shared/data-table so headers, rows, and cells remain visually consistent. Preserve each page's existing server-side filtering and pagination. Blade print reports stay print-native because they cannot render React components.

## Use searchable employee selectors
Use the shared HrisReferenceCombobox for every employee/reference selection control. Employee labels must be rendered and sorted as "last_name, first_name middle_name" from the narrowed name-part props, while retaining the existing submitted reference ID and nullable/manual choices.

## Use shadcn Select for non-searchable dropdowns
Render ordinary dropdown controls with the shared FormSelect adapter built on the shadcn Select primitives. Do not replace employee/reference selectors: those remain the searchable HrisReferenceCombobox with the established employee label format.

## Use the shared long-format date picker
Use components/ui/date-picker for page date and date-time controls; keep ISO values for server submission and use includeTime for date-time fields. Use readOnly for frozen schedules that must still submit their values; disabled inputs are excluded from form submission. Do not add native date controls outside the shared component.

## Use shared page spacing for operational views
Inventory and Admin pages and the settings layout use components/page-container for 1600px centered width, p-4 sm:p-6 lg:p-8 gutters, and gap-6 section spacing. Keep list/detail views aligned; do not reintroduce bespoke outer widths/padding. Shared table cells default to px-4 py-3. Compact controls, authentication layouts, and dialog bodies retain their purpose-specific spacing.

## Use stockroom in user-facing terminology
Visible warehouse labels are called Stockroom/Stockrooms, including Stockroom Management. Keep the Storage & Batches tab name and existing warehouse route names, field keys, model names, and historical stored location names unchanged.
