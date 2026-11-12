@php($anniversaryId = 'anniversary-'.\Illuminate\Support\Str::uuid())
<div class="print-letterhead-row">
    <x-print-district-mark class="print-district-seal" />
    <div class="print-district-heading">
        <div class="print-district-name">METRO KIDAPAWAN WATER DISTRICT</div>
        <div class="print-district-address">LANAO, KIDAPAWAN CITY, COTABATO</div>
    </div>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 160" class="print-anniversary" role="img" aria-label="50 Years anniversary">
        <defs><pattern id="{{ $anniversaryId }}" width="14" height="14" patternUnits="userSpaceOnUse"><path d="M0 7L7 0L14 7L7 14Z M3 7L7 3L11 7L7 11Z" fill="none" stroke="#1275cf" stroke-width="1.5" /></pattern></defs>
        <path d="M8 17H108Q108 49 78 49H8Z" fill="#0db9f0" />
        <path d="M8 17H108Q108 49 78 49H8Z" fill="url(#{{ $anniversaryId }})" />
        <path d="M8 55H78Q108 55 108 91H38Q8 91 8 55Z" fill="white" stroke="#d4d4d4" stroke-width=".7" />
        <path d="M8 66H26L33 60H44 M8 72H30L38 67H52 M8 79H27L35 84H47 M108 66H90L83 60H73 M108 73H87L81 78H68" fill="none" stroke="#999" stroke-width="1.3" />
        <path d="M8 98H24Q57 98 57 137V145H43Q8 145 8 112Z M64 145V132Q64 98 99 98H108V112Q108 145 76 145Z" fill="#0bd600" />
        <circle cx="174" cy="78" r="58" fill="none" stroke="#0ab7ed" stroke-width="12" stroke-dasharray="12 3" />
        <circle cx="174" cy="78" r="45" fill="none" stroke="#0bd600" stroke-width="9" stroke-dasharray="9 3" />
        <circle cx="174" cy="78" r="38" fill="white" />
        <x-print-district-mark x="140" y="44" width="68" height="68" />
        <text x="189" y="142" fill="#100568" font-family="Georgia,serif" font-style="italic" font-size="31">Years</text>
    </svg>
</div>
