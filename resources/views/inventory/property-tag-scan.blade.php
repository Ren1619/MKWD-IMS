<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Property Tag Verification</title>
    <style>
        * { box-sizing: border-box; }
        body { display: grid; min-height: 100vh; margin: 0; padding: 24px; place-items: center; background: #f1f5f9; color: #0f172a; font-family: Arial, sans-serif; }
        main { width: min(100%, 560px); padding: 28px; border: 1px solid #cbd5e1; border-radius: 16px; background: white; box-shadow: 0 16px 40px rgb(15 23 42 / 8%); }
        .verified { display: inline-block; padding: 6px 10px; border-radius: 999px; background: #dcfce7; color: #166534; font-size: 12px; font-weight: 700; }
        h1 { margin: 18px 0 6px; font-size: 26px; }
        .agency { margin: 0; color: #475569; }
        dl { display: grid; grid-template-columns: 145px 1fr; gap: 12px 20px; margin: 28px 0; padding-top: 22px; border-top: 1px solid #e2e8f0; }
        dt { color: #64748b; }
        dd { margin: 0; overflow-wrap: anywhere; font-weight: 600; }
        .notice { padding: 14px; border-radius: 10px; background: #f8fafc; color: #475569; font-size: 13px; line-height: 1.5; }
        @media (max-width: 480px) { dl { grid-template-columns: 1fr; gap: 4px; } dd { margin-bottom: 10px; } }
    </style>
</head>
<body>
    <main>
        <span class="verified">Verified property tag</span>
        <h1>{{ $asset->name }}</h1>
        <p class="agency">Recorded by {{ config('app.name') }}</p>
        <dl>
            <dt>Property number</dt><dd>{{ $asset->property_number ?: 'Not assigned' }}</dd>
            <dt>Serial number</dt><dd>{{ $asset->serial_number }}</dd>
            <dt>Classification</dt><dd>{{ $asset->accounting_classification->label() }}</dd>
            <dt>Lifecycle status</dt><dd>{{ $asset->lifecycle_status->label() }}</dd>
            <dt>Physical condition</dt><dd>{{ $asset->condition_status->label() }}</dd>
        </dl>
        <div class="notice">
            This page verifies that the QR code belongs to an official property record. Custodian, location, and financial information are intentionally hidden. Contact the agency Property or Supply Unit if this item is found, damaged, or appears improperly assigned.
        </div>
    </main>
</body>
</html>
