<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;

class RecordSuccessfulLogin
{
    /**
     * Create the event listener.
     */
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        $authenticationSource = session()->pull('ims_authentication_source', 'local');

        $this->auditLogger->record(
            'login',
            "Signed in to IMS as {$event->user->email} using {$authenticationSource} authentication.",
            $event->user,
            newValues: ['authentication_source' => $authenticationSource],
            actor: $event->user,
        );
    }
}
