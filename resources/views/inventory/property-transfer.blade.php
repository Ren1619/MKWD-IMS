<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>{{ $transfer->document_no }} — Property Transfer Report</title>
<style>body{font-family:Arial,sans-serif;font-size:12px;margin:30px}h1{text-align:center;font-size:20px}table{border-collapse:collapse;width:100%;margin:16px 0}th,td{border:1px solid #333;padding:8px;text-align:left}.status{text-align:center;font-weight:bold}.signatures{display:flex;gap:24px}.signatures>div{flex:1}button{margin:12px}@media print{button{display:none}@page{size:A4 landscape;margin:12mm}body{margin:0}}</style></head>
<body>
<button onclick="window.print()">Print</button>
<h1>PROPERTY TRANSFER REPORT</h1>
<p class="status">{{ strtoupper($transfer->status) }}@if($transfer->status !== 'completed') — NOT A COMPLETED HANDOVER @endif</p>
<p>PTR No.: <strong>{{ $transfer->document_no }}</strong> · Requested date: {{ $transfer->desired_date->toDateString() }}</p>
<p>From Accountable Officer: {{ $transfer->source_snapshot['recipient_name'] }} · {{ $transfer->source_snapshot['recipient_office'] ?? '' }}</p>
<p>To Accountable Officer: {{ $transfer->recipient_snapshot['name'] }} · {{ $transfer->recipient_snapshot['office'] ?? '' }}</p>
<p>Transfer type: {{ ucfirst($transfer->transfer_type) }} · Destination: {{ $transfer->destination_location }}</p>
<table><thead><tr><th>Date acquired</th><th>Property No.</th><th>Description / serial number</th><th>Quantity</th><th>Unit acquisition cost</th><th>Condition / remarks</th></tr></thead>
<tbody><tr><td>{{ substr($transfer->source_snapshot['acquisition_date'] ?? '',0,10) }}</td><td>{{ $transfer->source_snapshot['property_number'] ?? '' }}</td><td>{{ $transfer->source_snapshot['name'] ?? '' }} / {{ $transfer->source_snapshot['serial_number'] ?? '' }}</td><td>{{ $transfer->source_snapshot['quantity_per_property_card'] ?? '' }}</td><td>{{ $transfer->source_snapshot['acquisition_cost'] ?? '' }}</td><td>{{ data_get($transfer->actions->firstWhere('action','complete'), 'evidence.condition_status', $transfer->source_snapshot['condition_status'] ?? '') }}</td></tr></tbody></table>
<p>Reason for transfer: {{ $transfer->reason }}</p>
<p>Previous PAR/ICS: {{ $transfer->source_snapshot['document_no'] }} · Completed posting: {{ $transfer->completed_at?->toDateTimeString() ?? 'Not posted' }}</p>
<p>Resulting PAR/ICS: {{ $transfer->isExternal() ? 'Receiving agency accountability is evidenced by its signed receipt; no internal PAR/ICS is issued.' : ($transfer->replacementDocument?->document_no ?? 'Not issued') }}</p>
@php($approval = $transfer->actions->firstWhere('action','approve'))
@php($handover = $transfer->actions->firstWhere('action','complete'))
@if($transfer->isExternal())
<p>Transfer authority: {{ data_get($approval,'evidence.authority_reference','Not recorded') }} · Accounting clearance: {{ data_get($approval,'evidence.accounting_clearance_reference','Not recorded') }}</p>
<p>Accounting Unit posting reference: {{ data_get($handover,'evidence.accounting_reference','Not recorded') }}</p>
@endif
<div class="signatures">
<div><strong>Released / issued by</strong><p>{{ $transfer->source_snapshot['recipient_name'] }}</p><p>{{ $transfer->source_snapshot['recipient_position'] ?? '' }}</p><p>Signature: ____________________</p><p>Evidence: {{ data_get($handover,'evidence.release_reference','Not recorded') }}</p></div>
<div><strong>Received by</strong><p>{{ $transfer->recipient_snapshot['name'] }}</p><p>{{ $transfer->recipient_snapshot['position'] ?? '' }}</p><p>Signature: ____________________</p><p>Evidence: {{ data_get($handover,'evidence.receipt_reference','Not recorded') }}</p></div>
<div><strong>Approved by</strong><p>{{ $approval?->actor_name ?? 'Not approved' }}</p><p>Date: {{ $approval?->created_at?->toDateString() ?? '' }}</p><p>Signature: ____________________</p></div>
</div>
<p>Joint inspection reference: {{ data_get($handover,'evidence.inspection_reference','Not recorded') }} · Physical handover date: {{ data_get($handover,'evidence.handover_date','Not recorded') }}</p>
<table><thead><tr><th>Recorded at</th><th>Decision</th><th>IMS officer</th><th>Attestation</th></tr></thead><tbody>
@foreach($transfer->actions as $action)<tr><td>{{ $action->created_at }}</td><td>{{ ucfirst($action->action) }}</td><td>{{ $action->actor_name }}</td><td>{{ $action->attestation }}</td></tr>@endforeach
</tbody></table>
<p>Physical signatures and inspection remain staff actions. Evidence references and IMS attestations record their completion.</p>
</body></html>
