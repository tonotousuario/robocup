<?php

namespace App\Policies;

use App\Models\User;

class EncuentroPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdministrador() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function generar(User $user): bool
    {
        return false;
    }

    public function registrarGanador(User $user): bool
    {
        return $user->isJuez();
    }
}
