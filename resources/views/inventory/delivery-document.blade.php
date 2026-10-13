<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $delivery->document_no }} - {{ $document }}</title>
<style>
@page { size: A4 landscape; margin: 12mm; } body {font: 11px Arial,sans-serif;color:#111;} h1,h2 {text-align:center;} table {width:100%;border-collapse:collapse;} th,td {border:1px solid #333;padding:6px;text-align:left;vertical-align:top;} .toolbar,.notice {padding:10px;background:#eee;margin-bottom:16px;} .signatures {display:flex;gap:28px;margin-top:40px;} .signatures div {flex:1;border-top:1px solid;padding-top:8px;} @media print {.toolbar{display:none;}}
</style>
</head>
<body>
@php $returnAuthorization=$delivery->actions->firstWhere('action','authorize_supplier_return'); @endphp
<div class="toolbar"><button onclick="window.print()">Print / Save as PDF</button></div>
<h1>{{ config('app.name') }}</h1>
<h2>{{ match($document) {'iar'=>'Inspection and Acceptance Report','receiving-report'=>'Receiving Report',default=>'Return to Supplier Notice'} }}</h2>
<p><strong>{{ match($document) {'iar'=>$delivery->inspection_acceptance_no ?? 'IAR pending inspection','receiving-report'=>$delivery->receiving_report_no ?? 'RR pending acceptance',default=>$delivery->supplier_return_no} }}</strong> | {{ $delivery->document_no }} | Status: {{ ucfirst($delivery->status) }}</p>
<p>PR: {{ $delivery->procurementRequest->pr_no }} | PO: {{ $delivery->purchase_order_no }} | Supplier: {{ $delivery->supplier_name }}</p>
<p>Invoice: {{ $delivery->invoice_number }} | Delivery Receipt: {{ $delivery->delivery_reference }} | Received: {{ $delivery->received_at->timezone('Asia/Manila')->format('Y-m-d H:i') }}</p>
<p>NOA: {{ $delivery->noa_reference ?? 'See document basis' }} | NTP: {{ $delivery->ntp_reference ?? 'See document basis' }} | Department: {{ $delivery->receiving_department }}</p>
<p>PO / TOR / document verification: {{ $delivery->order_document_basis }}<br>Notification: {{ $delivery->notification_reference }}<br>Packaging findings: {{ $delivery->packaging_findings }}</p>
@if ($document !== 'supplier-return' && $delivery->status !== 'accepted')
<p class="notice">WORKING DOCUMENT - NO ACCEPTED STOCK OR PROPERTY POSTING</p>
@endif
@if ($document === 'supplier-return')
@if(!$returnAuthorization)<p class="notice">WORKING / LEGACY NOTICE — Four-signatory authorization evidence has not been recorded. This printout does not attest signed approval.</p>@endif
<p>REJECTED MATERIALS - SEGREGATE AND RETURN TO SUPPLIER FOR CORRECTION / REPLACEMENT</p>
<p>Notification reference: {{ $delivery->inspection['supplier_notification_reference'] ?? '' }} | Physical return: {{ $delivery->supplier_returned_at?->timezone('Asia/Manila')->format('Y-m-d') ?? 'Pending handover' }}</p>
<p>Signed notice date: {{ data_get($returnAuthorization?->metadata,'return_notice_date','Not recorded') }}</p>
@endif
<table><thead><tr><th>Material / specifications</th><th>Unit</th><th>Delivered</th><th>{{ $document === 'supplier-return' ? 'For return' : 'Accepted' }}</th><th>Unit cost</th><th>Value</th><th>Findings / destination / references</th></tr></thead><tbody>
@foreach ($delivery->lines as $line)
    @php
        $quantity = $document === 'supplier-return' ? $line->quantity_delivered - ($line->quantity_accepted ?? 0) : $line->quantity_accepted;
    @endphp
    @continue($document === 'supplier-return' && $quantity === 0)
    <tr><td>{{ $line->item_name }}<br>{{ $line->specifications }}
    @if($line->expiration_date)<br>Expiry: {{ $line->expiration_date->format('Y-m-d') }}@endif
    </td><td>{{ $line->unit_of_measure }}</td><td>{{ $line->quantity_delivered }}</td><td>{{ $quantity ?? 'Pending' }}</td><td>{{ number_format((float)$line->unit_cost,2) }}</td><td>{{ $quantity === null ? 'Pending' : number_format((float)(string)\Brick\Math\BigDecimal::of($line->unit_cost)->multipliedBy($quantity),2) }}</td><td>{{ ucfirst($line->condition ?? 'Pending') }}<br>{{ $line->findings }}<br>{{ $line->storage_location }}<br>{{ $line->disposition_reference }}</td></tr>
@endforeach
</tbody></table>
<p>Accepted / posted: {{ $delivery->accepted_at?->timezone('Asia/Manila')->format('Y-m-d H:i') ?? 'Pending acceptance' }} | Inspection evidence: {{ $delivery->inspection['inspection_reference'] ?? 'Pending' }}</p>
@php
$recorder=$delivery->actions->firstWhere('action','receive');
$inspector=$delivery->actions->firstWhere('action','inspect');
$acceptor=$delivery->actions->firstWhere('action','accept');
$return=$delivery->actions->firstWhere('action','record_supplier_return');
$endorsement=$delivery->actions->firstWhere('action','endorse');
@endphp
@if($document === 'supplier-return')
<p>Purpose: {{ data_get($returnAuthorization?->metadata,'return_purpose','Not recorded') }}<br>Reason for return: {{ data_get($returnAuthorization?->metadata,'return_reason','See recorded item discrepancies; signed reason not recorded.') }}<br>Action required: {{ data_get($returnAuthorization?->metadata,'return_action_required','Pending signed supplier-return notice.') }}</p>
<div class="signatures">@foreach(['return_preparer'=>'Prepared by / Sr. Supply Officer','return_end_user'=>'Conformed by / End User','return_procurement_head'=>'Conformed by / Procurement Head','return_chair'=>'Approved by / Inspectorate Chairperson'] as $role=>$title)<div>{{ data_get($returnAuthorization?->metadata,$role.'.name','Not recorded') }}<br>{{ $title }}<br>Signature: ____________________<br>Evidence: {{ data_get($returnAuthorization?->metadata,$role.'_signature_reference','Not recorded') }}</div>@endforeach</div>
<p>Return acknowledgement: {{ $return?->metadata['return_reference'] ?? 'Pending' }}</p>
@else
<div class="signatures"><div>{{ $recorder?->actor_name }}<br>Prepared by / signature</div><div>{{ $delivery->inspection['vice_chairperson_name'] ?? '' }}<br>Certified by Vice-Chairperson / signature</div><div>{{ $delivery->inspection['chairperson_name'] ?? '' }}<br>Certified by Chairperson / signature</div><div>{{ $delivery->inspection['end_user_name'] ?? '' }}<br>Validated and accepted by End User / signature</div></div>
<p>COA representative / witness: {{ $delivery->inspection['coa_witness_name'] ?? '' }} | Acceptance recorded by: {{ $acceptor?->actor_name }} | Signed acceptance reference: {{ $acceptor?->metadata['signed_acceptance_reference'] ?? 'Pending' }}</p>
@endif
@if($endorsement)<p>Pre-audit / accounting endorsement: {{ $endorsement->metadata['endorsement_reference'] ?? '' }} | Recipient: {{ $endorsement->metadata['endorsement_recipient'] ?? '' }} | {{ $endorsement->created_at->timezone('Asia/Manila')->format('Y-m-d H:i') }}</p>@endif
</body></html>
