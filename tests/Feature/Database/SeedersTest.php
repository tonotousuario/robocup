<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_catalogos_se_siembran(): void
    {
        $this->seed();

        $this->assertDatabaseCount('categorias', 8);
        $this->assertDatabaseHas('categorias', ['nombre' => 'Mini Sumo Autónomo Profesional']);
        $this->assertDatabaseCount('tarifas', 4);
        $this->assertDatabaseCount('instituciones', 3);
        $this->assertDatabaseHas('users', ['email' => 'admin@roboleague.test', 'rol' => 'Administrador']);
    }
}
