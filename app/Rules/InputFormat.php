<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use LogicException;

class InputFormat implements ValidationRule
{
    public function __construct(private string $format) {}

    /** @return array<string, array{pattern: string, message: string}> */
    public static function definitions(): array
    {
        static $formats;

        if ($formats === null) {
            $contents = file_get_contents(resource_path('input-formats.json'));
            if ($contents === false) {
                throw new LogicException('Input format definitions could not be read.');
            }
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                throw new LogicException('Input format definitions must be an object.');
            }
            $formats = [];
            foreach ($decoded as $key => $definition) {
                if (! is_string($key) || ! is_array($definition) || ! is_string($definition['pattern'] ?? null) || ! is_string($definition['message'] ?? null)) {
                    throw new LogicException('Invalid input format definition.');
                }
                $formats[$key] = ['pattern' => $definition['pattern'], 'message' => $definition['message']];
            }
        }

        return $formats;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $definition = self::definitions()[$this->format] ?? throw new LogicException('Unknown input format: '.$this->format);
        if ((! is_string($value) && ! is_int($value) && ! is_float($value))
            || preg_match('~\A(?:'.$definition['pattern'].')\z~u', (string) $value) !== 1) {
            $fail($definition['message']);
        }
    }
}
