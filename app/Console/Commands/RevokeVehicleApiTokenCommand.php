<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ims:revoke-vehicle-api-token
    {email : Email address of the user who owns the token}
    {name : Name of the receiving system}')]
#[Description('Revoke a receiving system vehicle API token')]
class RevokeVehicleApiTokenCommand extends Command
{
    public function handle(): int
    {
        $user = User::query()
            ->where('email', $this->argument('email'))
            ->first();

        if (! $user) {
            $this->error('No user was found with that email address.');

            return self::FAILURE;
        }

        $deletedTokens = $user->tokens()
            ->where('name', $this->argument('name'))
            ->delete();

        if ($deletedTokens === 0) {
            $this->error('No token was found with that name.');

            return self::FAILURE;
        }

        $this->info('The vehicle API token was revoked.');

        return self::SUCCESS;
    }
}
