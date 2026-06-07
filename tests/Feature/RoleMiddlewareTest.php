<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:Administrador'])
            ->get('/_test/solo-admin', fn () => 'ok');
    }

    public function test_administrador_puede_acceder(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)->get('/_test/solo-admin')->assertOk();
    }

    public function test_juez_recibe_403(): void
    {
        $juez = User::factory()->juez()->create();

        $this->actingAs($juez)->get('/_test/solo-admin')->assertForbidden();
    }

    public function test_invitado_es_bloqueado(): void
    {
        $this->get('/_test/solo-admin')->assertRedirect();
    }
}
