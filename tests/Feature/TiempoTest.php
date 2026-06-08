<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\IntentoTiempo;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TiempoTest extends TestCase
{
    use RefreshDatabase;

    private function juez(): User
    {
        return User::factory()->juez()->create();
    }

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'Administrador']);
    }

    /** Crea una inscripción aprobada de una categoría de tiempo y devuelve [inscripcion, categoria]. */
    private function inscripcionAprobadaTiempo(?Categoria $categoria = null): Inscripcion
    {
        $categoria ??= Categoria::factory()->tiempo()->create();
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
        InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $inscripcion->id_inscripcion]);

        return $inscripcion;
    }

    public function test_todos_los_roles_ven_el_index(): void
    {
        foreach (['Administrador', 'Juez', 'Coach', 'Piloto'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))
                ->get('/tiempos')
                ->assertOk();
        }
    }

    public function test_coach_y_piloto_no_pueden_capturar(): void
    {
        $inscripcion = $this->inscripcionAprobadaTiempo();
        $payload = [
            'id_inscripcion' => $inscripcion->id_inscripcion,
            'intentos' => [['numero_vuelta' => 1, 'tiempo_logrado' => 12.5, 'penalizacion_segundos' => 0]],
        ];

        $this->actingAs(User::factory()->coach()->create())->post('/tiempos', $payload)->assertForbidden();
        $this->actingAs(User::factory()->create(['rol' => 'Piloto']))->post('/tiempos', $payload)->assertForbidden();
    }

    public function test_juez_captura_tres_intentos(): void
    {
        $inscripcion = $this->inscripcionAprobadaTiempo();

        $this->actingAs($this->juez())
            ->post('/tiempos', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'intentos' => [
                    ['numero_vuelta' => 1, 'tiempo_logrado' => 20.0, 'penalizacion_segundos' => 0],
                    ['numero_vuelta' => 2, 'tiempo_logrado' => 18.5, 'penalizacion_segundos' => 1.0],
                    ['numero_vuelta' => 3, 'tiempo_logrado' => 19.0, 'penalizacion_segundos' => 0],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(3, IntentoTiempo::where('id_inscripcion', $inscripcion->id_inscripcion)->count());
    }

    public function test_re_capturar_actualiza_por_vuelta(): void
    {
        $inscripcion = $this->inscripcionAprobadaTiempo();
        $juez = $this->juez();

        $post = fn (float $t) => $this->actingAs($juez)->post('/tiempos', [
            'id_inscripcion' => $inscripcion->id_inscripcion,
            'intentos' => [['numero_vuelta' => 1, 'tiempo_logrado' => $t, 'penalizacion_segundos' => 0]],
        ]);

        $post(20.0)->assertRedirect();
        $post(15.0)->assertRedirect();

        $this->assertSame(1, IntentoTiempo::where('id_inscripcion', $inscripcion->id_inscripcion)->count());
        $this->assertSame('15.000', IntentoTiempo::where('id_inscripcion', $inscripcion->id_inscripcion)->first()->tiempo_logrado);
    }

    public function test_no_se_captura_si_no_esta_aprobada(): void
    {
        $categoria = Categoria::factory()->tiempo()->create();
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);

        $this->actingAs($this->juez())
            ->post('/tiempos', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'intentos' => [['numero_vuelta' => 1, 'tiempo_logrado' => 12.5, 'penalizacion_segundos' => 0]],
            ])
            ->assertSessionHasErrors('id_inscripcion');

        $this->assertSame(0, IntentoTiempo::where('id_inscripcion', $inscripcion->id_inscripcion)->count());
    }

    public function test_no_se_captura_si_categoria_es_combate(): void
    {
        $categoria = Categoria::factory()->combate()->create();
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
        InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $inscripcion->id_inscripcion]);

        $this->actingAs($this->juez())
            ->post('/tiempos', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'intentos' => [['numero_vuelta' => 1, 'tiempo_logrado' => 12.5, 'penalizacion_segundos' => 0]],
            ])
            ->assertSessionHasErrors('id_inscripcion');
    }

    public function test_ranking_ordena_por_mejor_tiempo(): void
    {
        $categoria = Categoria::factory()->tiempo()->create();
        $lento = $this->inscripcionAprobadaTiempo($categoria);
        $rapido = $this->inscripcionAprobadaTiempo($categoria);
        $sinTiempos = $this->inscripcionAprobadaTiempo($categoria);

        IntentoTiempo::create(['id_inscripcion' => $lento->id_inscripcion, 'numero_vuelta' => 1, 'tiempo_logrado' => 20.0, 'penalizacion_segundos' => 0]);
        IntentoTiempo::create(['id_inscripcion' => $rapido->id_inscripcion, 'numero_vuelta' => 1, 'tiempo_logrado' => 10.0, 'penalizacion_segundos' => 0]);

        $this->actingAs($this->juez())
            ->get('/tiempos?categoria='.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page
                ->component('tiempos/index')
                ->where('competidores.0.id_inscripcion', $rapido->id_inscripcion)
                ->where('competidores.0.posicion', 1)
                ->where('competidores.1.id_inscripcion', $lento->id_inscripcion)
                ->where('competidores.2.id_inscripcion', $sinTiempos->id_inscripcion)
                ->where('competidores.2.posicion', null)
            );
    }

    public function test_numero_vuelta_invalido_es_rechazado(): void
    {
        $inscripcion = $this->inscripcionAprobadaTiempo();

        $this->actingAs($this->juez())
            ->post('/tiempos', [
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'intentos' => [['numero_vuelta' => 4, 'tiempo_logrado' => 12.5, 'penalizacion_segundos' => 0]],
            ])
            ->assertSessionHasErrors('intentos.0.numero_vuelta');
    }
}
