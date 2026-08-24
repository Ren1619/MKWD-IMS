<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $base_url
 * @property int|null $updated_by
 */
#[Fillable(['base_url', 'updated_by'])]
class HrisIntegrationSetting extends Model
{
    public const SINGLETON_ID = 1;

    public static function configuredBaseUrl(): ?string
    {
        $databaseBaseUrl = self::query()->whereKey(self::SINGLETON_ID)->value('base_url');

        if (is_string($databaseBaseUrl) && $databaseBaseUrl !== '') {
            return $databaseBaseUrl;
        }

        $environmentBaseUrl = config('services.hris.base_url');

        return is_string($environmentBaseUrl) && $environmentBaseUrl !== ''
            ? $environmentBaseUrl
            : null;
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
