<?php

namespace App\Policies;

use App\Models\User;

class IntentoTiempoPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdministrador() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function capturar(User $user): bool
    {
        return $user->isJuez();
    }
}
