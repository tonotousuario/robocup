<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerarBracketRequest;
use App\Http\Requests\RegistrarGanadorRequest;
use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\ParticipanteEncuentro;
use App\Services\BracketService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EncuentroController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private BracketService $bracket) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Encuentro::class);

        $categorias = Categoria::where('tipo_evaluacion', 'Combate')->orderBy('nombre')->get(['id_categoria', 'nombre']);
        $categoriaSeleccionada = (int) $request->query('categoria', (string) ($categorias->first()->id_categoria ?? 0));

        $encuentros = collect();
        $aprobadosCount = 0;
        if ($categoriaSeleccionada > 0) {
            $encuentros = Encuentro::where('id_categoria', $categoriaSeleccionada)
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

            $aprobadosCount = Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $categoriaSeleccionada))
                ->whereHas('inspecciones', fn ($q) => $q->where('estado_aprobacion', 'Aprobado'))
                ->count();
        }

        return Inertia::render('combate/index', [
            'categorias' => $categorias,
            'categoriaSeleccionada' => $categoriaSeleccionada > 0 ? $categoriaSeleccionada : null,
            'encuentros' => $encuentros,
            'puedeGenerar' => $request->user()->isAdministrador(),
            'puedeRegistrar' => $request->user()->isJuez() || $request->user()->isAdministrador(),
            'aprobadosCount' => $aprobadosCount,
        ]);
    }

    public function generar(GenerarBracketRequest $request): RedirectResponse
    {
        $this->authorize('generar', Encuentro::class);

        $categoria = Categoria::findOrFail($request->integer('id_categoria'));

        if ($categoria->tipo_evaluacion !== 'Combate') {
            return back()->withErrors(['id_categoria' => 'La categoría no es de combate.']);
        }

        try {
            $this->bracket->generar($categoria);
        } catch (\DomainException $e) {
            return back()->withErrors(['id_categoria' => $e->getMessage()]);
        }

        return back()->with('success', 'Bracket generado.');
    }

    public function registrarGanador(RegistrarGanadorRequest $request, Encuentro $encuentro): RedirectResponse
    {
        $this->authorize('registrarGanador', Encuentro::class);

        $idInscripcion = $request->integer('id_inscripcion');
        $participantes = $encuentro->participantes;

        if ($participantes->count() < 2) {
            return back()->withErrors(['id_inscripcion' => 'El encuentro aún no tiene dos participantes.']);
        }

        if ($participantes->firstWhere('es_ganador', true) !== null) {
            return back()->withErrors(['id_inscripcion' => 'El encuentro ya tiene un ganador.']);
        }

        if (! $participantes->contains('id_inscripcion', $idInscripcion)) {
            return back()->withErrors(['id_inscripcion' => 'Ese robot no participa en este encuentro.']);
        }

        $this->bracket->registrarGanador($encuentro, $idInscripcion);

        return back()->with('success', 'Ganador registrado.');
    }
}
