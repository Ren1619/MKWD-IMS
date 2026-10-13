<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>{{ $document->document_no }} — Waste Materials Report</title>
<style>body{font-family:Arial,sans-serif;font-size:12px;margin:30px}h1{text-align:center;font-size:20px}table{border-collapse:collapse;width:100%;margin:15px 0}th,td{border:1px solid #333;padding:7px;text-align:left}.signatures{display:flex;gap:20px}.signatures>div{flex:1}.status{text-align:center;font-weight:bold}button{margin:10px}@media print{button{display:none}body{margin:0}@page{size:A4 landscape;margin:12mm}}</style></head><body>
<button onclick="window.print()">Print</button><h1>WASTE MATERIALS REPORT</h1>
<p class="status">{{ strtoupper($document->status) }} @if($document->status !== 'completed') — NOT A COMPLETED DISPOSITION CERTIFICATE @endif</p>
@php($approval = $document->actions->firstWhere('action','approve'))
@php($completion = $document->actions->firstWhere('action','complete'))
<p>Place of Storage: {{ $document->place_of_storage }} · WMR No.: {{ $document->document_no }}</p>
<p>Date: {{ data_get($completion,'evidence.disposition_date',$document->created_at->toDateString()) }} · Posted: {{ $document->completed_at?->toDateTimeString() ?? 'Not posted' }}</p>
<table><thead><tr><th>Item</th><th>Qty.</th><th>Unit</th><th>Description / source</th><th>O.R. No.</th><th>Amount (proceeds)</th></tr></thead><tbody>
@foreach($document->lines as $line)<tr><td>{{ $loop->iteration }}</td><td>{{ $line->quantity }}</td><td>{{ $line->unit }}</td><td>{{ $line->description }}<br>{{ $line->source_reference }} @if($line->source_snapshot)<br>{{ $line->source_snapshot['property_number'] ?? '' }} / {{ $line->source_snapshot['serial_number'] ?? '' }} @else <br>Standalone waste; no parent-asset derecognition. @endif</td><td>{{ $line->or_number ?? 'Not recorded' }}</td><td>{{ $line->proceeds !== null ? number_format((float) $line->proceeds, 2) : 'Not posted' }}</td></tr>@endforeach
</tbody></table>
<p>Particulars / reason: {{ $document->reason }}</p>
<div class="signatures"><div><strong>Certified Correct</strong><p>{{ $document->certifier_snapshot['name'] }}</p><p>PPE / Property Custodian</p><p>Signature: ____________________</p><p>Evidence: {{ $document->certification_reference }}</p></div>
<div><strong>Disposal Approved</strong><p>{{ data_get($approval,'evidence.chair.name','Not approved') }}</p><p>Disposal Committee Chairperson</p><p>Signature: ____________________</p><p>Authority: {{ data_get($approval,'evidence.approval_no','Not recorded') }} · {{ data_get($approval,'evidence.approval_date','') }}</p></div></div>
<h2>CERTIFICATE OF INSPECTION</h2>
<p>@if($completion)The enumerated materials were disposed of by {{ str_replace('_',' ',$document->method) }} as recorded below.@else Physical disposition and witness signatures have not been recorded. @endif</p>
<p>Method: {{ $document->method === 'destroyed' ? '[X]' : '[ ]' }} Destroyed · {{ $document->method === 'private_sale' ? '[X]' : '[ ]' }} Sold at private sale · {{ $document->method === 'public_auction' ? '[X]' : '[ ]' }} Sold at public auction</p>
<div class="signatures">@foreach(['inspector'=>'Property Inspector','internal_control_witness'=>'Internal Control Witness','accounting_witness'=>'Accounting Witness'] as $key=>$title)<div><strong>{{ $title }}</strong><p>{{ data_get($completion,'evidence.'.$key.'.name','Not recorded') }}</p><p>{{ data_get($completion,'evidence.'.$key.'.position','') }}</p><p>Signature: ____________________</p></div>@endforeach</div>
<p>Inspection certificate: {{ data_get($completion,'evidence.inspection_certificate_reference','Not recorded') }} · Internal Control signature: {{ data_get($completion,'evidence.internal_control_signature_reference','Not recorded') }} · Accounting signature: {{ data_get($completion,'evidence.accounting_signature_reference','Not recorded') }}</p>
<p>Disposition evidence: {{ data_get($completion,'evidence.disposition_reference','Not recorded') }} · Accounting posting / confirmation: {{ data_get($completion,'evidence.accounting_reference','Not recorded') }}</p>
<table><thead><tr><th>Recorded at</th><th>Decision</th><th>IMS officer</th><th>Attestation</th></tr></thead><tbody>@foreach($document->actions as $action)<tr><td>{{ $action->created_at }}</td><td>{{ ucfirst($action->action) }}</td><td>{{ $action->actor_name }}</td><td>{{ $action->attestation }}</td></tr>@endforeach</tbody></table>
<p>Physical inspection, sale or destruction, and signatures remain staff actions. This report records their evidence; IMS does not issue a financial JEV.</p>
</body></html>
