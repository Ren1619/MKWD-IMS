<style>
    @page { size: A4 portrait; margin: 12mm; }
    body { font-family: Arial, sans-serif; font-size: 12px; color: #111; max-width: 1000px; margin: 20px auto; }
    h1, h2 { text-align: center; }
    h1 { font-size: 22px; margin: 24px 0; }
    h2 { font-size: 16px; }
    .fields { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin: 18px 0; }
    .fields.issuance-request-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 24px; }
    .purpose { white-space: pre-wrap; overflow-wrap: anywhere; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { border: 1px solid #555; padding: 8px 5px; overflow-wrap: anywhere; }
    th { font-size: 10px; }
    .number { text-align: right; font-variant-numeric: tabular-nums; }
    .signatures { display: flex; gap: 24px; margin-top: 40px; break-inside: avoid; }
    .signatures > div { flex: 1; text-align: center; }
    .signature { min-height: 18px; margin-top: 40px; padding-bottom: 4px; border-bottom: 1px solid #111; font-weight: bold; }
    thead { display: table-header-group; }
    tr { break-inside: avoid; }
    @media print { .print-button { display: none; } body { margin: 0; max-width: none; } }
</style>
