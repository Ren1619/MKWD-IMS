<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Batch label - {{ $batch->item->stock_number }}</title>
<style>body{font:14px Arial,sans-serif;margin:24px;color:#111}.label{border:2px solid #111;max-width:620px;padding:20px;overflow-wrap:anywhere}h1{font-size:24px;margin:8px 0}h2{font-size:18px;margin:8px 0}dt{font-weight:bold}dd{margin:0 0 10px}button{margin-bottom:15px}@media print{body{margin:0}button{display:none}.label{break-inside:avoid}}</style></head><body>
<button type="button" onclick="window.print()">Print label</button>
@php
    $placement = $batch->latestPlacement;
    $expired = $batch->expiration_date !== null && $batch->expiration_date->toDateString() < $printedAt->toDateString();
@endphp
<article class="label"><p>{{ config('app.entity_name', 'Metro Kidapawan Water District') }}</p><h1>{{ $batch->item->stock_number }}</h1><h2>{{ $batch->item->name }}</h2><p>Batch {{ $batch->batch_number }} / Unit: {{ $batch->item->unit_of_measure }}</p>
<dl><dt>Receipt / return source</dt><dd>{{ $batch->reference_no ?? 'Original reference not recorded' }} / {{ $batch->source ?? 'Source not specified' }}</dd>
<dt>Posted receipt date</dt><dd>{{ $batch->received_at->toDateString() }}</dd><dt>Expiry date</dt><dd>{{ $batch->expiration_date?->toDateString() ?? 'Not specified' }}{{ $expired ? ' — EXPIRED: refer for condition review' : '' }}</dd>
<dt>Storage location</dt><dd>{{ $placement ? implode(' / ', array_filter([$placement->warehouse, $placement->area, $placement->rack, $placement->bin])) : 'Storage not yet recorded' }}</dd>
@if($placement)<dt>Confirmed by / date</dt><dd>{{ $placement->recorded_by_name }} / {{ $placement->created_at->timezone('Asia/Manila')->format('Y-m-d H:i') }}</dd><dt>Counter-check evidence</dt><dd>{{ $placement->source_evidence_reference }}</dd>@endif
<dt>Remaining quantity at label printing</dt><dd>{{ $batch->quantity_remaining }} {{ $batch->item->unit_of_measure }}</dd></dl>
<p>Printed {{ $printedAt->format('Y-m-d H:i') }} (Asia/Manila). This label identifies storage; use approved source records for stock movements and release.</p></article></body></html>
