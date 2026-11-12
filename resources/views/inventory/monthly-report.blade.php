<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $packet->document_no }} - Monthly reports</title>
<style>
@page {size:{{ isset($tables['stock-master']) ? 'legal landscape' : 'A4 landscape' }};margin:0;} *{box-sizing:border-box} body{font:10px Arial,sans-serif;color:#111;margin:0} h1,h2{text-align:center} .letterhead{display:block;width:100%;height:27mm;object-fit:contain;margin-bottom:3mm}.run-info{font-size:9px;line-height:1.5}.report-title{font-size:15px;margin:1mm 0 0;text-transform:uppercase}.report-period{text-align:center;font-size:11px;margin:0 0 4mm}.report-meta{display:flex;justify-content:space-between;font-size:9px;margin:3mm 0}.report{break-before:page}.report:first-of-type{break-before:auto}table{width:100%;border-collapse:collapse}th,td{padding:4px;vertical-align:top;text-align:left}thead th{border:1px solid #777;text-align:center;font-size:9px;font-weight:normal;vertical-align:middle}.stock-table{table-layout:fixed;font-size:9px}.stock-table td{border:0;padding:3px 2px}.numeric{text-align:right;white-space:nowrap}.classification td{padding-top:5px;font-weight:bold;font-size:9px}.total td{border-top:1px solid #222;border-bottom:3px double #222;font-weight:bold;padding-top:5px}.summary{margin-top:8mm;width:80%;font-size:9px}.summary th{border:0;text-align:right;font-weight:normal}.summary th:first-child{text-align:left}.summary td{border:0;text-align:right}.summary td:first-child{text-align:left}.summary tfoot td{border-top:1px solid #222;border-bottom:1px solid #222}.signatures{display:flex;gap:25px;margin-top:12mm;break-inside:avoid}.signatures>div{flex:1;font-size:10px}.signature-name{display:block;margin-top:9mm;font-weight:bold;text-transform:uppercase}.signature-role{display:block;font-size:9px;margin-top:2px}.signature-evidence{display:block;color:#555;font-size:8px;margin-top:3px}.footer{display:flex;justify-content:space-between;border-top:1px solid #555;padding-top:3mm;margin-top:10mm;font-size:8px}.toolbar,.notice{padding:8px;background:#eee;margin-bottom:12px}.fingerprint{font:8px monospace;overflow-wrap:anywhere;color:#666;margin-top:4mm}.generic-table td{border-bottom:1px solid #ddd}.audit-meta{font-size:8px;color:#555}.screen-only{display:block}@media print{.toolbar,.screen-only{display:none}thead{display:table-header-group}tr{break-inside:avoid}.notice{background:none;border:1px solid #777}}

/* Keep the on-screen preview and the printed sheet visually consistent. */
body{font-family:Arial,Helvetica,sans-serif;line-height:1.35;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.report-title{font-size:16px;letter-spacing:1.2px;font-weight:700;color:#172b4d}
.report-period{font-size:10px;margin-top:2px;color:#444}
.report-meta{padding:7px 0;border-top:1px solid #b8c1ce;border-bottom:1px solid #b8c1ce;gap:15px;line-height:1.5}
.run-info{color:#555;font-variant-numeric:tabular-nums}
thead th{background:#f0f3f7;border-color:#a3adba;padding:6px 3px;color:#172b4d;font-size:8px;font-weight:700;letter-spacing:.2px}
.stock-table td{padding:5px 3px;line-height:1.4}.stock-table .classification td{background:#f4f6f9;border-bottom:1px solid #d2d8e0;padding:6px 4px;color:#172b4d;letter-spacing:.4px}
.numeric{font-variant-numeric:tabular-nums;font-size:8px}.stock-table tbody tr:not(.classification):not(.total) td{border-bottom:1px solid #e4e7ec}
.total td{background:#f8f9fb;padding-top:7px;padding-bottom:7px}.summary{width:86%;margin-top:7mm}.summary th{background:none;border-bottom:1px solid #444;color:#333;font-size:9px;letter-spacing:0}.summary th,.summary td{padding:5px 4px}.summary tfoot td{font-weight:700}
.signatures{gap:7mm;margin-top:12mm}.signatures>div{min-width:0}.signature-name{padding-bottom:4px;border-bottom:1px solid #7d8794;min-height:17px;font-size:10px;letter-spacing:.2px}.signature-role{line-height:1.4;min-height:25px;color:#444}.signature-evidence{line-height:1.5;overflow-wrap:anywhere}
.footer{color:#555;letter-spacing:.2px}.notice{font-size:9px;border:1px solid #c0c7d0;border-left:3px solid #66768b;background:#f5f6f8;line-height:1.5}.generic-table{font-size:9px}.generic-table td{padding:6px 4px;overflow-wrap:anywhere}.generic-table tbody tr:nth-child(even){background:#fafbfc}
.summary,.signatures{break-inside:avoid}h2{break-after:avoid}tfoot{display:table-row-group}
.toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;border:1px solid #d5dce5;border-radius:10px;padding:14px 18px;color:#4b5563;font:12px Arial,sans-serif}.toolbar button{border:0;border-radius:6px;background:#172b4d;color:white;padding:10px 18px;cursor:pointer;font-weight:600}.toolbar p{margin:0}
@media screen{html{background:#edf0f5}body{max-width:1160px;margin:28px auto;padding:24px}.report{background:white;padding:30px 32px;margin:24px 0;box-shadow:0 3px 20px #172b4d15;border:1px solid #dde2ea;border-radius:4px;min-height:690px}}
@media print{body{font-size:9px;padding:0}body.transmittal-page{padding:10mm 8mm}.report{padding:10mm 8mm;box-decoration-break:clone;-webkit-box-decoration-break:clone;border:0;margin:0;box-shadow:none;border-radius:0}.toolbar,.screen-only{display:none}.letterhead{height:24mm}.report-title{font-size:14px}.signature-name{margin-top:9mm}.stock-table thead th{padding:5px 2px}.stock-table td{padding:4px 2px}.report-meta{font-size:8px}.report-period{margin-bottom:3mm}}
</style></head><body class="{{ $document === 'transmittal' ? 'transmittal-page' : '' }}">
<x-print-layout>
<div class="toolbar"><div><strong>Monthly inventory reports</strong><p>{{ isset($tables['stock-master']) ? 'Legal (8.5 x 14 in), landscape' : 'A4, landscape' }} / Review the saved report before printing.</p></div><button onclick="window.print()">Print / Save as PDF</button></div>
@php
$finalized=in_array($packet->status,['approved','transmitted'],true);
$transmittal=$packet->actions->firstWhere('action','transmit');
$signatures=$packet->actions->where('inventory_monthly_report_snapshot_id',$packet->active_snapshot_id);
@endphp
@if($document === 'transmittal')
<h1>Monthly Reports Transmittal Letter</h1>
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
<section class="report">
<h2 class="report-title" aria-label="{{ $table['title'] }}">{{ $key === 'stock-master' ? 'STOCKMASTER LIST' : $table['title'] }}</h2>
<div class="run-info">Run Date: {{ now('Asia/Manila')->format('m/d/Y') }}<br>Run Time: {{ now('Asia/Manila')->format('h:i:s A') }}</div>
<p class="report-period">Made As Of: {{ $packet->period_month->copy()->endOfMonth()->format('F d, Y') }}</p>
<div class="report-meta"><span>{{ $packet->document_no }} / Revision {{ $packet->revision }} / {{ ucfirst($packet->status) }}</span><span>Fund: {{ $packet->fund_cluster }} ? {{ $packet->classification_name }}</span></div>
@if(!$finalized)<p class="notice">WORKING DOCUMENT - MONTHLY PACKAGE HAS NOT BEEN APPROVED</p>@endif
@if(in_array($key,['issue-journal','return-journal'],true))<p>Supporting journal schedule for Accounting JEV preparation; this report does not post a general-ledger entry.</p>@endif
@if(in_array($key,['purchase-orders','job-orders'],true))<p>Amounts show the approved requisition estimate. Refer to the signed PO / JO for contract amounts and terms.</p>@endif
@if($packet->supersedes)<p>Correction of: {{ $packet->supersedes->document_no }} | Reason: {{ $packet->correction_reason }}</p>@endif
@if($key === 'stock-master')
@php
$groups = collect($table['rows'])->groupBy(fn ($row) => $row[2]);
$amounts = $table['amounts'] ?? [];
$money = fn ($value) => $value === null ? '?' : number_format((float) $value, 2);
$sumMoney = fn ($rows, $field) => $rows->contains(fn ($row) => !isset($amounts[$row[0]][$field])) ? null : $rows->sum(fn ($row) => (float) $amounts[$row[0]][$field]);
@endphp
<table class="stock-table">
<colgroup><col style="width:3%"><col style="width:8%"><col style="width:18%"><col style="width:4%">@for($i=0;$i<6;$i++)<col style="width:3.5%"><col style="width:7.5%">@endfor</colgroup>
<thead><tr><th rowspan="2">No.</th><th rowspan="2">Item Code</th><th rowspan="2">Item Description</th><th rowspan="2">Unit</th>@foreach(['BEGINNING BALANCE','RECEIVED','RELEASED','RETURNED','ADJUSTMENT','ENDING BALANCE'] as $heading)<th colspan="2">{{ $heading }}</th>@endforeach</tr><tr>@for($i=0;$i<6;$i++)<th>QTY.</th><th>AMOUNT</th>@endfor</tr></thead>
<tbody>
@php
$number = 0;
@endphp
@forelse($groups as $classification => $rows)
<tr class="classification"><td colspan="16">{{ strtoupper($classification) }}</td></tr>
@foreach($rows as $row)
<tr><td>{{ ++$number }}</td><td>{{ $row[0] }}</td><td>{{ $row[1] }}</td><td>{{ $row[3] }}</td>
@foreach([[4,'opening'],[5,'received'],[7,'issued'],[6,'returned'],[8,'adjusted'],[9,'closing']] as [$index,$field])
<td class="numeric">{{ number_format((float) $row[$index], 2) }}</td><td class="numeric">{{ $money($field === 'closing' ? $row[10] : ($amounts[$row[0]][$field] ?? null)) }}</td>
@endforeach</tr>
@endforeach
<tr class="total"><td colspan="4">SUB TOTAL</td>@foreach([[4,'opening'],[5,'received'],[7,'issued'],[6,'returned'],[8,'adjusted'],[9,'closing']] as [$index,$field])<td class="numeric">{{ number_format($rows->sum($index),2) }}</td><td class="numeric">{{ $money($field === 'closing' ? $rows->sum(10) : $sumMoney($rows,$field)) }}</td>@endforeach</tr>
@empty<tr><td colspan="16">No approved source records for this period and report.</td></tr>
@endforelse
@php
$allRows = collect($table['rows']);
@endphp
<tr class="total"><td colspan="4">GRAND TOTAL</td>@foreach([[4,'opening'],[5,'received'],[7,'issued'],[6,'returned'],[8,'adjusted'],[9,'closing']] as [$index,$field])<td class="numeric">{{ number_format($allRows->sum($index),2) }}</td><td class="numeric">{{ $money($field === 'closing' ? $allRows->sum(10) : $sumMoney($allRows,$field)) }}</td>@endforeach</tr>
</tbody></table>
<table class="summary"><thead><tr><th>ITEM GROUP SUMMARY</th>@foreach(['Beginning Amt.','Received','Issued','Returned','Adjustments','Ending Amt.'] as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead><tbody>
@foreach($groups as $classification=>$rows)<tr><td>{{ strtoupper($classification) }}</td>@foreach(['opening','received','issued','returned','adjusted'] as $field)<td>{{ $money($sumMoney($rows,$field)) }}</td>@endforeach<td>{{ $money($rows->sum(10)) }}</td></tr>@endforeach
</tbody><tfoot><tr><td>Total:</td>@foreach(['opening','received','issued','returned','adjusted'] as $field)<td>{{ $money($sumMoney($allRows,$field)) }}</td>@endforeach<td>{{ $money($allRows->sum(10)) }}</td></tr></tfoot></table>
@if(!isset($table['amounts']))<p class="audit-meta">Movement amounts were not captured in this historical snapshot. Dashes indicate unavailable amounts.</p>@endif
@else
<table class="generic-table"><thead><tr>@foreach($table['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead><tbody>
@forelse($table['rows'] as $row)<tr>@foreach($row as $cell)<td>{{ $cell ?? '' }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($table['columns']) }}">No approved source records for this period and report.</td></tr>@endforelse
</tbody></table>
@endif
@if(filled($packet->reconciliation_reference))<p class="audit-meta screen-only">Validation and reconciliation evidence: {{ $packet->reconciliation_reference }}</p>@endif
<div class="signatures">@foreach($packet->signatories as $stage=>$person)
@php
$actionName=match($stage){'prepared'=>'prepare','checked'=>'check','noted'=>'note',default=>'approve'};
$decision=$stage === 'prepared' ? $signatures->first(fn ($entry) => in_array($entry->action, ['prepare', 'refresh'], true)) : $signatures->firstWhere('action',$actionName);
@endphp
<div>{{ match($stage) {'prepared'=>'Prepared by:', 'checked'=>'Checked by:', 'noted'=>'Noted by:', default=>'Approved by:'} }}<span class="signature-name">{{ $person['name'] }}</span><span class="signature-role">{{ str_contains($person['role'], ' / ') ? explode(' / ', $person['role'], 2)[1] : $person['role'] }}</span><span class="signature-evidence">{{ $decision?->created_at->timezone('Asia/Manila')->format('Y-m-d H:i') ?? 'Pending' }} {{ $decision?->metadata['signature_reference'] ?? '' }}</span></div>
@endforeach</div>
<div class="footer"><span>{{ $packet->document_no }}</span><span>Monthly Inventory Reports</span><span>{{ $packet->period_month->format('F Y') }}</span></div>
<p class="fingerprint screen-only">Source fingerprint: {{ $packet->snapshot->source_hash }}</p></section>
@endforeach
@endif
</x-print-layout>
</body></html>

