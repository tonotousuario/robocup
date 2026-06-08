<?php

namespace App\Http\Controllers;

use App\Http\Requests\AmonestarRequest;
use App\Http\Requests\GenerarBracketRequest;
use App\Http\Requests\RegistrarGanadorRequest;
use App\Http\Requests\RegistrarRoundRequest;
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
                ->with(['participantes.inscripcion.robot', 'rounds', 'amonestaciones'])
                ->get()
                ->map(fn (Encuentro $e) => [
                    'id_encuentro' => $e->id_encuentro,
                    'ronda' => $e->ronda,
                    'id_encuentro_siguiente' => $e->id_encuentro_siguiente,
                    'tipo_resultado' => $e->tipo_resultado,
                    'marcador' => $e->rounds->filter(fn ($r) => $r->id_inscripcion_ganador !== null)
                        ->groupBy('id_inscripcion_ganador')->map->count(),
                    'amonestaciones' => $e->amonestaciones->map(fn ($am) => [
                        'id_amonestacion' => $am->id_amonestacion,
                        'id_inscripcion' => $am->id_inscripcion,
                        'motivo' => $am->motivo,
                        'numero_round' => $am->numero_round,
                    ])->values(),
                    'participantes' => $e->participantes->map(fn (ParticipanteEncuentro $p) => [
                        'id_inscripcion' => $p->id_inscripcion,
                        'robot' => $p->inscripcion?->robot?->nombre,
                        'es_ganador' => $p->es_ganador,
                        'reparacion_usada' => $p->inscripcion?->reparacion_usada ?? false,
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

    private function asegurarGestionable(Encuentro $encuentro): ?RedirectResponse
    {
        $participantes = $encuentro->participantes;

        if ($participantes->count() < 2) {
            return back()->withErrors(['encuentro' => 'El encuentro aún no tiene dos participantes.']);
        }

        if ($participantes->firstWhere('es_ganador', true) !== null) {
            return back()->withErrors(['encuentro' => 'El encuentro ya tiene un ganador.']);
        }

        return null;
    }

    public function registrarRound(RegistrarRoundRequest $request, Encuentro $encuentro): RedirectResponse
    {
        $this->authorize('registrarGanador', Encuentro::class);

        if ($error = $this->asegurarGestionable($encuentro)) {
            return $error;
        }

        $repetido = $request->boolean('repetido');
        $idGanador = $request->input('id_inscripcion_ganador') !== null ? $request->integer('id_inscripcion_ganador') : null;

        if (! $repetido) {
            if ($idGanador === null || ! $encuentro->participantes->contains('id_inscripcion', $idGanador)) {
                return back()->withErrors(['id_inscripcion_ganador' => 'Selecciona un robot participante como ganador del round.']);
            }
        }

        $this->bracket->registrarRound($encuentro, $idGanador, $repetido);

        return back()->with('success', 'Round registrado.');
    }

    public function ganarPorDefault(Request $request, Encuentro $encuentro): RedirectResponse
    {
        $this->authorize('registrarGanador', Encuentro::class);

        if ($error = $this->asegurarGestionable($encuentro)) {
            return $error;
        }

        $id = $request->integer('id_inscripcion');
        if (! $encuentro->participantes->contains('id_inscripcion', $id)) {
            return back()->withErrors(['id_inscripcion' => 'Ese robot no participa en este encuentro.']);
        }

        $this->bracket->ganarPorDefault($encuentro, $id);

        return back()->with('success', 'Ganador por default registrado.');
    }

    public function descalificar(Request $request, Encuentro $encuentro): RedirectResponse
    {
        $this->authorize('registrarGanador', Encuentro::class);

        if ($error = $this->asegurarGestionable($encuentro)) {
            return $error;
        }

        $id = $request->integer('id_inscripcion');
        if (! $encuentro->participantes->contains('id_inscripcion', $id)) {
            return back()->withErrors(['id_inscripcion' => 'Ese robot no participa en este encuentro.']);
        }

        $this->bracket->descalificar($encuentro, $id);

        return back()->with('success', 'Robot descalificado.');
    }

    public function amonestar(AmonestarRequest $request, Encuentro $encuentro): RedirectResponse
    {
        $this->authorize('registrarGanador', Encuentro::class);

        $id = $request->integer('id_inscripcion');
        if (! $encuentro->participantes->contains('id_inscripcion', $id)) {
            return back()->withErrors(['id_inscripcion' => 'Ese robot no participa en este encuentro.']);
        }

        $this->bracket->amonestar($encuentro, $id, $request->string('motivo')->toString(), $request->user()->id, $request->input('numero_round') !== null ? $request->integer('numero_round') : null);

        return back()->with('success', 'Amonestación registrada.');
    }

    public function marcarReparacion(Request $request, Inscripcion $inscripcion): RedirectResponse
    {
        $this->authorize('registrarGanador', Encuentro::class);

        if ($inscripcion->reparacion_usada) {
            return back()->withErrors(['reparacion' => 'Este robot ya usó su tiempo de reparación.']);
        }

        $inscripcion->update(['reparacion_usada' => true]);

        return back()->with('success', 'Tiempo de reparación registrado.');
    }
}
