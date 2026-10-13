<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ data_get($authorization->metadata,'return_notice_no') }} — Return to Supplier Notice</title>
<style>@page{size:A4 landscape;margin:12mm}body{font:12px Arial,sans-serif;line-height:1.5;color:#111}h1{text-align:center;font-size:20px}table{width:100%;border-collapse:collapse;margin:15px 0}th,td{border:1px solid #333;padding:7px;text-align:left}.signatures{display:flex;gap:20px;margin-top:35px}.signatures>div{flex:1}@media print{button{display:none}}</style></head><body><button onclick="window.print()">Print / Save as PDF</button>
@php $evidence=$authorization->metadata; $source=data_get($evidence,'return_source_snapshot',[]); $handover=$delivery->actions->firstWhere('action','record_supplier_return'); @endphp
<h1>RETURN TO SUPPLIER NOTICE</h1>
<p>Reference No.: {{ data_get($evidence,'return_notice_no') }}<br>Date: {{ data_get($evidence,'return_notice_date') }}<br>Recorded: {{ $authorization->created_at->timezone('Asia/Manila')->format('Y-m-d H:i') }}</p>
<h2>Procurement details</h2>
<p>Supplier Name: {{ data_get($source,'supplier_name') }}<br>Purchase Order No.: {{ data_get($source,'purchase_order_no') }}<br>Sales Invoice Number: {{ data_get($source,'invoice_number') }}<br>Delivery Receipt Number: {{ data_get($source,'delivery_reference') }}<br>Purpose: {{ data_get($evidence,'return_purpose') }}</p>
<h2>Details of non-conforming items</h2>
<table><thead><tr><th>No.</th><th>Item description</th><th>Qty.</th><th>Unit</th><th>Non-conformance</th></tr></thead><tbody>
@php $number=0; @endphp
@foreach(data_get($source,'lines',[]) as $line)
@php $rejected=$line['quantity_delivered']-($line['quantity_accepted'] ?? 0); @endphp
@continue($rejected <= 0)
<tr><td>{{ ++$number }}</td><td>{{ $line['item_name'] }}<br>{{ $line['specifications'] }}</td><td>{{ $rejected }}</td><td>{{ $line['unit_of_measure'] }}</td><td>{{ $line['findings'] }}</td></tr>
@endforeach</tbody></table>
<p>Reason for Return: {{ data_get($evidence,'return_reason') }}<br>Action Required: {{ data_get($evidence,'return_action_required') }}</p>
<div class="signatures">@foreach(['return_preparer'=>'Prepared by / Sr. Supply Officer','return_end_user'=>'Conformed by / End User','return_procurement_head'=>'Conformed by / Procurement Head','return_chair'=>'Approved by / MKWD Inspectorate Team Chairperson'] as $role=>$title)<div>____________________________<br>{{ data_get($evidence,$role.'.name') }}<br>{{ $title }}<p>Signed evidence: {{ data_get($evidence,$role.'_signature_reference') }}</p></div>@endforeach</div>
<p>Physical handover date: {{ data_get($handover?->metadata,'returned_at','Pending handover') }}<br>Supplier acknowledgement reference: {{ data_get($handover?->metadata,'return_reference','Pending handover') }}</p>
<p><small>Signed notice authorization and physical supplier handover are separate records. Only rejected delivery quantities are returned; this notice does not deduct conforming warehouse stock. Physical inspection, original signatures and supplier handover remain staff actions.</small></p>
</body></html>
