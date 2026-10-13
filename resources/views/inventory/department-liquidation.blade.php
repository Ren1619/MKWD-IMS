<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>IS Liquidation {{ $document->document_no }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; }
        .sheet { border: 2px solid #222; padding: 12px; }
        h1 { text-align: center; background: #e6f2f2; border: 1px solid; padding: 10px; letter-spacing: 4px; font-size: 22px; }
        h2 { font-size: 16px; }
        .fields, .signatures { display: flex; justify-content: space-between; gap: 20px; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 8px 4px; }
        td { border-bottom: 1px solid #777; padding: 5px 4px; min-height: 16px; }
        .signatures > div { width: 30%; }
        .signature { min-height: 40px; border-bottom: 1px solid; padding-top: 15px; }
        @media print { .print-button { display: none; } .sheet { break-after: page; } tr { break-inside: avoid; } }
    </style>
</head>
<body>
<button class="print-button" onclick="window.print()">Print</button>
@foreach (array_chunk($document->lines, 30) as $pageLines)
<div class="sheet">
    <h2>METRO KIDAPAWAN WATER DISTRICT</h2>
    <h1>I S LIQUIDATION</h1>
    <div class="fields"><span>DEPARTMENT: {{ $document->department }}</span><span>DATE: {{ $document->liquidation_date->format('m/d/Y') }}</span></div>
    <p>IS LIQUIDATION NO.: <strong>{{ $document->document_no }}</strong></p>
    @if ($document->supply_request_id)
        <p>ISSUANCE REQUEST FORM NO.: <strong>{{ $document->isf_no }}</strong></p>
        <p>Materials released against this ISF as of the reporting date. Balance = issued − utilized.</p>
        @if ($document->return_slip_no)
            <p>RETURN SLIP NO.: {{ $document->return_slip_no }} &nbsp; DATE: {{ $document->return_slip_date?->format('m/d/Y') }}</p>
        @endif
    @else
        <p>Legacy department consolidation retained for historical reference.</p>
    @endif
    <table>
        <thead><tr><th>NO.</th><th>MATERIAL SPECIFICS</th><th>TOTAL ISSUED</th><th>TOTAL UTILIZED</th><th>BALANCE</th></tr></thead>
        <tbody>
        @foreach ($pageLines as $line)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $line['item_description'] }} ({{ $line['unit_of_measure'] }})@if ($line['usage_details'])<br><small>Usage: {{ $line['usage_details'] }}</small>@endif</td><td>{{ $line['quantity_issued'] }}</td><td>{{ $line['quantity_utilized'] }}</td><td>{{ $line['balance'] }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <p>CHARGE CODE: {{ $document->charge_code }}</p>
    <div class="signatures">
        <div><strong>REQUESTED BY:</strong><div class="signature">{{ $document->requested_by_name }}</div>Name &amp; Signature</div>
        <div><strong>CHECKED BY:</strong><div class="signature">{{ collect($document->history)->firstWhere('action', 'check')['actor_name'] ?? '' }}</div>Division Manager</div>
        <div><strong>APPROVED BY:</strong><div class="signature">{{ collect($document->history)->firstWhere('action', 'approve')['actor_name'] ?? '' }}</div>Department Manager</div>
    </div>
    <p>Liquidation status: {{ ucfirst($document->status) }}</p>
</div>
@endforeach
</body>
</html>
