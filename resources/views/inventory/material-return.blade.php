<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->document_no }} - Return Materials Slip</title>
    <style>
        @page { size: 215.9mm 330.2mm; margin: 12mm 25.4mm 18mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; background: #e8e8e8; font: 11pt Arial, sans-serif; }
        .toolbar { max-width: 215.9mm; margin: 16px auto; padding: 12px 16px; background: white; font-size: 10pt; display: flex; flex-wrap: wrap; align-items: center; gap: 16px; }
        .sheet { width: 215.9mm; min-height: 330.2mm; margin: 16px auto; padding: 12mm 25.4mm 18mm; background: white; display: flex; flex-direction: column; }
        .letterhead, .letterfoot { display: block; width: 215.9mm; max-width: none; margin-left: -25.4mm; height: auto; }
        .letterhead { margin-bottom: 7mm; }
        .letterfoot { margin-top: auto; padding-top: 10mm; }
        h1 { margin: 0 0 9mm; text-align: center; font-size: 14pt; font-weight: 700; }
        .reference-grid { display: grid; grid-template-columns: 1fr 58mm; gap: 5mm; margin-bottom: 5mm; font-size: 10pt; }
        .reference-field { display: flex; align-items: baseline; gap: 2mm; }
        .field-value { flex: 1; min-width: 0; border-bottom: 1px solid #111; padding: 0 1mm 1mm; overflow-wrap: anywhere; }
        .issue-reference { margin-bottom: 7mm; font-size: 10pt; }
        .issue-reference .reference-field { align-items: flex-end; }
        .reference-caption { width: 38mm; flex-shrink: 0; }
        .reference-caption span { display: block; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 1.5mm; vertical-align: top; overflow-wrap: anywhere; font-size: 9pt; }
        th { text-align: center; font-weight: 700; vertical-align: middle; height: 9mm; }
        .materials td { height: 6mm; }
        .materials td:nth-child(-n+3), .materials td:nth-child(5) { text-align: center; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        .purpose { margin-top: 7mm; break-inside: avoid; font-size: 10pt; }
        .purpose p { margin: 0; }
        .reason { min-height: 25mm; padding-top: 2mm; line-height: 7mm; white-space: pre-wrap; overflow-wrap: anywhere; text-decoration: underline; text-underline-offset: 3px; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr 1.2fr; gap: 7mm; margin-top: 8mm; break-inside: avoid; font-size: 10pt; }
        .signature-title { margin: 0; }
        .signature-space { height: 13mm; border-bottom: 1px solid #111; }
        .signature-name { min-height: 5mm; padding-top: 1mm; text-align: center; overflow-wrap: anywhere; }
        .signature-role { text-align: center; font-size: 9pt; }
        .notice { margin: -5mm 0 5mm; text-align: center; font-size: 8pt; }
        .appendix { display: none; }
        body.include-appendix .appendix { display: flex; }
        .appendix h2 { font-size: 13pt; margin-top: 7mm; }
        .appendix p { font-size: 9pt; line-height: 1.5; overflow-wrap: anywhere; }
        @media print {
            body { background: white; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 299mm; margin: 0; padding: 0; }
            .appendix { break-before: page; }
        }
        @media screen and (max-width: 820px) {
            .toolbar { margin: 8px; }
            body { overflow-x: auto; }
        }
    </style>
</head>
<body>
<x-print-layout embedded>
    @php
        $noted = $document->actions->firstWhere('action', 'note');
        $received = $document->actions->firstWhere('action', 'receive');
        $recorder = $document->actions->firstWhere('action', 'submit');
        $verifier = $document->actions->firstWhere('action', 'verify');
        $approver = $document->actions->firstWhere('action', 'approve');
        $accounting = $document->actions->firstWhere('action', 'endorse_accounting');
    @endphp
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        <label><input type="checkbox" onchange="document.body.classList.toggle('include-appendix', this.checked)"> Include inspection and posting appendix</label>
        <span>Paper: 8.5 &times; 13 inches (Folio). Turn off browser headers and footers.</span>
    </div>
    <main class="sheet">
        <div class="letterhead"><x-print-brand-header /></div>
        <h1>RETURN MATERIALS SLIP</h1>
        @if ($document->status !== 'approved')
            <p class="notice">WORKING DOCUMENT - STOCK HAS NOT BEEN POSTED &middot; {{ ucfirst($document->status) }}</p>
        @endif
        <div class="reference-grid">
            <div class="reference-field"><span>RMS Number:</span><span class="field-value">{{ $document->document_no }}</span></div>
            <div class="reference-field"><span>Date:</span><span class="field-value">{{ $document->document_date->format('m/d/Y') }}</span></div>
        </div>
        <div class="issue-reference">
            <div class="reference-field"><div class="reference-caption">Reference<span>(IS, ICS/ ARE No.)</span></div><span class="field-value">{{ $document->lines->pluck('source_issue_reference')->filter()->unique()->implode(', ') ?: $document->legacy_issue_reference }}</span></div>
        </div>
        <table class="materials" aria-label="Returned materials">
            <colgroup><col style="width: 7.5%"><col style="width: 12.1%"><col style="width: 12.1%"><col style="width: 34.9%"><col style="width: 16.7%"><col style="width: 16.7%"></colgroup>
            <thead><tr><th>No</th><th>Qty</th><th>UOM</th><th>Description of Item</th><th>Condition</th><th>Remarks</th></tr></thead>
            <tbody>
                @foreach ($document->lines as $line)
                    <tr>
                        <td>{{ $loop->iteration }}</td><td>{{ $line->quantity_declared }}</td><td>{{ $line->unit_of_measure }}</td>
                        <td>{{ $line->item_name }}</td>
                        <td>{{ $line->condition === 'good' ? 'Serviceable' : ucfirst($line->condition ?? '') }}</td>
                        <td>{{ $line->inspection_remarks }}</td>
                    </tr>
                @endforeach
                @for ($row = $document->lines->count(); $row < 18; $row++)
                    <tr class="blank-row" aria-hidden="true"><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>
                @endfor
            </tbody>
        </table>
        <section class="purpose">
            <p><strong>Purpose / Reason for return:</strong></p>
            <div class="reason">{{ $document->reason }}</div>
        </section>
        <section class="signatures" aria-label="RMS signatories">
            <div><p class="signature-title">Prepared by:</p><div class="signature-space"></div><div class="signature-name">{{ $document->returned_by_name }}</div><div class="signature-role">End-user</div></div>
            <div><p class="signature-title">Noted by:</p><div class="signature-space"></div><div class="signature-name">{{ $noted?->metadata['manager_name'] ?? '' }}</div><div class="signature-role">Department Manager</div></div>
            <div><p class="signature-title">Received by:</p><div class="signature-space"></div><div class="signature-name">{{ $received?->actor_name }}</div><div class="signature-role">PMMD Office/ Date{{ $received ? ': '.$received->metadata['received_date'] : '' }}</div></div>
        </section>
        <div class="letterfoot"><x-print-brand-footer /></div>
    </main>
    <section class="sheet appendix" aria-label="Inspection and posting appendix">
        <div class="letterhead"><x-print-brand-header /></div>
        <h2>{{ $document->document_no }} - Supporting details</h2>
        <p>Returned by: {{ $document->returned_by_name }} | Department: {{ $document->department_name }} | Physical return date: {{ $document->return_date->format('m/d/Y') }}</p>
        <p>Inspection reference: {{ $document->inspection_reference ?? 'Pending' }} | Department Manager: {{ $noted?->metadata['manager_name'] ?? 'Not recorded' }}</p>
        @if ($document->approved_at)
            <p>Stock posting date: {{ $document->approved_at->timezone('Asia/Manila')->format('Y-m-d H:i') }}</p>
        @endif
    <h3>Inspection and stock posting details</h3>
    <p>Recorded by: {{ $recorder?->actor_name ?? 'Not recorded' }} | Inspected and verified by: {{ $verifier?->actor_name ?? 'Pending' }} | Approved by: {{ $approver?->actor_name ?? 'Pending' }}</p>
    <table>
        <thead><tr><th>No.</th><th>Accepted</th><th>Rejected</th><th>Unit cost</th><th>Accepted value</th><th>Stockroom / shelf / bin</th></tr></thead>
        <tbody>
        @foreach ($document->lines as $line)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $line->quantity_accepted ?? 'Pending' }}</td>
                <td>{{ $line->quantity_accepted === null ? 'Pending' : $line->quantity_declared - $line->quantity_accepted }}</td>
                <td>{{ number_format((float) $line->unit_cost, 2) }}</td>
                <td>{{ $line->quantity_accepted === null ? 'Pending' : number_format((float) (string) \Brick\Math\BigDecimal::of($line->unit_cost)->multipliedBy($line->quantity_accepted), 2) }}</td>
                <td>{{ $line->warehouse_name }} {{ $line->storage_location }}
                    @if ($line->expiration_date)<br>Expiry: {{ $line->expiration_date->format('Y-m-d') }}@endif
                    @if ($line->valuation_basis)<br>Valuation basis: {{ $line->valuation_basis }}@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @if ($accounting)
        <p>Accounting endorsement: {{ $accounting->metadata['accounting_reference'] ?? '' }} | Recipient: {{ $accounting->metadata['accounting_recipient'] ?? '' }} | {{ $accounting->created_at->timezone('Asia/Manila')->format('Y-m-d H:i') }}</p>
    @endif
        <div class="letterfoot"><x-print-brand-footer /></div>
    </section>
</x-print-layout>
</body>
</html>
