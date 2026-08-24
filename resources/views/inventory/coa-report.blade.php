<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document['code'] }} - {{ $document['title'] }}</title>
    <style>
        @page { size: landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font-family: Arial, sans-serif; font-size: 10px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; padding: 12px; margin-bottom: 16px; background: #f1f5f9; border: 1px solid #cbd5e1; }
        .toolbar button { padding: 8px 14px; border: 0; border-radius: 4px; color: white; background: #0f172a; cursor: pointer; }
        .heading { text-align: center; margin-bottom: 14px; }
        .heading p, .heading h1, .heading h2 { margin: 3px 0; }
        .heading h1 { font-size: 15px; text-transform: uppercase; }
        .heading h2 { font-size: 12px; }
        .meta { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 10px; }
        .meta div { border-bottom: 1px solid #111; padding: 4px 2px; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        th, td { border: 1px solid #111; padding: 4px; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #e5e7eb; text-align: center; }
        td.number { text-align: right; white-space: nowrap; }
        .empty { padding: 28px; text-align: center; }
        .summary { display: flex; justify-content: flex-end; gap: 20px; margin-top: 8px; font-weight: bold; }
        .certification { margin-top: 18px; padding: 10px; border: 1px solid #111; line-height: 1.5; }
        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 36px; margin-top: 38px; text-align: center; }
        .signature-line { border-top: 1px solid #111; padding-top: 4px; }
        .footer { display: flex; justify-content: space-between; margin-top: 18px; font-size: 8px; }
        @media print { .toolbar { display: none; } body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>Print preview · {{ $document['code'] }}</span>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <header class="heading">
        <p>Republic of the Philippines</p>
        <h2>{{ $entity }}</h2>
        <h1>{{ $document['title'] }}</h1>
        <p>{{ $document['code'] }}</p>
    </header>

    <section class="meta">
        <div><strong>Entity:</strong> {{ $entity }}</div>
        <div><strong>Fund cluster:</strong> {{ $filters['fund_cluster'] ?: 'All / Not specified' }}</div>
        <div><strong>As of:</strong> {{ $filters['as_of'] }}</div>
        <div><strong>Period:</strong> {{ $filters['period_label'] }}@if ($filters['from'] && $filters['to']) ({{ $filters['from'] }} to {{ $filters['to'] }})@endif</div>
    </section>

    <table>
        <thead>
            <tr>
                <th style="width: 28px">#</th>
                @foreach ($columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="number">{{ $loop->iteration }}</td>
                    @foreach ($row as $value)
                        <td @class(['number' => is_numeric($value)])>{{ $value === null || $value === '' ? '—' : $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="empty" colspan="{{ count($columns) + 1 }}">No records matched this report and period.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($summary)
        <section class="summary">
            @foreach ($summary as $label => $value)
                <span>{{ ucfirst($label) }}: {{ is_numeric($value) ? number_format((float) $value, 2) : $value }}</span>
            @endforeach
        </section>
    @endif

    <section class="certification">
        I hereby certify that this report was prepared from the property and inventory records of {{ $entity }} and, where applicable, reconciled with the results of the physical count. Supporting source documents and accountable forms remain subject to verification by the Inventory Committee, Accounting Unit, Property Unit, and Commission on Audit.
    </section>

    <section class="signatures">
        <div><div class="signature-line">Prepared by / Property Officer</div><div>Date</div></div>
        <div><div class="signature-line">Verified by / Inventory Committee Chair</div><div>Date</div></div>
        <div><div class="signature-line">Approved by / Head of Agency</div><div>Date</div></div>
    </section>

    <footer class="footer">
        <span>System-generated audit working document · Verify signatures and supporting records before submission.</span>
        <span>Generated {{ $generatedAt->format('Y-m-d H:i:s') }}</span>
    </footer>
</body>
</html>
