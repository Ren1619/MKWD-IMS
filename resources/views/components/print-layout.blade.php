@props(['compact' => false, 'embedded' => false])

<style>
    table.print-document-frame { width: 100%; margin: 0; border: 0; border-collapse: collapse; table-layout: fixed; }
    table.print-document-frame > thead > tr > td, table.print-document-frame > tbody > tr > td, table.print-document-frame > tfoot > tr > td { border: 0; padding: 0; text-align: initial; vertical-align: top; background: transparent; font: inherit; }
    @media screen {
        table.print-document-frame, table.print-document-frame > tbody, table.print-document-frame > tbody > tr, table.print-document-frame > tbody > tr > td { display: block; }
        table.print-document-frame > thead, table.print-document-frame > tfoot { display: none; }
    }
    .print-brand-header, .print-brand-footer { display: block; width: 100%; margin: 0 auto; break-inside: avoid; }
    .print-brand-header { margin-bottom: 6mm; }
    .print-brand-footer { margin-top: 6mm; }
    .print-letterhead-row, .print-letterfoot-row { box-sizing: border-box; color: #100568; font-family: Arial, Helvetica, sans-serif; line-height: 1.2; background: white; }
    .print-letterhead-row { display: flex; align-items: center; gap: {{ $compact ? '1mm' : '3mm' }}; width: 100%; height: {{ $compact ? '9mm' : '28mm' }}; border-bottom: .3mm solid #100568; padding-bottom: 1mm; }
    .print-district-seal { display: block; flex: 0 0 auto; width: {{ $compact ? '8mm' : '25mm' }}; height: {{ $compact ? '8mm' : '25mm' }}; }
    .print-district-heading { flex: 1; min-width: 0; }
    .print-district-name { font-size: {{ $compact ? '5.5px' : 'clamp(11px, 1.6vw, 22px)' }}; font-weight: 900; letter-spacing: -.3px; }
    .print-district-address { margin-top: 1mm; font-size: {{ $compact ? '3.5px' : 'clamp(7px, .85vw, 11px)' }}; }
    .print-anniversary { display: block; flex: 0 0 auto; width: {{ $compact ? '14mm' : '35mm' }}; height: {{ $compact ? '9mm' : '26mm' }}; }
    .print-letterfoot-row { display: grid; grid-template-columns: 1.6fr 1.3fr 1.4fr 2.35fr 1.35fr .8fr; align-items: center; gap: {{ $compact ? '.7mm' : '2mm' }}; width: 100%; min-height: {{ $compact ? '4mm' : '12mm' }}; border-top: .25mm solid #100568; padding-top: {{ $compact ? '.3mm' : '1.5mm' }}; font-size: {{ $compact ? '3px' : 'clamp(6px, .7vw, 10px)' }}; font-weight: 700; }
    .print-service-promise { display: flex; align-items: center; position: relative; height: {{ $compact ? '3mm' : '9mm' }}; padding-right: {{ $compact ? '2mm' : '5mm' }}; }
    .print-promise-symbol { flex: 0 0 auto; width: 20%; height: 100%; padding-left: 2%; border-radius: 1mm 0 0 1mm; background: #00b9e8; }
    .print-promise-copy { display: flex; flex: 1; flex-direction: column; justify-content: center; height: 100%; border-radius: 0 1mm 1mm 0; background: #00b9e8; color: white; white-space: nowrap; z-index: 1; }
    .print-promise-copy span { font-family: 'Arial Narrow', Arial, sans-serif; font-size: 1.05em; font-weight: 900; }
    .print-promise-copy small { font-family: Georgia, serif; font-style: italic; font-weight: normal; font-size: 1em; margin-top: .4mm; }
    .print-promise-mascot { position: absolute; right: 0; width: 24%; height: 120%; }
    .print-contact-phone { display: flex; align-items: center; gap: {{ $compact ? '.4mm' : '1.2mm' }}; white-space: nowrap; font-size: 1.1em; }
    .print-contact-block { display: grid; gap: {{ $compact ? '.4mm' : '1.5mm' }}; min-width: 0; }
    .print-contact-block > span { display: flex; align-items: center; gap: {{ $compact ? '.3mm' : '1mm' }}; white-space: nowrap; }
    .print-contact-icon { display: block; width: {{ $compact ? '1.5mm' : '3mm' }}; height: {{ $compact ? '1.5mm' : '3mm' }}; flex: 0 0 auto; }
    .print-contact-icon > circle:first-child { fill: #100568; stroke: none; }
    .print-contact-icon > :not(:first-child) { fill: none; stroke: white; stroke-width: 1.4; }
    .print-social-block { text-align: center; white-space: nowrap; }
    .print-social-symbols { display: flex; justify-content: center; gap: .4em; margin-bottom: 1mm; }
    .print-social-symbols svg { display: block; width: 1.6em; height: 1.6em; fill: #100568; }
    .print-bagong-brand { text-align: center; font-size: .7em; white-space: nowrap; }
    .print-bagong-brand svg { display: block; width: {{ $compact ? '5mm' : '12mm' }}; height: {{ $compact ? '3mm' : '8mm' }}; margin: auto; }
    .print-bagong-brand strong { font-size: inherit; }
    @if ($embedded)
        @media screen { .print-brand-header, .print-brand-footer { display: none; } }
    @endif
    @media print {
        @page { margin-top: {{ $compact ? '3mm' : '6mm' }}; margin-bottom: {{ $compact ? '2mm' : '6mm' }}; }
        body { margin: 0; }
        table.print-document-frame > thead { display: table-header-group; break-inside: avoid; }
        table.print-document-frame > tfoot { display: table-footer-group; break-inside: avoid; }
        table.print-document-frame > tbody > tr, table.print-document-frame > tbody > tr > td { break-inside: auto; }
        table.print-document-frame > thead > tr > td { height: {{ $compact ? '10mm' : '32mm' }}; }
        table.print-document-frame > tfoot > tr > td { height: {{ $compact ? '6mm' : '18mm' }}; }
        .print-brand-header, .print-brand-footer { position: fixed; left: 0; right: 0; width: 100%; margin: 0; z-index: 10; background: white; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        .print-brand-header { top: 0; bottom: auto; height: {{ $compact ? '9mm' : '28mm' }}; }
        .print-brand-footer { top: auto; bottom: 0; height: {{ $compact ? '4mm' : '12mm' }}; }
        .print-brand-header .print-letterhead-row, .print-brand-footer .print-letterfoot-row { height: 100%; }
        @if ($compact)
            .tag { min-height: 0; padding: 2mm; grid-template-columns: 26mm 1fr; gap: 2mm; }
            .qr img { width: 25mm; height: 25mm; }
            .tag .title { font-size: 10px; }
            .tag dl { font-size: 7px; }
        @endif
        @if ($embedded)
            .sheet > .letterhead, .sheet > .letterfoot { display: none; }
            .sheet { min-height: 0; }
            main.sheet, body.include-appendix .sheet.appendix { display: block; }
        @endif
    }
</style>
<div class="print-brand-header" role="banner">
    <x-print-brand-header />
</div>
<table class="print-document-frame" role="presentation">
    <thead aria-hidden="true"><tr><td></td></tr></thead>
    <tbody><tr><td>{{ $slot }}</td></tr></tbody>
    <tfoot aria-hidden="true"><tr><td></td></tr></tfoot>
</table>
<div class="print-brand-footer" role="contentinfo">
    <x-print-brand-footer />
</div>
