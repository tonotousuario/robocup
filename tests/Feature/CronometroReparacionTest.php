<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\ParticipanteEncuentro;
use App\Models\Robot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CronometroReparacionTest extends TestCase
{
    use RefreshDatabase;

    private function inscripcionAprobada(Categoria $categoria): Inscripcion
    {
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
        InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $inscripcion->id_inscripcion]);

        return $inscripcion;
    }

    public function test_iniciar_reparacion_fija_la_hora(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:00:00'));
        $categoria = Categoria::factory()->combate()->create();
        $inscripcion = $this->inscripcionAprobada($categoria);

        $this->actingAs(User::factory()->juez()->create())
            ->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion")
            ->assertRedirect();

        $fresh = $inscripcion->fresh();
        $this->assertTrue($fresh->reparacion_usada);
        $this->assertNotNull($fresh->reparacion_iniciada_en);
        $this->assertSame('2026-06-09 10:00:00', $fresh->reparacion_iniciada_en->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_reparacion_sigue_siendo_una_sola_vez(): void
    {
        $categoria = Categoria::factory()->combate()->create();
        $inscripcion = $this->inscripcionAprobada($categoria);
        $juez = User::factory()->juez()->create();

        $this->actingAs($juez)->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion")->assertRedirect();
        $this->actingAs($juez)->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion")->assertSessionHasErrors('reparacion');
    }

    public function test_index_de_combate_expone_reparacion_iniciada_en(): void
    {
        $categoria = Categoria::factory()->combate()->create();
        $a = $this->inscripcionAprobada($categoria);
        $b = $this->inscripcionAprobada($categoria);
        $encuentro = Encuentro::factory()->create(['id_categoria' => $categoria->id_categoria]);
        ParticipanteEncuentro::create(['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $a->id_inscripcion]);
        ParticipanteEncuentro::create(['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $b->id_inscripcion]);

        $this->actingAs(User::factory()->juez()->create())
            ->get('/combate?categoria='.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page
                ->component('combate/index')
                ->has('encuentros.0.participantes.0.reparacion_iniciada_en')
            );
    }

    public function test_proyeccion_lista_reparaciones_activas(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-09 10:00:00'));
        $categoria = Categoria::factory()->combate()->create();

        $activo = $this->inscripcionAprobada($categoria);
        $activo->update(['reparacion_usada' => true, 'reparacion_iniciada_en' => now()]); // recién iniciada

        $vencido = $this->inscripcionAprobada($categoria);
        $vencido->update(['reparacion_usada' => true, 'reparacion_iniciada_en' => now()->subMinutes(6)]); // hace 6 min

        $this->get('/proyeccion/combate/'.$categoria->id_categoria)
            ->assertInertia(fn (Assert $page) => $page
                ->component('proyeccion/combate')
                ->has('reparacionesActivas', 1)
            );

        Carbon::setTestNow();
    }
}
