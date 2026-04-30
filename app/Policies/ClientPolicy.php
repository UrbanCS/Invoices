<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function view(User $user, Client $client): bool
    {
        return $user->canManage() || ($user->isClientUser() && $user->client_id === $client->id);
    }

    public function manage(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
