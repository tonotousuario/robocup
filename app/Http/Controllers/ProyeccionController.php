<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\ParticipanteEncuentro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProyeccionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('proyeccion/index', [
            'categoriasCombate' => Categoria::where('tipo_evaluacion', 'Combate')->orderBy('nombre')->get(['id_categoria', 'nombre']),
        ]);
    }

    public function show(Categoria $categoria): Response
    {
        $encuentros = Encuentro::where('id_categoria', $categoria->id_categoria)
            ->with(['participantes.inscripcion.robot'])
            ->get()
            ->map(fn (Encuentro $e) => [
                'id_encuentro' => $e->id_encuentro,
                'ronda' => $e->ronda,
                'id_encuentro_siguiente' => $e->id_encuentro_siguiente,
                'participantes' => $e->participantes->map(fn (ParticipanteEncuentro $p) => [
                    'id_inscripcion' => $p->id_inscripcion,
                    'robot' => $p->inscripcion?->robot?->nombre,
                    'es_ganador' => $p->es_ganador,
                ])->values(),
            ])->values();

        return Inertia::render('proyeccion/combate', [
            'categoria' => ['id_categoria' => $categoria->id_categoria, 'nombre' => $categoria->nombre],
            'encuentros' => $encuentros,
            'enVivo' => $this->enVivo($encuentros),
            'posiciones' => $this->posiciones(),
        ]);
    }

    /**
     * El encuentro vigente (2 participantes, sin ganador) de la ronda más avanzada.
     *
     * @param  Collection<int, array<string, mixed>>  $encuentros
     * @return array<string, mixed>|null
     */
    private function enVivo(Collection $encuentros): ?array
    {
        $orden = ['Final' => 1, 'Semifinal' => 2, 'Cuartos' => 3, 'Octavos' => 4, 'Dieciseisavos' => 5];

        $vigentes = $encuentros
            ->filter(fn (array $e) => count($e['participantes']) === 2
                && collect($e['participantes'])->every(fn (array $p) => ! $p['es_ganador']))
            ->sortBy(fn (array $e) => $orden[$e['ronda']] ?? 99);

        $e = $vigentes->first();
        if ($e === null) {
            return null;
        }

        return [
            'id_encuentro' => $e['id_encuentro'],
            'ronda' => $e['ronda'],
            'robots' => collect($e['participantes'])->pluck('robot')->values(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function posiciones(): Collection
    {
        return DB::table('vista_posiciones')
            ->orderBy('categoria')
            ->orderBy('mejor_tiempo')
            ->get()
            ->map(fn ($f) => [
                'robot' => $f->robot,
                'categoria' => $f->categoria,
                'mejor_tiempo' => $f->mejor_tiempo !== null ? (string) $f->mejor_tiempo : null,
            ]);
    }
}
