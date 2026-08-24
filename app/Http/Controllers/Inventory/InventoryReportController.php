<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryReportRequest;
use App\Http\Requests\Inventory\PrintInventoryReportRequest;
use App\Services\InventoryReportService;
use Illuminate\Contracts\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryReportController extends Controller
{
    public function __construct(private InventoryReportService $reports) {}

    public function index(InventoryReportRequest $request): InertiaResponse
    {
        $filters = $request->filters();

        return Inertia::render('Inventory/Reports/Index', [
            'summary' => $this->reports->summary(),
            'records' => $filters['report'] === 'assets'
                ? $this->reports->assets($filters)
                : $this->reports->consumables($filters),
            'filters' => $filters,
            'options' => $this->reports->options(),
            'documents' => $this->reports->catalog(),
        ]);
    }

    public function print(PrintInventoryReportRequest $request, string $document): View
    {
        return view('inventory.coa-report', $this->reports->printable($document, $request->filters()));
    }

    public function export(InventoryReportRequest $request): StreamedResponse
    {
        $report = $this->reports->export($request->filters());

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, $report['headers'], escape: '\\');

            foreach ($report['rows'] as $row) {
                fputcsv($output, array_map($this->escapeSpreadsheetValue(...), $row), escape: '\\');
            }

            fclose($output);
        }, $report['filename'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function escapeSpreadsheetValue(mixed $value): string
    {
        $formatted = match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Yes' : 'No',
            default => (string) $value,
        };

        if (preg_match('/^[\s]*[=+\-@\t\r]/u', $formatted) === 1) {
            return "'".$formatted;
        }

        return $formatted;
    }
}
