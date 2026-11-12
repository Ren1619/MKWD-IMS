<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class RecordsInvalidated implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(public string $scope, private ?int $userId = null) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel($this->scope === 'account' ? 'records.account.'.$this->userId : 'records.'.$this->scope),
        ];
    }

    public function broadcastAs(): string
    {
        return 'records.changed';
    }

    /** @return array{scope: string} */
    public function broadcastWith(): array
    {
        return ['scope' => $this->scope];
    }
}
