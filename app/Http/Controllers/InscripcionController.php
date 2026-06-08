<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInscripcionRequest;
use App\Models\Inscripcion;
use App\Models\Robot;
use App\Services\TarifaService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InscripcionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private TarifaService $tarifas) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Inscripcion::class);

        $user = $request->user();

        $query = Inscripcion::with(['robot.piloto', 'robot.categoria', 'tarifa'])->orderByDesc('id_inscripcion');

        if (! $user->isAdministrador()) {
            $query->whereHas('robot', fn ($q) => $q->where('id_piloto', $user->id));
        }

        $inscripciones = $query->get()->map(fn (Inscripcion $i) => [
            'id_inscripcion' => $i->id_inscripcion,
            'robot' => $i->robot?->nombre,
            'categoria' => $i->robot?->categoria?->nombre,
            'piloto' => $i->robot?->piloto ? $i->robot->piloto->name.' '.$i->robot->piloto->apellidos : null,
            'tarifa' => $i->tarifa?->descripcion,
            'monto_pagado' => $i->monto_pagado,
            'estado_pago' => $i->estado_pago,
        ])->values();

        $robotsQuery = Robot::whereDoesntHave('inscripciones', fn ($q) => $q->where('estado_pago', '!=', 'Cancelado'))->orderBy('nombre');
        if (! $user->isAdministrador()) {
            $robotsQuery->where('id_piloto', $user->id);
        }

        $tarifaVigente = $this->tarifas->vigenteParaHoy();

        return Inertia::render('inscripciones/index', [
            'inscripciones' => $inscripciones,
            'robotsInscribibles' => $robotsQuery->get(['id_robot', 'nombre']),
            'tarifaVigente' => $tarifaVigente ? ['descripcion' => $tarifaVigente->descripcion, 'monto' => $tarifaVigente->monto] : null,
        ]);
    }

    public function store(StoreInscripcionRequest $request): RedirectResponse
    {
        $this->authorize('create', Inscripcion::class);

        $robot = Robot::findOrFail($request->integer('id_robot'));
        $user = $request->user();

        if (! $user->isAdministrador() && (int) $robot->id_piloto !== $user->id) {
            return back()->withErrors(['id_robot' => 'Ese robot no te pertenece.']);
        }

        if ($robot->inscripciones()->where('estado_pago', '!=', 'Cancelado')->exists()) {
            return back()->withErrors(['id_robot' => 'Este robot ya tiene una inscripción activa.']);
        }

        $tarifa = $this->tarifas->vigenteParaHoy();
        if ($tarifa === null) {
            return back()->withErrors(['id_robot' => 'No hay una tarifa vigente para hoy.']);
        }

        Inscripcion::create([
            'id_robot' => $robot->id_robot,
            'id_tarifa' => $tarifa->id_tarifa,
            'monto_pagado' => 0,
            'estado_pago' => 'Pendiente',
        ]);

        return back()->with('success', 'Robot inscrito.');
    }

    public function pagar(Inscripcion $inscripcion): RedirectResponse
    {
        $this->authorize('pagar', $inscripcion);

        if ($inscripcion->estado_pago !== 'Pendiente') {
            return back()->withErrors(['estado_pago' => 'Solo se pueden cobrar inscripciones pendientes.']);
        }

        $inscripcion->update([
            'estado_pago' => 'Pagado',
            'monto_pagado' => $inscripcion->tarifa?->monto ?? 0,
        ]);

        return back()->with('success', 'Inscripción marcada como pagada.');
    }

    public function cancelar(Inscripcion $inscripcion): RedirectResponse
    {
        $this->authorize('cancelar', $inscripcion);

        if ($inscripcion->estado_pago !== 'Pendiente') {
            return back()->withErrors(['estado_pago' => 'Solo se pueden cancelar inscripciones pendientes.']);
        }

        $inscripcion->update(['estado_pago' => 'Cancelado']);

        return back()->with('success', 'Inscripción cancelada.');
    }
}
