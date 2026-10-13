<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->document_no }} - Returned Materials Slip</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font: 11px Arial, sans-serif; color: #111; }
        h1, h2 { text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; vertical-align: top; }
        .toolbar, .notice { padding: 10px; background: #eee; margin-bottom: 16px; }
        .signatures { display: flex; gap: 30px; margin-top: 45px; }
        .signatures div { flex: 1; border-top: 1px solid; padding-top: 8px; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">Print / Save as PDF</button></div>
    <h1>{{ config('app.name') }}</h1>
    <h2>Returned Materials Slip / Material Return Slip</h2>
    <p><strong>{{ $document->document_no }}</strong> | Status: {{ ucfirst($document->status) }}</p>
    <p>Returned by: {{ $document->returned_by_name }} | Department: {{ $document->department_name }} | Physical return date: {{ $document->return_date->format('Y-m-d') }}</p>
    <p>Endorsement reference: {{ $document->endorsement_reference }} | Inspection reference: {{ $document->inspection_reference ?? 'Pending' }}</p>
    <p>Reason: {{ $document->reason }}</p>
    @if ($document->source_mode === 'legacy')
        <p>External / legacy issue evidence: {{ $document->legacy_issue_reference }}</p>
    @endif
    @if ($document->status !== 'approved')
        <p class="notice">WORKING DOCUMENT - STOCK HAS NOT BEEN POSTED</p>
    @else
        <p>Stock posting date: {{ $document->approved_at->timezone('Asia/Manila')->format('Y-m-d H:i') }}</p>
    @endif
    <table>
        <thead><tr><th>Stock number / material / source issue</th><th>Unit</th><th>Declared</th><th>Accepted</th><th>Rejected</th><th>Unit cost</th><th>Accepted value</th><th>Condition / location / findings</th></tr></thead>
        <tbody>
        @foreach ($document->lines as $line)
            <tr>
                <td>{{ $line->stock_number }}<br>{{ $line->item_name }}<br>{{ $line->source_issue_reference }}
                    @if ($line->expiration_date)<br>Expiry: {{ $line->expiration_date->format('Y-m-d') }}@endif
                    @if ($line->valuation_basis)<br>Valuation basis: {{ $line->valuation_basis }}@endif
                </td>
                <td>{{ $line->unit_of_measure }}</td><td>{{ $line->quantity_declared }}</td>
                <td>{{ $line->quantity_accepted ?? 'Pending' }}</td>
                <td>{{ $line->quantity_accepted === null ? 'Pending' : $line->quantity_declared - $line->quantity_accepted }}</td>
                <td>{{ number_format((float) $line->unit_cost, 2) }}</td>
                <td>{{ $line->quantity_accepted === null ? 'Pending' : number_format((float) (string) \Brick\Math\BigDecimal::of($line->unit_cost)->multipliedBy($line->quantity_accepted), 2) }}</td>
                <td>{{ ucfirst($line->condition ?? 'Pending') }}<br>{{ $line->storage_location }}<br>{{ $line->inspection_remarks }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @php
        $recorder = $document->actions->firstWhere('action', 'submit');
        $verifier = $document->actions->firstWhere('action', 'verify');
        $approver = $document->actions->firstWhere('action', 'approve');
        $accounting = $document->actions->firstWhere('action', 'endorse_accounting');
    @endphp
    <div class="signatures"><div>{{ $document->returned_by_name }}<br>Returned by / signature</div><div>{{ $recorder?->actor_name }}<br>Recorded by / signature</div><div>{{ $verifier?->actor_name }}<br>Inspected and verified by / signature</div><div>{{ $approver?->actor_name }}<br>Approved by / signature</div></div>
    @if ($accounting)
        <p>Accounting endorsement: {{ $accounting->metadata['accounting_reference'] ?? '' }} | Recipient: {{ $accounting->metadata['accounting_recipient'] ?? '' }} | {{ $accounting->created_at->timezone('Asia/Manila')->format('Y-m-d H:i') }}</p>
    @endif
</body>
</html>
