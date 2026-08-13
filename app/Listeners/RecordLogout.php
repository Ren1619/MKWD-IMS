<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Logout;

class RecordLogout
{
    /**
     * Create the event listener.
     */
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->auditLogger->record(
            'logout',
            "Signed out of IMS as {$event->user->email}.",
            $event->user,
            actor: $event->user,
        );
    }
}
