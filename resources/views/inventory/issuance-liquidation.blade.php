<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>IS Liquidation {{ $slip->slip_no }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; }
        .sheet { border: 2px solid #222; padding: 12px; }
        h1 { text-align: center; background: #e6f2f2; border: 1px solid; padding: 10px; letter-spacing: 6px; font-size: 22px; }
        h2 { font-size: 16px; }
        .fields, .signatures { display: flex; justify-content: space-between; gap: 20px; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 8px 4px; }
        td { border-bottom: 1px solid #777; padding: 5px 4px; height: 16px; }
        .signatures > div { width: 30%; }
        .signature { min-height: 40px; border-bottom: 1px solid; padding-top: 15px; }
        .footer { display: flex; justify-content: space-between; margin-top: 12px; }
        @media print { .print-button { display: none; } .sheet { break-after: page; } }
    </style>
</head>
<body>
<button class="print-button" onclick="window.print()">Print</button>
@foreach (array_chunk($slip->lines, 30) as $pageLines)
<div class="sheet">
    <h2>METRO KIDAPAWAN WATER DISTRICT</h2>
    <h1>I S LIQUIDATION</h1>
    <div class="fields"><span>DEPARTMENT: {{ $slip->snapshot['department'] }}</span><span>DATE: {{ $slip->liquidation_date?->format('m/d/Y') }}</span></div>
    <p>ISSUANCE SLIP NO.: <strong>{{ $slip->slip_no }}</strong> &nbsp; (Issued {{ $slip->issued_at->format('m/d/Y') }})</p>
    <table>
        <thead><tr><th>NO.</th><th>ITEM DESCRIPTION</th><th>QUANTITY ISSUED</th><th>QUANTITY UTILIZED</th><th>BALANCE</th></tr></thead>
        <tbody>
        @for ($index = 0; $index < 30; $index++)
            @php($line = $pageLines[$index] ?? null)
            <tr><td>{{ ($loop->iteration - 1) * 30 + $index + 1 }}</td><td>{{ $line['item_description'] ?? '' }}</td><td>{{ $line['quantity_issued'] ?? '' }}</td><td>{{ $line['quantity_utilized'] ?? '' }}</td><td>{{ $line['balance'] ?? '' }}</td></tr>
        @endfor
        </tbody>
    </table>
    <p>CHARGE CODE: {{ $slip->snapshot['charge_code'] }}</p>
    <div class="signatures">
        <div><strong>REQUESTED BY:</strong><div class="signature">{{ $slip->snapshot['requested_by'] }}</div>Name &amp; Signature</div>
        <div><strong>CHECKED BY:</strong><div class="signature">{{ collect($slip->history)->firstWhere('action', 'check')['actor_name'] ?? '' }}</div>Division Manager</div>
        <div><strong>APPROVED BY:</strong><div class="signature">{{ collect($slip->history)->firstWhere('action', 'approve')['actor_name'] ?? '' }}</div>Department Manager</div>
    </div>
    <p>Liquidation status: {{ ucfirst($slip->status) }}</p>
    <div class="footer"><span>FM - GSD - 043</span><span>00</span><span>8-16-2023</span></div>
</div>
@endforeach
</body>
</html>
