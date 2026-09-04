<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Property Tag - {{ $asset->property_number ?: $asset->serial_number }}</title>
    <style>
        @page { size: 90mm 55mm; margin: 3mm; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; background: #e2e8f0; color: #0f172a; font-family: Arial, sans-serif; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; max-width: 84mm; margin: 0 auto 16px; font-size: 12px; }
        button { padding: 8px 14px; border: 0; border-radius: 5px; color: white; background: #0f172a; cursor: pointer; }
        .tag { display: grid; grid-template-columns: 31mm 1fr; gap: 3mm; width: 84mm; min-height: 49mm; margin: auto; padding: 3mm; overflow: hidden; border: 1.5px solid #0f172a; border-radius: 2mm; background: white; }
        .qr { display: flex; align-items: center; justify-content: center; }
        .qr img { display: block; width: 29mm; height: 29mm; }
        .agency { margin: 0 0 1mm; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .title { margin: 0 0 1.5mm; font-size: 12px; line-height: 1.1; }
        .classification { display: inline-block; margin-bottom: 1.5mm; padding: 1mm 1.5mm; border: 1px solid #475569; border-radius: 20px; font-size: 7px; font-weight: 700; text-transform: uppercase; }
        dl { display: grid; grid-template-columns: 18mm 1fr; gap: .7mm 1.5mm; margin: 0; font-size: 7.5px; line-height: 1.25; }
        dt { color: #475569; }
        dd { margin: 0; overflow-wrap: anywhere; font-weight: 600; }
        .scan { grid-column: 1 / -1; align-self: end; margin: 1mm 0 0; text-align: center; font-size: 6.5px; color: #475569; }
        @media print { body { padding: 0; background: white; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>Permanent QR property tag</span>
        <button type="button" onclick="window.print()">Print tag</button>
    </div>
    <main class="tag">
        <div class="qr"><img src="{{ $qrCodeDataUri }}" alt="QR code for {{ $asset->name }}"></div>
        <div>
            <p class="agency">{{ config('app.name') }}</p>
            <h1 class="title">{{ $asset->name }}</h1>
            <span class="classification">{{ $asset->accounting_classification->label() }}</span>
            <dl>
                <dt>Property no.</dt><dd>{{ $asset->property_number ?: 'Not assigned' }}</dd>
                <dt>Serial no.</dt><dd>{{ $asset->serial_number }}</dd>
                <dt>Fund cluster</dt><dd>{{ $asset->fund_cluster ?: 'Not specified' }}</dd>
            </dl>
        </div>
        <p class="scan">Scan to verify this property record. Tag ID: {{ $asset->property_tag_uuid }}</p>
    </main>
</body>
</html>
