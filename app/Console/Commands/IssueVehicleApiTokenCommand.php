<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ims:issue-vehicle-api-token
    {email : Email address of the active user who owns the token}
    {name : Unique name of the receiving system}
    {--expires=365 : Number of days before the token expires}
    {--replace : Revoke an existing token with the same name}')]
#[Description('Issue a vehicles:read API token for an authorized receiving system')]
class IssueVehicleApiTokenCommand extends Command
{
    public function handle(): int
    {
        $user = User::query()
            ->where('email', $this->argument('email'))
            ->where('is_active', true)
            ->first();

        if (! $user) {
            $this->error('No active user was found with that email address.');

            return self::FAILURE;
        }

        $tokenName = (string) $this->argument('name');
        $expiresInDays = filter_var($this->option('expires'), FILTER_VALIDATE_INT);

        if ($expiresInDays === false || $expiresInDays < 1 || $expiresInDays > 3650) {
            $this->error('The --expires value must be between 1 and 3650 days.');

            return self::INVALID;
        }

        $existingTokenQuery = $user->tokens()->where('name', $tokenName);

        if ($existingTokenQuery->exists() && ! $this->option('replace')) {
            $this->error('A token with that name already exists. Use --replace to rotate it.');

            return self::FAILURE;
        }

        if ($this->option('replace')) {
            $existingTokenQuery->delete();
        }

        $token = $user->createToken(
            $tokenName,
            ['vehicles:read'],
            now()->addDays($expiresInDays),
        );

        $this->warn('Copy this token now. It will not be shown again:');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
