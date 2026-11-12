<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>PPE Lapsing Schedule ? {{ $year }}</title>
<style>
body{font-family:Arial,sans-serif;font-size:12px;margin:30px}h1,h2{text-align:center}table{border-collapse:collapse;width:100%;margin:20px 0}th,td{border:1px solid #333;padding:7px;text-align:right}th:first-child,td:first-child{text-align:left}.signatures{display:flex;gap:60px;margin-top:55px}.signatures>div{flex:1}.signature{border-bottom:1px solid #333;padding-top:35px}tr{break-inside:avoid}@media print{button{display:none}body{margin:0}@page{size:A4 landscape;margin:12mm}}
</style>
</head>
<body>
<x-print-layout>
<button onclick="window.print()">Print / Save as PDF</button>
<h1>PROPERTY, PLANT AND EQUIPMENT</h1>
<h2>Lapsing Schedule ? {{ $year }}</h2>
<p><strong>{{ $asset->item_description }}</strong> ? Property number: {{ $asset->property_number ?? 'Not recorded' }}</p>
<p>Acquired: {{ $asset->acquisition_date->format('F j, Y') }} ? Acquisition cost: PHP {{ number_format((float) $asset->acquisition_cost, 2) }}</p>
<table>
<thead><tr><th>Period</th><th>Gross carrying amount</th><th>Depreciation expense</th><th>Accumulated depreciation</th><th>Impairment losses</th><th>Book value</th><th>Recognition</th></tr></thead>
<tbody>
<tr><th>Opening balance (January 1)</th><td>{{ number_format((float) ($opening?->grossCarryingAmount ?? 0), 2) }}</td><td>?</td><td>{{ number_format((float) ($opening?->accumulatedDepreciation ?? 0), 2) }}</td><td>{{ number_format((float) ($opening?->impairmentLosses ?? 0), 2) }}</td><td>{{ number_format((float) ($opening?->carryingAmount ?? 0), 2) }}</td><td>{{ $opening === null ? 'Not yet acquired' : ($opening->isRecognized ? 'Recognized' : 'Derecognized') }}</td></tr>
@foreach($rows as $row)
<tr><th>{{ $row['month'] }}</th><td>{{ number_format((float) ($row['valuation']?->grossCarryingAmount ?? 0), 2) }}</td><td>{{ number_format($row['expense'], 2) }}</td><td>{{ number_format((float) ($row['valuation']?->accumulatedDepreciation ?? 0), 2) }}</td><td>{{ number_format((float) ($row['valuation']?->impairmentLosses ?? 0), 2) }}</td><td>{{ number_format((float) ($row['valuation']?->carryingAmount ?? 0), 2) }}</td><td>{{ $row['valuation'] === null ? 'Not yet acquired' : ($row['valuation']->isRecognized ? 'Recognized' : 'Derecognized') }}</td></tr>
@endforeach
</tbody>
<tfoot><tr><th>Annual depreciation</th><td></td><td>{{ number_format(array_sum(array_column($rows, 'expense')), 2) }}</td><td colspan="4"></td></tr></tfoot>
</table>
<p>Amounts in PHP. Monthly closing balances use the recorded accounting policy and lifecycle events as of each month end. Future periods are projections based on currently recorded information. Gross cost and accumulated depreciation remain shown as historical amounts after derecognition; book value is zero.</p>
<div class="signatures"><div>Prepared by:<div class="signature">{{ $preparedBy->name }}</div><p>Signature / Date</p></div><div>Certified by:<div class="signature">{{ $certifiedBy->name }}</div><p>Signature / Date</p></div></div>
</x-print-layout>
</body></html>
