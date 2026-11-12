---
paths:
  - 'resources/js/pages/Inventory/Returns/**,app/Services/InventoryReturnService.php,resources/views/inventory/material-return.blade.php'
---

# Services Views Inventory

## Carry one RMS form through acknowledgment receipt and posting
New RMS records use workflow_version 2: submitted → noted (staff attestation of a named Department Manager acknowledgment) → received (PMMD actor/date) → verified → approved. Use the shared six-column Return Materials Slip preview from preparation, including IS-scoped preparation, through decisions and print. Freeze actual issuance-slip references and signatory names; the RMS number is the default inspection reference. Accepted lines require a registered stockroom and approval creates its immutable batch placement atomically with stock posting. Keep version 1 records on their historical workflow; never invent missing acknowledgments or signatures. Noted/received returns retain quantity commitments and block month-end sign-off.

## RMS does not collect an endorsement letter
Remove the RMS intake endorsement_reference field throughout preparation, validation, persistence, serialization, print, and the inventory_returns schema. Original issue references identify the source. Department Manager acknowledgment and the later accounting endorsement remain separate workflow actions.

## Combine common RMS reasons with freely entered details
RMS preparation offers optional common reasons and Other, while preserving typed details when the selection changes or sources reload. Combine the selected heading and detailed text into the existing reason field for submission and the shared preview; preserve line breaks on print. A common reason may stand alone; custom reasons still require text. Keep the combined reason within the existing 3000-character limit.

## Use a single editable RMS purpose combobox
This supersedes the earlier separate common-reason heading and details controls: use one editable ReturnReasonCombobox bound directly to form.data.reason. Selecting a preset fills the field; users expand, rewrite, or enter their full reason in that same field, within 3000 characters. Preview, submission and print use that single reason value; do not reintroduce a separate details field or an Other sentinel.

## Match the supplied RMS print form on Folio paper
The main RMS print page follows the supplied Return Materials Slip: 8.5 x 13-inch Folio paper, embedded source-document letterhead/footer assets, RMS/date and issue-reference lines, six material columns with at least 18 rows, reason and three blank signature areas with recorded names. Pending slips show the working-document notice. Operational/valuation/inspection/accounting details stay in an optional appendix enabled from the print toolbar; do not add them to the main form or imply unrecorded signatures.
