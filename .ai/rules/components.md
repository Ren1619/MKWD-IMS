---
paths:
  - 'resources/js/components/**'
  - resources/js/components/validation-summary.tsx
  - resources/js/components/asset-details.tsx
---

# Components

## Keep popup controls viewport-safe
Select, dropdown-menu, and combobox popups must be at least their trigger width, grow for long labels, and cap width with Base UI's --available-width. Keep compact triggers single-line, but allow popup labels and metadata to wrap instead of clipping or horizontal overflow.

## Declare form submit button types
The shared Button wraps Base UI and defaults to type="button". Form actions intended to trigger onSubmit must explicitly pass type="submit"; otherwise Apply/Save clicks do not submit the form.

## Make breadcrumb links follow their destinations
Use destination/current-URL matching to decide whether a breadcrumb is the current page, never its position in the list. A final or single breadcrumb pointing to a parent must remain an Inertia link. BreadcrumbItem.href is optional for current-page and grouping labels; never use # or assign a fake landing-page route. Both app header layouts display any nonempty breadcrumb list. BreadcrumbNavigationTest renders real components and checks all page breadcrumb destinations against registered reachable GET routes.

## Hide validation summaries in production builds
The user's production-only removal request supersedes the rule requiring a visible summary on every invalid form. Render the red validation summary only outside Vite production builds. Keep field-level messages, validation enforcement, and first-error scrolling enabled in production.

## Render lifecycle metadata as readable historical details
Never dump event.details as JSON or raw key/value strings in the property timeline. Render nested snapshots as labeled fields with formatted money, dates and status labels. Hide internal ID fields; preserve historical snapshot values instead of replacing them with current asset data.

## Avoid uneven property detail cards and detached disclosure markers
Use full-width stacked detail sections with four field columns on wide screens and two on smaller screens. Keep supplementary sections as compact full-width disclosure rows. Hide native summary markers and align a custom chevron with the title; do not repeat expand-helper text on every row. Style missing values more quietly than recorded values.

## Present acquisition and valuation as a table
The Accounting tab's Acquisition and valuation section uses a semantic Field/Value table with row headers, formatted values, and emphasized financial totals. Retain all applicable fields and omit PPE-only depreciation fields for semi-expendable property; other detail sections keep their existing layout.

## Group accounting table rows by financial purpose
Within Acquisition and valuation, group rows into Acquisition costs, Depreciation, Current valuation, and Accounting policy and status using labeled table bodies and visible group headers. Semi-expendable property omits the depreciation group and keeps available-for-use date/useful life in acquisition information. Preserve all existing fields and highlighted totals.

## Separate accounting groups into cards
Render each accounting group as its own titled card containing a Field/Value table, arranged in two columns on wide screens and stacked on small screens. This supersedes the earlier single table with labeled row groups. Keep financial totals emphasized and omit the Depreciation card for semi-expendable property.

## Stack accounting cards in independent unequal columns
On desktop, stack Acquisition details and Current valuation in the left one-third column; stack Depreciation and Accounting policy and status in the right two-thirds column. Use independent vertical stacks and fractional grid tracks so parent padding and the column gap are excluded from the available widths. Collapse to one column on smaller screens. This supersedes the equal-width accounting card grid.

## Fill independent accounting columns without shrinking cards
Use balanced equal-width desktop columns with independent vertical stacks for accounting cards, superseding the earlier 1/3–2/3 ratio at the user's request to eliminate gaps. AccountingGroup cards must use w-full and must not use self-start inside the flex columns, which shrinks them to content width. Retain single-column mobile layout.

## Make the left accounting column twice as wide
Use desktop grid tracks minmax(0,2fr) minmax(0,1fr), superseding the equal-width layout. Preserve the user's current card ordering, independent vertical stacks, full-width cards and gap spacing. Keep the single-column mobile layout.
