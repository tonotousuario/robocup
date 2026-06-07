<?php

namespace Tests\Unit;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_enum_tiene_los_cuatro_roles(): void
    {
        $this->assertSame('Administrador', RolUsuario::Administrador->value);
        $this->assertSame('Juez', RolUsuario::Juez->value);
        $this->assertSame('Coach', RolUsuario::Coach->value);
        $this->assertSame('Piloto', RolUsuario::Piloto->value);
    }

    public function test_rol_se_castea_a_enum(): void
    {
        $user = User::factory()->juez()->create();

        $this->assertInstanceOf(RolUsuario::class, $user->rol);
        $this->assertSame(RolUsuario::Juez, $user->rol);
    }

    public function test_helpers_de_rol(): void
    {
        $juez = User::factory()->juez()->create();

        $this->assertTrue($juez->isJuez());
        $this->assertFalse($juez->isAdministrador());
        $this->assertTrue($juez->hasRole(RolUsuario::Juez, RolUsuario::Administrador));
        $this->assertFalse($juez->hasRole(RolUsuario::Coach));
    }
}
