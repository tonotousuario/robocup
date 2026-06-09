<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInscripcionRequest;
use App\Models\Categoria;
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

        $query = Inscripcion::with(['robot.piloto', 'robot.categoria', 'tarifa']);

        if (! $user->isAdministrador()) {
            $query->whereHas('robot', fn ($q) => $q->where('id_piloto', $user->id));
        }

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->whereHas('robot', fn ($r) => $r->where('nombre', 'ilike', "%{$q}%")
                ->orWhereHas('piloto', fn ($p) => $p->where('name', 'ilike', "%{$q}%")->orWhere('apellidos', 'ilike', "%{$q}%"))
                ->orWhereHas('categoria', fn ($c) => $c->where('nombre', 'ilike', "%{$q}%")));
        }

        if ($request->filled('estado')) {
            $query->where('estado_pago', $request->string('estado')->toString());
        }

        if ($request->filled('categoria')) {
            $query->whereHas('robot', fn ($r) => $r->where('id_categoria', $request->integer('categoria')));
        }

        $ordenables = ['id_inscripcion', 'estado_pago', 'monto_pagado'];
        $sort = in_array($request->query('sort'), $ordenables, true) ? $request->query('sort') : 'id_inscripcion';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $dir);

        $inscripciones = $query->paginate(15)->withQueryString()->through(fn (Inscripcion $i) => [
            'id_inscripcion' => $i->id_inscripcion,
            'robot' => $i->robot?->nombre,
            'categoria' => $i->robot?->categoria?->nombre,
            'piloto' => $i->robot?->piloto ? $i->robot->piloto->name.' '.$i->robot->piloto->apellidos : null,
            'tarifa' => $i->tarifa?->descripcion,
            'monto_pagado' => $i->monto_pagado,
            'estado_pago' => $i->estado_pago,
        ]);

        $robotsQuery = Robot::whereDoesntHave('inscripciones', fn ($q) => $q->where('estado_pago', '!=', 'Cancelado'))->orderBy('nombre');
        if (! $user->isAdministrador()) {
            $robotsQuery->where('id_piloto', $user->id);
        }

        $tarifaVigente = $this->tarifas->vigenteParaHoy();

        return Inertia::render('inscripciones/index', [
            'inscripciones' => $inscripciones,
            'robotsInscribibles' => $robotsQuery->get(['id_robot', 'nombre']),
            'tarifaVigente' => $tarifaVigente ? ['descripcion' => $tarifaVigente->descripcion, 'monto' => $tarifaVigente->monto] : null,
            'categorias' => Categoria::orderBy('nombre')->get(['id_categoria', 'nombre']),
            'filtros' => [
                'q' => $request->query('q', ''),
                'estado' => $request->query('estado', ''),
                'categoria' => $request->query('categoria', ''),
                'sort' => $sort,
                'dir' => $dir,
            ],
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
