<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RolUsuario;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'apellidos', 'email', 'telefono', 'rol', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'rol' => RolUsuario::class,
        ];
    }

    public function hasRole(RolUsuario ...$roles): bool
    {
        return in_array($this->rol, $roles, true);
    }

    public function isAdministrador(): bool
    {
        return $this->hasRole(RolUsuario::Administrador);
    }

    public function isJuez(): bool
    {
        return $this->hasRole(RolUsuario::Juez);
    }

    public function isCoach(): bool
    {
        return $this->hasRole(RolUsuario::Coach);
    }

    public function isPiloto(): bool
    {
        return $this->hasRole(RolUsuario::Piloto);
    }

    /** @return HasMany<Robot, $this> */
    public function robotsComoPiloto(): HasMany
    {
        return $this->hasMany(Robot::class, 'id_piloto', 'id');
    }

    /** @return HasMany<InspeccionChecklist, $this> */
    public function inspecciones(): HasMany
    {
        return $this->hasMany(InspeccionChecklist::class, 'id_juez', 'id');
    }
}
