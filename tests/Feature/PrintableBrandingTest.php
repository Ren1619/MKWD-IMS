<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

test('every printable template uses the shared header and footer', function () {
    $templates = collect(File::allFiles(resource_path('views')))
        ->filter(fn (SplFileInfo $file): bool => str_contains($file->getContents(), 'window.print'));

    expect($templates)->toHaveCount(19);
    foreach ($templates as $template) {
        $source = $template->getContents();
        expect($source)->toContain('<x-print-layout', '</x-print-layout>');
        expect(strpos($source, '<x-print-layout'))->toBeGreaterThan(strpos($source, '<body'));
        expect(strpos($source, '</x-print-layout>'))->toBeLessThan(strpos($source, '</body>'));
    }
});

test('printable document titles do not repeat the district name above the document name', function () {
    $templates = collect(File::allFiles(resource_path('views/inventory')))
        ->filter(fn (SplFileInfo $file): bool => str_contains($file->getContents(), 'window.print'));
    $entityHeadings = [
        '<h2>METRO KIDAPAWAN WATER DISTRICT</h2>',
        '<p>Metro Kidapawan Water District</p>',
        '<h1>{{ config(\'app.name\') }}</h1>',
        '<p class="agency">{{ config(\'app.name\') }}</p>',
        '<h1>{{ $packet->snapshot->payload[\'entity_name\'] }}</h1>',
        '<p>{{ $document->entity_name }}</p>',
        '<h2>{{ $entity }}</h2>',
        '<p>{{ config(\'app.entity_name\', \'Metro Kidapawan Water District\') }}</p>',
    ];
    foreach ($templates as $template) {
        expect($template->getContents())->not->toContain(...$entityHeadings);
    }
    expect(file_get_contents(resource_path('views/components/print-brand-header.blade.php')))
        ->toContain('METRO KIDAPAWAN WATER DISTRICT');
});

test('print branding renders selectable text and vector artwork around the document content', function () {
    $html = Blade::render('<x-print-layout><main>Printable content</main></x-print-layout>');
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $header = $xpath->query('//div[@role="banner"]')->item(0);
    $footer = $xpath->query('//div[@role="contentinfo"]')->item(0);

    expect($header?->textContent)->toContain('METRO KIDAPAWAN WATER DISTRICT', 'LANAO, KIDAPAWAN CITY, COTABATO', 'Years')
        ->and($footer?->textContent)->toContain('0951-024-6710', '(064) 577-1533', 'metrokidapawan_wd@yahoo.com', 'metrokidapawanwd.gov.ph', '@mkwdpublicinfo', 'BAGONG PILIPINAS')
        ->and(strpos($html, 'role="banner"'))->toBeLessThan(strpos($html, 'Printable content'))
        ->and(strpos($html, 'role="contentinfo"'))->toBeGreaterThan(strpos($html, 'Printable content'));
    expect($xpath->query('//div[@role="banner"]//svg')->length)->toBeGreaterThanOrEqual(3)
        ->and($xpath->query('//div[@role="contentinfo"]//svg')->length)->toBeGreaterThanOrEqual(5)
        ->and($xpath->query('//img | //image')->length)->toBe(0)
        ->and($html)->not->toContain('rms-letterhead.jpeg', 'rms-footer.jpeg', 'data:image');
    expect($xpath->query('//div[@role="contentinfo"]//*[contains(@class,"print-district-seal") or contains(@class,"print-anniversary") or contains(@class,"print-letterhead-row")]')->length)->toBe(0)
        ->and($xpath->query('//div[@role="banner"]//*[contains(@class,"print-letterfoot-row")]')->length)->toBe(0)
        ->and($footer?->textContent)->toContain('ANG IMONG MKWD', 'nag-atiman kanimo')
        ->not->toContain('POTABLE, ACCESSIBLE, UNLIMITED', 'LANAO, KIDAPAWAN CITY, COTABATO');
    expect($xpath->query('//div[@role="contentinfo"]//div[@class="print-contact-phone"]')->length)->toBe(2);
});

test('branding reserves print margins and keeps property tags compact', function () {
    $standard = Blade::render('<x-print-layout>Report</x-print-layout>');
    $compact = Blade::render('<x-print-layout compact>Tag</x-print-layout>');
    expect($standard)->toContain('position: fixed', 'margin-top: 6mm', 'margin-bottom: 6mm', 'top: 0;', 'bottom: 0;', 'height: 32mm;', 'height: 18mm;', 'display: table-header-group', 'display: table-footer-group')
        ->toContain('bottom: auto;', 'top: auto;')
        ->and($standard)->not->toContain('top: -', 'bottom: -')
        ->and($compact)->toContain('margin-top: 3mm', 'margin-bottom: 2mm', 'height: 10mm;', 'height: 6mm;')
        ->and(file_get_contents(resource_path('views/inventory/property-tag.blade.php')))->toContain('<x-print-layout compact>', '100mm 70mm');
});

test('RMS keeps its screen letterhead and hides duplicate branding when printing', function () {
    $html = Blade::render('<x-print-layout embedded><main class="sheet">RMS</main></x-print-layout>');
    expect($html)->toContain('@media screen', '.sheet > .letterhead, .sheet > .letterfoot { display: none; }', '.sheet { min-height: 0; }');
    expect(file_get_contents(resource_path('views/inventory/material-return.blade.php')))
        ->toContain('<x-print-brand-header', '<x-print-brand-footer')
        ->not->toContain('rms-letterhead.jpeg', 'rms-footer.jpeg');
});

test('repeated vector marks have unique fragment identifiers', function () {
    $html = Blade::render('<x-print-layout embedded><x-print-brand-header /><x-print-brand-header /></x-print-layout>');
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $ids = collect(iterator_to_array($xpath->query('//*[@id]')))->map(fn (DOMElement $element): string => $element->getAttribute('id'));
    expect($ids->count())->toBeGreaterThan(0)->and($ids->unique()->count())->toBe($ids->count());
});
