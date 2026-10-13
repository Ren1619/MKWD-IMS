@php
$action=$delivery->actions->firstWhere('action','inspect');
$evaluation=$action?->metadata ?? $delivery->inspection ?? [];
$source=data_get($evaluation,'source_snapshot',[]);
$sourceLines=data_get($source,'lines',$delivery->lines->toArray());
$observations=collect(data_get($action?->metadata,'lines',[]))->keyBy('line_id');
@endphp
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ data_get($evaluation,'evaluation_no','Legacy inspection') }} — Inspection &amp; Evaluation Report</title>
<style>@page{size:A4 landscape;margin:12mm}body{font:12px Arial,sans-serif;color:#111;line-height:1.5}h1{text-align:center;font-size:20px}table{width:100%;border-collapse:collapse;margin:14px 0}th,td{border:1px solid #333;padding:6px;text-align:left;vertical-align:top}.signatures{display:flex;gap:40px;margin-top:25px}.signatures>div{flex:1}.notice{padding:10px;border:1px solid #333}@media print{button{display:none}}</style></head><body><button onclick="window.print()">Print / Save as PDF</button>
<h1>INSPECTION &amp; EVALUATION REPORT</h1>

@if(!isset($evaluation['evaluation_no']))<p class="notice">LEGACY INSPECTION — Structured evaluation dates, checklist and signatory evidence were not recorded. Blank fields do not attest completion.</p>@endif
<p>Supplier: {{ data_get($source,'supplier_name',$delivery->supplier_name) }}<br>IER No.: {{ $evaluation['evaluation_no'] ?? 'Not recorded (legacy)' }}<br>Evaluation date: {{ $evaluation['evaluation_date'] ?? 'Not recorded' }}<br>Delivery record: {{ data_get($source,'document_no',$delivery->document_no) }}</p>
<table><thead><tr><th>Reference document</th><th>Number</th><th>Date</th></tr></thead><tbody>
<tr><td>Purchase Order / Job Order</td><td>{{ data_get($source,'purchase_order_no',$delivery->purchase_order_no) }}</td><td>{{ $evaluation['order_reference_date'] ?? 'Not recorded' }}</td></tr>
<tr><td>Invoice</td><td>{{ data_get($source,'invoice_number',$delivery->invoice_number) }}</td><td>{{ $evaluation['invoice_date'] ?? 'Not recorded' }}</td></tr>
<tr><td>Delivery receipt</td><td>{{ data_get($source,'delivery_reference',$delivery->delivery_reference) }}</td><td>{{ $evaluation['delivery_receipt_date'] ?? 'Not recorded' }}</td></tr></tbody></table>
<h2>INSPECTION ACTIVITIES</h2><p>Site inspection: {{ $evaluation['inspection_site'] ?? 'Not recorded' }}<br>Signed inspection report: {{ $evaluation['inspection_reference'] ?? 'Not recorded' }}</p>
<p>Ticked boxes indicate non-conformance to specifications; an unticked box in a legacy report is not proof of conformance.</p>
<table><thead><tr><th>Item / specifications</th><th>Delivered / conforming / rejected</th><th>Non-conformance checklist</th><th>Non-conformance / discrepancy findings</th></tr></thead><tbody>
@foreach($sourceLines as $line)
@php $observed=$observations->get($line['id']); $categories=data_get($observed,'nonconformities'); @endphp
<tr><td>{{ $line['item_name'] }}<br>{{ $line['specifications'] }}</td><td>{{ $line['quantity_delivered'] }} / {{ data_get($observed,'quantity_accepted', $line['quantity_accepted'] ?? 'Not recorded') }} / {{ $line['quantity_delivered']-(data_get($observed,'quantity_accepted', $line['quantity_accepted'] ?? 0)) }} {{ $line['unit_of_measure'] }}</td><td>
@if($categories === null)Not recorded (legacy)
@else
@foreach(['packaging'=>'Packing / packaging','label'=>'Label (minimum)','count'=>'Count','brand_model'=>'Brand / model'] as $key=>$description)<div>{{ in_array($key,$categories,true)?'[X]':'[ ]' }} {{ $description }}</div>@endforeach
@endif</td><td>{{ data_get($observed,'findings', $line['findings']) }}</td></tr>@endforeach</tbody></table>
<p><strong>EVALUATION / FINDINGS:</strong><br>{{ $evaluation['evaluation_findings'] ?? $action?->remarks ?? 'Not recorded' }}</p>
<p><strong>OTHERS / COMMENTS (sampling method, formula, etc.):</strong><br>{{ $evaluation['evaluation_comments'] ?? 'Not recorded' }}</p>
<div class="signatures"><div>Inspected / evaluated by:<p>________________________<br>{{ data_get($evaluation,'evaluator.name','Not recorded') }}<br>{{ data_get($evaluation,'evaluator.position') }}</p><p>Signed evidence: {{ $evaluation['evaluator_signature_reference'] ?? 'Not recorded' }}</p></div><div>Noted by:<p>________________________<br>{{ data_get($evaluation,'evaluation_chair.name',$evaluation['chairperson_name'] ?? 'Not recorded') }}<br>Chairperson, Inspectorate Team</p><p>Signed evidence: {{ $evaluation['evaluation_chair_signature_reference'] ?? 'Not recorded' }}</p></div></div>
<p><small>Inspection and evaluation do not post usable inventory. Conforming quantities require independent signed acceptance in the delivery workflow. Physical inspection and original signatures remain staff actions.</small></p></body></html>
