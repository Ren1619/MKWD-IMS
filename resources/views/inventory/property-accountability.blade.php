<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->document_no }}</title>
    <style>
        @page { size: portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font-family: Arial, sans-serif; font-size: 11px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; padding: 12px; margin-bottom: 18px; background: #f1f5f9; border: 1px solid #cbd5e1; }
        button { padding: 8px 14px; border: 0; border-radius: 4px; color: white; background: #0f172a; cursor: pointer; }
        header { margin-bottom: 18px; text-align: center; }
        h1 { margin: 4px; font-size: 16px; }
        p { margin: 3px 0; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; margin-bottom: 16px; }
        .meta div { min-height: 24px; padding: 4px 2px; border-bottom: 1px solid #111; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 7px; border: 1px solid #111; vertical-align: top; }
        th { background: #e5e7eb; text-align: center; }
        .number { text-align: right; }
        .attestation { margin-top: 18px; padding: 10px; border: 1px solid #111; line-height: 1.45; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; margin-top: 56px; text-align: center; }
        .signature-line { padding-top: 5px; border-top: 1px solid #111; }
        .history { margin-top: 28px; font-size: 9px; }
        .footer { display: flex; justify-content: space-between; margin-top: 22px; font-size: 8px; }
        @media print { .toolbar, .history { display: none; } body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>{{ $document->document_no }} - {{ ucfirst(str_replace('_', ' ', $document->status)) }}</span>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>
    <header>
        <p>Republic of the Philippines</p>
        <p>{{ $document->entity_name }}</p>
        <h1>{{ $document->document_type === 'PAR' ? 'PROPERTY ACKNOWLEDGMENT RECEIPT' : 'INVENTORY CUSTODIAN SLIP' }}</h1>
    </header>
    <section class="meta">
        <div><strong>Entity Name:</strong> {{ $document->entity_name }}</div>
        <div><strong>{{ $document->document_type }} No.:</strong> {{ $document->document_no }}</div>
        <div><strong>Fund Cluster:</strong> {{ $document->fund_cluster ?: 'Not specified' }}</div>
        <div><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $document->status)) }}</div>
    </section>
    <table>
        <thead>
            <tr>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Description</th>
                <th>{{ $document->document_type === 'PAR' ? 'Property Number' : 'Item Number' }}</th>
                <th>Date Acquired</th>
                <th>Amount</th>
                @if($document->document_type === 'ICS')<th>Estimated Useful Life</th>@endif
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="number">{{ $document->quantity }}</td>
                <td>{{ $document->unit_of_measure }}</td>
                <td>
                    <strong>{{ $document->asset_name }}</strong><br>
                    {{ $document->asset_description }}
                    @if($document->serial_number)<br>Serial: {{ $document->serial_number }}@endif
                </td>
                <td>{{ $document->property_number ?: '-' }}</td>
                <td>{{ $document->acquisition_date?->format('Y-m-d') ?: '-' }}</td>
                <td class="number">&#8369;{{ number_format((float) $document->acquisition_cost, 2) }}</td>
                @if($document->document_type === 'ICS')
                    <td class="number">{{ $document->estimated_useful_life_months ? $document->estimated_useful_life_months.' months' : '-' }}</td>
                @endif
            </tr>
        </tbody>
    </table>
    <section class="attestation">
        <strong>Accountability:</strong> The recipient acknowledges custody of the listed government property and responsibility for its official use, safekeeping, maintenance, and return or authorized transfer. This document does not transfer ownership.
    </section>
    <section class="signatures">
        <div>
            <div class="signature-line">
                <strong>{{ $document->issued_by_name }}</strong><br>
                Property / Supply Custodian<br>
                Issued {{ $document->issued_at->format('Y-m-d H:i') }}
            </div>
        </div>
        <div>
            <div class="signature-line">
                <strong>{{ $document->recipient_name }}</strong><br>
                {{ $document->recipient_code ?: 'Recipient / End-user' }}<br>
                {{ $document->acknowledged_at ? 'Digitally acknowledged '.$document->acknowledged_at->format('Y-m-d H:i') : 'Recipient acknowledgment pending' }}
            </div>
        </div>
    </section>
    <section class="history">
        <strong>Digital control history</strong>
        <table>
            <thead><tr><th>Date</th><th>Actor</th><th>Action</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
                @foreach($document->actions as $action)
                    <tr>
                        <td>{{ $action->created_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $action->actor->name }}</td>
                        <td>{{ $action->action }}</td>
                        <td>{{ $action->to_status }}</td>
                        <td>{{ $action->remarks ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
    <footer class="footer">
        <span>System-generated accountability document - verify its status before reliance.</span>
        <span>Capitalization threshold applied: &#8369;50,000</span>
    </footer>
</body>
</html>
