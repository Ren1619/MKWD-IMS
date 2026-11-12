---
paths:
  - 'resources/views/**'
---

# Views

## Brand every printable document with the RMS images
All printable forms, reports, labels and property tags use the shared x-print-layout component: public/rms-letterhead.jpeg as header and public/rms-footer.jpeg as footer. Preserve document paper sizes, reserve print margins for repeated branding, and use compact mode for 100 x 70 mm property tags. RMS uses embedded mode to retain its existing screen sheet and avoid duplicate printed images.

## Use live text and SVG for printable branding
This supersedes the earlier RMS JPEG branding rule. All 19 printable templates use x-print-layout with shared print-brand-header/footer components, live district/contact text and inline SVG marks; do not reintroduce raster letterhead/footer banners. Keep the existing print margins, compact property-tag mode and embedded RMS preview; SVG fragment IDs must be unique when marks repeat.

## Keep print branding inside page bounds
Chromium printing wraps negative fixed top/bottom offsets across page boundaries, moving the header to the foot and footer onto the next page. Use fixed top:0/bottom:0 with the print-document-frame's repeating thead/tfoot spacers. Footer follows rms-footer.jpeg's service badge, side-by-side phones, contacts, social row and Bagong Pilipinas; never place the district letterhead or anniversary in it. Validate pagination with an actual multi-page PDF when changing this layout.

## Show the document title immediately after the print letterhead
Printable output order is shared letterhead, document name, content and contact footer. Do not repeat METRO KIDAPAWAN WATER DISTRICT, app.name or entity-name headings above the document title; the district name belongs in the letterhead. Preserve entity fields within document metadata and official body text.
