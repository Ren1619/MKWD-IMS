<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $record->pr_no }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        body { font: 12px Arial, sans-serif; color: #111; }
        header { text-align: center; margin-bottom: 20px; }
        h1 { font-size: 18px; }
        .toolbar { padding: 12px; background: #eee; }
        .fields { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        td, th { border: 1px solid #111; padding: 8px; text-align: left; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .signature { padding-top: 24px; border-bottom: 1px solid #111; }
        .work { white-space: pre-wrap; border: 1px solid #111; padding: 12px; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    @php($details = $record->preprocurement ?? [])
    <div class="toolbar"><button type="button" onclick="window.print()">Print / Save as PDF</button> {{ $record->pr_no }} — {{ str_replace('_', ' ', $record->status) }}</div>
    <header><p>Metro Kidapawan Water District</p><h1>{{ $record->document_kind === 'JO' ? 'JOB ORDER' : 'PURCHASE REQUISITION' }}</h1><p>{{ $record->pr_no }}</p></header>
    <section class="fields">
        <div><strong>{{ $record->document_kind }} type:</strong> {{ $details['requisition_type'] ?? str_replace('_', ' ', $record->type) }}</div>
        <div><strong>Category:</strong> {{ str_replace('_', ' ', $record->procurement_category ?? '') }}</div>
        <div><strong>Department:</strong> {{ $details['department'] ?? '' }}</div>
        <div><strong>Division:</strong> {{ $details['division'] ?? '' }}</div>
        <div><strong>End user:</strong> {{ $details['end_user'] ?? '' }}</div>
        <div><strong>Approved request:</strong> {{ $details['approved_request_reference'] ?? '' }}</div>
        <div><strong>Charge account:</strong> {{ $details['charge_account'] ?? '' }}</div>
        <div><strong>ABC:</strong> {{ number_format((float) ($details['abc'] ?? 0), 2) }}</div>
        <div><strong>CAF number:</strong> {{ $details['caf_number'] ?? '' }}</div>
        <div><strong>APP / PPMP:</strong> {{ $record->app_reference }} / {{ $record->ppmp_reference }}</div>
        <div><strong>Vehicle plate:</strong> {{ $details['vehicle_plate'] ?? 'N/A' }}</div>
        <div><strong>ARE number:</strong> {{ $details['are_number'] ?? 'N/A' }}</div>
        @if($record->document_kind === 'JO')
            <div><strong>Target completion:</strong> {{ $details['target_completion_date'] ?? '' }}</div>
            <div><strong>Estimated cost:</strong> {{ number_format((float) ($details['estimated_cost'] ?? 0), 2) }}</div>
        @else
            <div><strong>Place of delivery:</strong> {{ $details['place_of_delivery'] ?? '' }}</div>
        @endif
    </section>
    <p><strong>Purpose:</strong> {{ $record->purpose }}</p>
    @if($record->document_kind === 'JO')
        <h2>Description of work and inclusions</h2><div class="work">{{ $details['work_description'] ?? '' }}</div>
    @else
        <table><thead><tr><th>Description / specifications</th><th>Quantity</th><th>Unit</th><th>Unit cost</th><th>Total</th></tr></thead><tbody>
            @foreach($record->lines as $line)
                <tr><td>{{ $line->item_name }}<br>{{ $line->specifications }}</td><td>{{ $line->quantity }}</td><td>{{ $line->unit_of_measure }}</td><td>{{ number_format((float) $line->estimated_unit_cost, 2) }}</td><td>{{ number_format($line->quantity * (float) $line->estimated_unit_cost, 2) }}</td></tr>
            @endforeach
            <tr><td colspan="4"><strong>Total estimated cost</strong></td><td>{{ number_format($record->lines->sum(fn ($line) => $line->quantity * (float) $line->estimated_unit_cost), 2) }}</td></tr>
        </tbody></table>
    @endif
    <section class="signatures">
        @foreach(['prepared_by_user_id' => 'Prepared by — '.($details['prepared_by_division'] ?? '').' Division Manager', 'recommending_user_id' => 'Recommending approval — AGM for A&F', 'countersigning_user_id' => 'Countersigned by — AHRD Department Manager', 'gm_user_id' => 'Approved by — General Manager'] as $key => $label)
            @php($signature = collect($details['signatures'] ?? [])->firstWhere('stage', $key))
            @php($assignedSignature = collect($details['signature_route'] ?? [])->firstWhere('stage', $key))
            <div class="signature"><strong>{{ $label }}</strong><p>{{ $signature['name'] ?? ($key === 'gm_user_id' ? ($details['approve']['name'] ?? null) : null) ?? $assignedSignature['name'] ?? $signatories[$details[$key] ?? 0] ?? '' }}</p><p>{{ $signature['signed_at'] ?? ($key === 'gm_user_id' ? ($details['approve']['at'] ?? 'Pending') : 'Pending') }}</p></div>
        @endforeach
    </section>
    <p><strong>Status:</strong> {{ str_replace('_', ' ', $record->status) }}</p>
    @if(isset($details['forward']))<p><strong>Endorsed to:</strong> {{ $details['forward']['recipient'] }} by {{ $details['forward']['name'] }} on {{ $details['forward']['at'] }}</p>@endif
    <p>Prepared on {{ $details['prepared_at'] ?? $record->created_at }}. Supporting documents and decision history are retained in IMS.</p>
</body>
</html>
