<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('records.inventory', fn (User $user): bool => $user->is_active);
Broadcast::channel('records.administration', fn (User $user): bool => $user->is_active && $user->isSuperAdmin());
Broadcast::channel('records.account.{id}', fn (User $user, string $id): bool => $user->is_active && (string) $user->id === $id);
