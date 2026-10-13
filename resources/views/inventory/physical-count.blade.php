<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $session->document_no }} — {{ ucfirst($document) }}</title>
    <style>
        @page { size: landscape; margin: 12mm; }
        body { color: #111; font: 11px Arial, sans-serif; }
        h1, h2 { text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; }
        .toolbar { padding: 12px; background: #eee; margin-bottom: 16px; }
        .tags { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .tag { border: 1px solid #111; padding: 16px; break-inside: avoid; }
        .tag p { line-height: 1.8; }
        .signatures { display: flex; justify-content: space-between; margin-top: 40px; gap: 24px; }
        .signatures div { width: 30%; border-top: 1px solid; padding-top: 8px; text-align: center; }
        .draft { border: 1px solid; padding: 10px; font-weight: bold; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">Print / Save as PDF</button></div>
    <h1>{{ config('app.name') }}</h1>
    <h2>Physical Inventory — {{ ucfirst($document) }}</h2>
    <p><strong>{{ $session->document_no }}</strong> — {{ $session->title }} — Status: {{ ucfirst($session->status) }}</p>
    <p>Memorandum: {{ $session->memorandum_reference }} — Cut-off: {{ $session->cutoff_at?->timezone('Asia/Manila')->format('Y-m-d H:i:s') ?? 'Not started' }}</p>
    @if ($session->status !== 'approved')
        <p class="draft">WORKING DOCUMENT — NOT AN APPROVED PHYSICAL INVENTORY OR POSTED ADJUSTMENT</p>
    @endif
    @if ($document === 'tags')
        <section class="tags">
            @foreach ($session->lines as $line)
                <article class="tag">
                    <h3>INVENTORY TAG — {{ $line->tag_no }}</h3>
                    <p>Date: ____________________ — Aisle: ____________________ — Bin: ____________________</p>
                    <p>Stock number: {{ $line->stock_number }} — Description: {{ $line->item_name }}</p>
                    <p>Account group: ___________________________________________________</p>
                    <p>No. of pieces: ____________________ — Unit of measure: {{ $line->unit_of_measure }}</p>
                    <p>Location: ________________________________________________________</p>
                    <p>Condition: [ ] In good condition &nbsp; [ ] Defective (for evaluation)</p>
                    <p>Remarks: ________________________________________________________</p>
                    <p>Taken by: ____________________ — Checked by: ____________________</p>
                    <p>Validated by: ____________________ — Witness: ____________________</p>
                </article>
            @endforeach
        </section>
    @elseif ($document === 'adjustments')
        <table><thead><tr><th>Tag / stock number</th><th>Description</th><th>Batch</th><th>Adjustment quantity</th><th>Unit cost</th><th>Amount</th><th>Basis / findings</th></tr></thead><tbody>
            @foreach ($session->lines as $line)
                @foreach ($line->adjustments as $adjustment)
                    <tr><td>{{ $line->tag_no }} / {{ $line->stock_number }}</td><td>{{ $line->item_name }}</td><td>{{ $adjustment->inventory_item_batch_id }}</td><td>{{ $adjustment->quantity }}</td><td>{{ $adjustment->unit_cost }}</td><td>{{ number_format($adjustment->quantity * (float) $adjustment->unit_cost, 2) }}</td><td>{{ $line->valuation_basis ?: $line->latestEntry?->remarks }}</td></tr>
                @endforeach
            @endforeach
        </tbody></table>
        <p>Positive quantities recognize overages at the documented valuation. Negative quantities remove shortages at their original batch costs. Adjustments are separate from material issuance and supplier receipts.</p>
    @else
        <table><thead><tr><th>Tag / stock number</th><th>Description / UOM</th><th>Book at cut-off</th><th>Issues before / after count</th><th>Segregated receipts</th><th>Expected at count</th><th>Physical count</th><th>Over/(short)</th><th>Location / condition</th><th>Counter / checker / validator</th><th>Remarks</th></tr></thead><tbody>
            @foreach ($session->lines as $line)
                <tr>
                    <td>{{ $line->tag_no }} / {{ $line->stock_number }}</td><td>{{ $line->item_name }} / {{ $line->unit_of_measure }}</td><td>{{ $line->book_quantity }}</td>
                    <td>{{ $line->before_count_issues }} / {{ $line->after_count_issues }}</td><td>{{ $line->segregated_receipts }}</td><td>{{ $line->book_quantity - $line->before_count_issues }}</td>
                    <td>{{ $line->latestEntry?->quantity ?? '____________' }}</td><td>{{ $line->latestEntry === null ? 'Not counted' : $line->latestEntry->quantity - ($line->book_quantity - $line->before_count_issues) }}</td>
                    <td>{{ $line->latestEntry?->location }} / {{ $line->latestEntry?->condition }}</td><td>{{ $line->latestEntry?->counter_name }} / {{ $line->latestEntry?->checker_name }} / {{ $line->latestEntry?->validator_name }}</td><td>{{ $line->latestEntry?->remarks }} @if ($line->latestEntry !== null) Observed {{ $line->latestEntry->observed_quantity ?? $line->latestEntry->quantity }}; {{ $line->latestEntry->after_count_issues_at_observation }} after-count issues added back. @endif</td>
                </tr>
            @endforeach
        </tbody></table>
    @endif
    <div class="signatures">
        <div>{{ $signatures->get('submit') }}<br>Prepared / submitted by</div>
        <div>{{ $session->verified_by_user_id === null ? '' : $signatures->get('verify') }}<br>Verified by</div>
        <div>{{ $signatures->get('approve') }}<br>Approved by</div>
    </div>
    <p>Retain the signed tags, memorandum, and supporting source documents for accounting and audit submission.</p>
</body>
</html>
