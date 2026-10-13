<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ data_get($notice->metadata, 'notice_no') }} — Notice of Postponement</title>
<style>@page{size:A4;margin:18mm}body{font:13px Arial,sans-serif;line-height:1.5;color:#111}h1{font-size:18px;text-align:center}table{border-collapse:collapse;width:100%}th,td{border:1px solid #333;padding:6px;text-align:left}dl{display:grid;grid-template-columns:160px 1fr;gap:6px}dd{margin:0}.signatures{display:flex;gap:30px;margin-top:30px}.signatures>div{flex:1}button{margin-bottom:16px}@media print{button{display:none}}</style></head><body>
<button onclick="window.print()">Print / Save as PDF</button>
<h1>NOTICE OF POSTPONEMENT OF DELIVERY INSPECTION</h1>
<p>Reference: <strong>{{ data_get($notice->metadata, 'notice_no') }}</strong><br>Date: {{ data_get($notice->metadata, 'notice_date') }}<br>Recorded: {{ $notice->created_at->timezone('Asia/Manila')->format('Y-m-d H:i') }}</p>
<dl><dt>To:</dt><dd>{{ data_get($notice->metadata, 'supplier_contact_name') }}<br>{{ $delivery->supplier_name }}<br>{{ data_get($notice->metadata, 'supplier_address') }}</dd>
<dt>From:</dt><dd>Inspectorate Team</dd><dt>Subject:</dt><dd>Postponement of delivery inspection for PO {{ $delivery->purchase_order_no }}</dd></dl>
<p>This is to formally inform you that the delivery inspection originally set on <strong>{{ str_replace('T', ' ', data_get($notice->metadata, 'original_inspection_at')) }}</strong> at <strong>{{ data_get($notice->metadata, 'original_site') }}</strong> is postponed.</p>
<p>Purchase Order / Contract No.: {{ $delivery->purchase_order_no }}<br>Delivery record: {{ $delivery->document_no }} / {{ $delivery->delivery_reference }}<br>Quantity / Lot reference: {{ data_get($notice->metadata, 'lot_reference') }}</p>
<table><thead><tr><th>Item / project description</th><th>Quantity</th><th>Unit</th></tr></thead><tbody>@foreach($delivery->lines as $line)<tr><td>{{ $line->item_name }}</td><td>{{ $line->quantity_delivered }}</td><td>{{ $line->unit_of_measure }}</td></tr>@endforeach</tbody></table>
<p>The postponement is due to:</p>
@foreach(['no_quorum'=>'Unavailability of required Inspectorate Team members (no quorum)','technical_representative_unavailable'=>'Unavailability of key technical representatives','safety_force_majeure'=>'Force majeure / safety concerns','other'=>'Other'] as $reason=>$description)
<div>{{ data_get($notice->metadata, 'postponement_reason') === $reason ? '[X]' : '[ ]' }} {{ $description }}</div>
@endforeach
<p>Details: {{ $notice->remarks }}</p>
<p>The inspection is tentatively reset to:<br>New proposed date and time: <strong>{{ str_replace('T', ' ', data_get($notice->metadata, 'proposed_inspection_at')) }}</strong><br>Proposed venue / inspection site: <strong>{{ data_get($notice->metadata, 'proposed_site') }}</strong></p>
<p>Please confirm your availability for the rescheduled inspection, or coordinate with the Inspectorate Team / Secretariat for any necessary adjustment.</p>
<p>For guidance and compliance.</p>
<div class="signatures"><div>Very truly yours,<p>____________________________<br>{{ data_get($notice->metadata, 'chair.name') }}<br>MKWD Inspectorate Team Chairperson</p><p>Signed evidence: {{ data_get($notice->metadata, 'chair_signature_reference') }}</p></div><div>Noted:<p>____________________________<br>{{ data_get($notice->metadata, 'authority.name') }}<br>{{ data_get($notice->metadata, 'authority.position') ?? 'Approving Authority / General Manager' }}</p><p>Signed evidence: {{ data_get($notice->metadata, 'authority_signature_reference') }}</p></div></div>
<p>Cc: Inspectorate Team Members; {{ $delivery->receiving_department }}; Procurement Unit; Records/File.</p>
<p>Supplier notification / transmittal evidence: {{ data_get($notice->metadata, 'notice_sent_reference') }}</p>
<p><small>This preserved notice records signed evidence and a tentative schedule. Supplier confirmation and actual inspection remain staff actions; acceptance and inventory posting require the delivery workflow.</small></p>
</body></html>
