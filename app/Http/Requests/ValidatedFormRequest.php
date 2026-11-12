<?php

namespace App\Http\Requests;

use App\Rules\InputFormat;
use Illuminate\Foundation\Http\FormRequest;

abstract class ValidatedFormRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    protected function validationRules(): array
    {
        $rules = parent::validationRules();
        foreach ($rules as $attribute => $attributeRules) {
            $attributeRules = is_array($attributeRules) ? $attributeRules : [$attributeRules];
            $field = last(explode('.', $attribute));
            $format = match (true) {
                in_array('integer', $attributeRules, true) => 'integer',
                in_array('numeric', $attributeRules, true) => 'number',
                in_array('date_format:Y-m-d', $attributeRules, true) => 'date',
                in_array('date_format:Y-m', $attributeRules, true) => 'month',
                in_array('date_format:Y-m-d\TH:i', $attributeRules, true) => 'datetime',
                in_array('string', $attributeRules, true) && in_array($field, ['stock_number', 'uacs_object_code', 'property_number_prefix'], true) => $field,
                default => null,
            };
            if ($format !== null) {
                $attributeRules[] = new InputFormat($format);
            }
            $rules[$attribute] = $attributeRules;
        }

        return $rules;
    }
}
