<?php

namespace App\Policies;

use App\Models\Inscripcion;
use App\Models\User;

class InscripcionPolicy
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

    public function pagar(User $user, Inscripcion $inscripcion): bool
    {
        return false;
    }

    public function cancelar(User $user, Inscripcion $inscripcion): bool
    {
        return false;
    }
}
