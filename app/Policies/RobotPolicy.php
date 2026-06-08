<?php

namespace App\Policies;

use App\Models\Robot;
use App\Models\User;

class RobotPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdministrador() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isPiloto();
    }

    public function create(User $user): bool
    {
        return $user->isPiloto();
    }

    public function update(User $user, Robot $robot): bool
    {
        return $user->isPiloto() && (int) $robot->id_piloto === $user->id;
    }

    public function delete(User $user, Robot $robot): bool
    {
        return $user->isPiloto() && (int) $robot->id_piloto === $user->id;
    }
}
