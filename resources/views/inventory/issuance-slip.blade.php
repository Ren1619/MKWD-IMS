<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Issuance Slip {{ $slip->slip_no }}</title>
    @include('inventory.issuance-print-styles')
</head>
<body>
<x-print-layout>
<button class="print-button" onclick="window.print()">Print / Save PDF</button>
<h1>ISSUANCE SLIP</h1>
<div class="fields"><span><strong>IS No.:</strong> {{ $slip->slip_no }}</span><span><strong>IS Date:</strong> {{ $slip->issued_at->format('F j, Y') }}</span></div>
<p><strong>Linked IRF No.:</strong> {{ $slip->snapshot['irf_no'] ?? $slip->supplyRequest->irf_no ?? 'Not recorded' }}</p>
<p><strong>Requesting Department:</strong> {{ $slip->snapshot['department'] ?? $slip->supplyRequest->office_name }}</p>
<p><strong>Issued to (Requisitioner):</strong> {{ $slip->snapshot['requested_by'] ?? $slip->supplyRequest->requester_name }}</p>
<p class="purpose"><strong>Purpose:</strong> {{ $slip->supplyRequest->purpose }}</p>
<table>
    <thead><tr><th>No.</th><th>ITEM CODE</th><th>ITEM DESCRIPTION</th><th>UNIT</th><th>QTY.</th><th>UNIT COST</th><th>AMOUNT</th><th>CHARGE ACCOUNT</th></tr></thead>
    <tbody>
    @foreach ($lines as $line)
        <tr>
            <td>{{ $loop->iteration }}</td><td>{{ $line['item_code'] }}</td><td>{{ $line['item_description'] }}</td><td>{{ $line['unit_of_measure'] }}</td>
            <td class="number">{{ $line['quantity_issued'] }}</td>
            <td class="number">{{ $line['unit_cost'] === null ? '' : number_format($line['unit_cost'], 2) }}</td>
            <td class="number">{{ $line['amount'] === null ? '' : number_format($line['amount'], 2) }}</td><td>{{ $line['charge_account'] }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot><tr><th colspan="6">Total amount</th><td class="number">{{ $lines->contains(fn ($line) => $line['amount'] === null) ? '' : number_format($lines->sum('amount'), 2) }}</td><td></td></tr></tfoot>
</table>
<div class="signatures">
    @foreach (['prepared_by' => 'Prepared By', 'checked_by' => 'Checked By', 'approved_by' => 'Approved By'] as $role => $label)
        <div>{{ $label }}<div class="signature">{{ $signatories[$role]['name'] }}</div>{{ $signatories[$role]['position'] }}</div>
    @endforeach
</div>
</x-print-layout>
</body>
</html>
