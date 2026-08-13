<?php

namespace App\Actions\Integrations;

use App\Contracts\HrisReferenceSource;
use App\Models\HrisReference;
use Illuminate\Support\Facades\DB;

class SyncHrisReferences
{
    public function __construct(private HrisReferenceSource $source) {}

    /**
     * @return array{created: int, updated: int, deactivated: int, total: int}
     */
    public function handle(): array
    {
        $references = $this->source->references();

        return DB::transaction(function () use ($references): array {
            $created = 0;
            $updated = 0;
            $seenByType = [];

            foreach ($references as $data) {
                $reference = HrisReference::query()->updateOrCreate(
                    [
                        'type' => $data['type'],
                        'external_id' => $data['external_id'],
                    ],
                    [
                        ...$data,
                        'last_synced_at' => now(),
                    ],
                );

                $reference->wasRecentlyCreated ? $created++ : $updated++;
                $seenByType[$data['type']][] = $data['external_id'];
            }

            $deactivated = 0;

            foreach ($seenByType as $type => $externalIds) {
                $deactivated += HrisReference::query()
                    ->where('type', $type)
                    ->whereNotIn('external_id', $externalIds)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            return [
                'created' => $created,
                'updated' => $updated,
                'deactivated' => $deactivated,
                'total' => count($references),
            ];
        });
    }
}
