<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Issuance Request {{ $record->irf_no ?? $record->ris_no }}</title>
    @include('inventory.issuance-print-styles')
</head>
<body>
<x-print-layout>
<button class="print-button" onclick="window.print()">Print / Save PDF</button>
<h1>ISSUANCE REQUEST</h1>
<div class="fields issuance-request-fields">
    <span><strong>Department:</strong> {{ $record->office_name }}</span>
    <span><strong>Date:</strong> {{ ($record->irf_date ?? $record->submitted_at)?->format('F j, Y') }}</span>
    <span><strong>IRF No.:</strong> {{ $record->irf_no ?? $record->ris_no }}</span>
</div>
<p class="purpose"><strong>Purpose:</strong> {{ $record->purpose }}</p>
<table>
    <thead><tr><th>No.</th><th>ITEM DESCRIPTION</th><th>UNIT</th><th>QTY.</th></tr></thead>
    <tbody>
    @foreach ($record->lines as $line)
        <tr><td>{{ $loop->iteration }}</td><td>{{ $line->item_name }}</td><td>{{ $line->unit_of_measure }}</td><td class="number">{{ $line->quantity_requested }}</td></tr>
    @endforeach
    </tbody>
</table>
<p><strong>CHARGE CODE:</strong> {{ $record->responsibility_center_code }}</p>
<div class="signatures">
    <div>Requisitioner<div class="signature">{{ $record->issuance_details['requested_by'] ?? $record->requester_name }}</div></div>
    <div>Checked By<div class="signature">{{ $record->issuance_details['checked_by'] ?? '' }}</div>Division Manager / Head</div>
    <div>Approved By<div class="signature">{{ $record->issuance_details['approved_by'] ?? '' }}</div>Department Manager / Head</div>
</div>
</x-print-layout>
</body>
</html>
