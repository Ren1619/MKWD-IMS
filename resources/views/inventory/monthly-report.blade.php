<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $packet->document_no }} - Monthly reports</title>
<style>
@page {size:A4 landscape;margin:12mm;} body {font:11px Arial,sans-serif;color:#111;} h1,h2 {text-align:center;} table {width:100%;border-collapse:collapse;} th,td {border:1px solid #333;padding:5px;text-align:left;vertical-align:top;} .toolbar,.notice {padding:10px;background:#eee;margin-bottom:16px;} .report {break-before:page;} .report:first-of-type {break-before:auto;} .signatures {display:flex;gap:24px;margin-top:40px;break-inside:avoid;} .signatures div {flex:1;border-top:1px solid;padding-top:8px;} .fingerprint {font:9px monospace;overflow-wrap:anywhere;} @media print {.toolbar{display:none;}thead{display:table-header-group;}tr{break-inside:avoid;}}
</style></head><body>
<div class="toolbar"><button onclick="window.print()">Print / Save as PDF</button></div>
@php
$finalized=in_array($packet->status,['approved','transmitted'],true);
$transmittal=$packet->actions->firstWhere('action','transmit');
$signatures=$packet->actions->where('inventory_monthly_report_snapshot_id',$packet->active_snapshot_id);
@endphp
@if($document === 'transmittal')
<h1>{{ $packet->snapshot->payload['entity_name'] }}</h1><h2>Monthly Reports Transmittal Letter</h2>
@if($packet->status !== 'transmitted')<p class="notice">DRAFT TRANSMITTAL - HANDOVER HAS NOT BEEN RECORDED</p>@endif
<p>Reference: {{ $transmittal?->metadata['transmittal_reference'] ?? $packet->document_no }}<br>Date: {{ ($packet->transmitted_at ?? $packet->approved_at)->timezone('Asia/Manila')->format('Y-m-d') }}</p>
<p>To: {{ $transmittal?->metadata['transmittal_recipient'] ?? 'ABD Department Manager / Accounting' }}</p>
<p>We transmit the approved monthly inventory package <strong>{{ $packet->document_no }}</strong> for {{ $packet->period_month->format('F Y') }}, revision {{ $packet->revision }}, for Journal Entry Voucher preparation and audit reference.</p>
<p>Stock master selection: {{ $packet->classification_name }}; {{ $packet->include_zero ? 'with zero balances' : 'without zero balances' }}. Reporting fund label: {{ $packet->fund_cluster }}.</p>
<ul>@foreach($packet->snapshot->payload['reports'] as $report)<li>{{ $report['title'] }} - {{ count($report['rows']) }} row(s)</li>@endforeach</ul>
<p>Approved adjustment references are included in the adjustment register. Journal registers are supporting schedules; Accounting assigns the accounts and JEV.</p>
<p>JEV reference: {{ $transmittal?->metadata['jev_reference'] ?? 'To be assigned by Accounting' }}<br>Handover remarks: {{ $transmittal?->remarks }}</p>
<div class="signatures"><div>{{ $packet->signatories['prepared']['name'] }}<br>Prepared / transmitted by</div><div>________________________<br>Received by / date</div></div>
@else
@foreach($tables as $key=>$table)
<section class="report"><h1>{{ $packet->snapshot->payload['entity_name'] }}</h1><h2>{{ $table['title'] }}</h2>
<p><strong>{{ $packet->document_no }}</strong> | Month: {{ $packet->period_month->format('F Y') }} | Revision: {{ $packet->revision }} | Snapshot: {{ $packet->snapshot->version }} | Status: {{ ucfirst($packet->status) }}</p>
<p>Period: {{ $packet->snapshot->payload['from'] }} to {{ $packet->snapshot->payload['to'] }} | Reporting fund label: {{ $packet->fund_cluster }} | Stock master: {{ $packet->classification_name }}, {{ $packet->include_zero ? 'with zero balances' : 'without zero balances' }}</p>
@if(!$finalized)<p class="notice">WORKING DOCUMENT - MONTHLY PACKAGE HAS NOT BEEN APPROVED</p>@endif
@if(in_array($key,['issue-journal','return-journal'],true))<p>Supporting journal schedule for Accounting JEV preparation; this report does not post a general-ledger entry.</p>@endif
@if(in_array($key,['purchase-orders','job-orders'],true))<p>Amounts show the approved requisition estimate. Refer to the signed PO / JO for contract amounts and terms.</p>@endif
@if($packet->supersedes)<p>Correction of: {{ $packet->supersedes->document_no }} | Reason: {{ $packet->correction_reason }}</p>@endif
<table><thead><tr>@foreach($table['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead><tbody>
@forelse($table['rows'] as $row)<tr>@foreach($row as $cell)<td>{{ $cell ?? '' }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($table['columns']) }}">No approved source records for this period and report.</td></tr>@endforelse
</tbody></table>
<p>Validation and reconciliation evidence: {{ $packet->reconciliation_reference }}<br>Saved snapshot: {{ $packet->snapshot->created_at->timezone('Asia/Manila')->format('Y-m-d H:i') }}</p>
<div class="signatures">@foreach($packet->signatories as $stage=>$person)
@php
$actionName=match($stage){'prepared'=>'prepare','checked'=>'check','noted'=>'note',default=>'approve'};
$decision=$stage === 'prepared' ? $signatures->first(fn ($entry) => in_array($entry->action, ['prepare', 'refresh'], true)) : $signatures->firstWhere('action',$actionName);
@endphp
<div>{{ $person['name'] }}<br>{{ $person['role'] }}<br>{{ $decision?->created_at->timezone('Asia/Manila')->format('Y-m-d H:i') ?? 'Pending' }}<br>{{ $decision?->metadata['signature_reference'] ?? ($stage === 'prepared' ? $packet->reconciliation_reference : '') }}</div>
@endforeach</div>
<p class="fingerprint">Source fingerprint: {{ $packet->snapshot->source_hash }}</p></section>
@endforeach
@endif
</body></html>
