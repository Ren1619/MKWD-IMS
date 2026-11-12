<?php

namespace App\Listeners;

use App\Events\RecordsChanged;
use App\Events\RecordsInvalidated;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastRecordChanges implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 15;

    public int $uniqueFor = 60;

    /** @var list<int> */
    public array $backoff = [1, 5, 10];

    /**
     * Handle the event.
     */
    public function handle(RecordsChanged $event): void
    {
        RecordsInvalidated::dispatch($event->scope, $event->userId);
    }

    public function uniqueId(RecordsChanged $event): string
    {
        return $event->scope.':'.$event->userId;
    }

    public function failed(RecordsChanged $event, Throwable $exception): void
    {
        Log::error('Realtime record update could not be broadcast.', ['scope' => $event->scope, 'exception' => $exception]);
    }
}
