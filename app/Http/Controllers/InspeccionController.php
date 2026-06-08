<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarInspeccionRequest;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InspeccionController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InspeccionChecklist::class);

        $user = $request->user();

        $query = Inscripcion::with(['robot.categoria', 'robot.piloto', 'inspecciones'])->orderBy('id_inscripcion');

        if ($user->isPiloto()) {
            $query->whereHas('robot', fn ($q) => $q->where('id_piloto', $user->id));
        } else {
            $query->where('estado_pago', 'Pagado');
        }

        $inscripciones = $query->get()->map(function (Inscripcion $i) {
            $inspeccion = $i->inspecciones->first();

            return [
                'id_inscripcion' => $i->id_inscripcion,
                'robot' => $i->robot?->nombre,
                'categoria' => $i->robot?->categoria?->nombre,
                'piloto' => $i->robot?->piloto ? $i->robot->piloto->name.' '.$i->robot->piloto->apellidos : null,
                'peso_maximo_g' => $i->robot?->categoria?->peso_maximo_g,
                'dimensiones_maximas' => $i->robot?->categoria?->dimensiones_maximas,
                'estado_pago' => $i->estado_pago,
                'estado' => $inspeccion?->estado_aprobacion ?? 'Pendiente',
                'inspeccion' => $inspeccion ? [
                    'peso_medido_g' => $inspeccion->peso_medido_g,
                    'dimensiones_medidas' => $inspeccion->dimensiones_medidas,
                    'estado_aprobacion' => $inspeccion->estado_aprobacion,
                    'observaciones' => $inspeccion->observaciones,
                ] : null,
            ];
        })->values();

        return Inertia::render('inspecciones/index', [
            'inspecciones' => $inscripciones,
            'puedeInspeccionar' => $user->isJuez() || $user->isAdministrador(),
        ]);
    }

    public function guardar(GuardarInspeccionRequest $request): RedirectResponse
    {
        $this->authorize('guardar', InspeccionChecklist::class);

        $data = $request->validated();
        $inscripcion = Inscripcion::findOrFail($data['id_inscripcion']);

        if ($inscripcion->estado_pago !== 'Pagado') {
            return back()->withErrors(['id_inscripcion' => 'La inscripción no está pagada; no puede inspeccionarse.']);
        }

        InspeccionChecklist::updateOrCreate(
            ['id_inscripcion' => $inscripcion->id_inscripcion],
            [
                'id_juez' => $request->user()->id,
                'peso_medido_g' => $data['peso_medido_g'],
                'dimensiones_medidas' => $data['dimensiones_medidas'],
                'estado_aprobacion' => $data['estado_aprobacion'],
                'observaciones' => $data['observaciones'] ?? null,
            ],
        );

        return back()->with('success', 'Inspección registrada.');
    }
}
