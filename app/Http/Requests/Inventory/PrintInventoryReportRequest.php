<?php

namespace App\Http\Requests\Inventory;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintInventoryReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'as_of' => ['nullable', 'date'],
            'period' => ['nullable', Rule::in(['all', 'current_year', 'current_month', 'q1', 'q2', 'q3', 'q4', 'custom'])],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'from' => [Rule::requiredIf($this->input('period') === 'custom'), 'nullable', 'date'],
            'to' => [Rule::requiredIf($this->input('period') === 'custom'), 'nullable', 'date', 'after_or_equal:from'],
            'fund_cluster' => ['nullable', 'string', 'max:100'],
            'custodian' => ['nullable', 'integer', Rule::exists('hris_references', 'id')],
        ];
    }

    /** @return array{as_of: string, period: string, period_label: string, year: int, from: string|null, to: string|null, fund_cluster: string, custodian: int|null} */
    public function filters(): array
    {
        $period = $this->string('period')->toString() ?: 'current_year';
        $year = $this->integer('year') ?: today()->year;
        [$from, $to, $periodLabel] = $this->periodRange($period, $year);

        return [
            'as_of' => $this->date('as_of')?->toDateString() ?? today()->toDateString(),
            'period' => $period,
            'period_label' => $periodLabel,
            'year' => $year,
            'from' => $from,
            'to' => $to,
            'fund_cluster' => $this->string('fund_cluster')->toString(),
            'custodian' => $this->integer('custodian') ?: null,
        ];
    }

    /** @return array{string|null, string|null, string} */
    private function periodRange(string $period, int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);

        return match ($period) {
            'all' => [null, null, 'All time'],
            'current_month' => [today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString(), today()->format('F Y')],
            'q1', 'q2', 'q3', 'q4' => $this->quarterRange($yearStart, (int) substr($period, 1)),
            'custom' => [$this->date('from')?->toDateString(), $this->date('to')?->toDateString(), 'Custom period'],
            default => [$yearStart->toDateString(), $yearStart->endOfYear()->toDateString(), "Calendar year {$year}"],
        };
    }

    /** @return array{string, string, string} */
    private function quarterRange(CarbonImmutable $yearStart, int $quarter): array
    {
        $start = $yearStart->addMonths(($quarter - 1) * 3);

        return [$start->toDateString(), $start->addMonths(3)->subDay()->toDateString(), "Q{$quarter} {$start->year}"];
    }
}
