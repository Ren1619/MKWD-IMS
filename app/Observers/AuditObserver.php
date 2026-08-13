<?php

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditObserver
{
    /** @var list<string> */
    private const EXCLUDED_ATTRIBUTES = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'created_at',
        'updated_at',
    ];

    public function __construct(private AuditLogger $auditLogger) {}

    public function created(Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        $this->auditLogger->record(
            'created',
            "Created {$this->subjectDescription($model)}.",
            $model,
            newValues: $this->safeValues($model->getAttributes()),
        );
    }

    public function updated(Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        $changes = $this->safeValues($model->getChanges());

        if ($changes === []) {
            return;
        }

        $this->auditLogger->record(
            'updated',
            "Updated {$this->subjectDescription($model)}.",
            $model,
            $this->safeValues(Arr::only($model->getRawOriginal(), array_keys($changes))),
            $changes,
        );
    }

    public function deleted(Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        $this->auditLogger->record(
            'deleted',
            "Deleted {$this->subjectDescription($model)}.",
            $model,
            oldValues: $this->safeValues($model->getAttributes()),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function safeValues(array $values): array
    {
        return Arr::except($values, self::EXCLUDED_ATTRIBUTES);
    }

    private function subjectDescription(Model $model): string
    {
        $type = Str::of(class_basename($model))->snake(' ')->toString();
        $identifier = collect(['name', 'email', 'property_number', 'serial_number', 'code'])
            ->map(fn (string $attribute): mixed => $model->getAttribute($attribute))
            ->first(fn (mixed $value): bool => filled($value));

        return $identifier ? "{$type} \"{$identifier}\"" : "{$type} #{$model->getKey()}";
    }
}
