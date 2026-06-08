<?php

namespace App\Policies;

use App\Models\User;

class InspeccionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdministrador() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isJuez() || $user->isPiloto();
    }

    public function guardar(User $user): bool
    {
        return $user->isJuez();
    }
}
