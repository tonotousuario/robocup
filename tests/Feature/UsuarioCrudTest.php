<?php

namespace Tests\Feature;

use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsuarioCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'Administrador']);
    }

    public function test_no_admin_recibe_403(): void
    {
        $this->actingAs(User::factory()->coach()->create())
            ->get('/usuarios')
            ->assertForbidden();
    }

    public function test_index_usuarios_pagina_busca_y_filtra_por_rol(): void
    {
        User::factory()->count(20)->create(['rol' => 'Piloto']);
        User::factory()->juez()->create(['name' => 'JuezUno']);
        User::factory()->juez()->create(['name' => 'JuezDos']);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/usuarios')
            ->assertInertia(fn (Assert $page) => $page
                ->component('usuarios/index')
                ->has('usuarios.data')
                ->where('usuarios.per_page', 15)
            );

        $this->actingAs($admin)
            ->get('/usuarios?q=JuezUno')
            ->assertInertia(fn (Assert $page) => $page
                ->has('usuarios.data', 1)
                ->where('usuarios.data.0.name', 'JuezUno')
            );

        $this->actingAs($admin)
            ->get('/usuarios?rol=Juez')
            ->assertInertia(fn (Assert $page) => $page
                ->where('usuarios.data', fn ($rows) => collect($rows)->every(fn ($r) => $r['rol'] === 'Juez'))
            );

        $this->actingAs($admin)
            ->get('/usuarios?sort=name&dir=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filtros.sort', 'name')
                ->where('filtros.dir', 'asc')
            );

        $this->actingAs($admin)
            ->get('/usuarios?sort=hackcolumn')
            ->assertOk();
    }

    public function test_admin_crea_usuario_con_password_hasheada(): void
    {
        $this->actingAs($this->admin())
            ->post('/usuarios', [
                'name' => 'Ana', 'apellidos' => 'López', 'email' => 'ana@test.mx',
                'telefono' => null, 'rol' => 'Juez', 'password' => 'secret123',
            ])
            ->assertRedirect();

        $user = User::where('email', 'ana@test.mx')->first();
        $this->assertNotNull($user);
        $this->assertSame('Juez', $user->rol->value);
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_duplicado_es_rechazado(): void
    {
        User::factory()->create(['email' => 'dup@test.mx']);

        $this->actingAs($this->admin())
            ->post('/usuarios', [
                'name' => 'X', 'apellidos' => 'Y', 'email' => 'dup@test.mx',
                'rol' => 'Piloto', 'password' => 'secret123',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_rol_invalido_es_rechazado(): void
    {
        $this->actingAs($this->admin())
            ->post('/usuarios', [
                'name' => 'X', 'apellidos' => 'Y', 'email' => 'z@test.mx',
                'rol' => 'Hacker', 'password' => 'secret123',
            ])
            ->assertSessionHasErrors('rol');
    }

    public function test_editar_sin_password_no_cambia_el_hash(): void
    {
        $user = User::factory()->coach()->create(['password' => Hash::make('original123')]);

        $this->actingAs($this->admin())
            ->put("/usuarios/{$user->id}", [
                'name' => 'Nuevo', 'apellidos' => 'Nombre', 'email' => $user->email,
                'rol' => 'Coach', 'password' => '',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('original123', $user->fresh()->password));
        $this->assertSame('Nuevo', $user->fresh()->name);
    }

    public function test_no_se_puede_borrar_usuario_referenciado(): void
    {
        $piloto = User::factory()->create(['rol' => 'Piloto']);
        Robot::factory()->create(['id_piloto' => $piloto->id]);

        $this->actingAs($this->admin())
            ->delete("/usuarios/{$piloto->id}")
            ->assertSessionHasErrors('usuario');

        $this->assertDatabaseHas('users', ['id' => $piloto->id]);
    }

    public function test_no_se_puede_borrar_a_si_mismo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete("/usuarios/{$admin->id}")
            ->assertSessionHasErrors('usuario');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_borra_usuario_sin_referencias(): void
    {
        $user = User::factory()->create(['rol' => 'Piloto']);

        $this->actingAs($this->admin())
            ->delete("/usuarios/{$user->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
