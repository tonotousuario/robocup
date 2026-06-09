<?php

namespace App\Http\Controllers;

use App\Enums\RolUsuario;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $props = match ($user->rol) {
            RolUsuario::Administrador => $this->adminStats(),
            RolUsuario::Juez => $this->juezStats(),
            RolUsuario::Coach, RolUsuario::Piloto => $this->robotOwnerStats($user),
        };

        return Inertia::render('dashboard', $props);
    }

    /**
     * @return array{
     *     stats: array<int, array{label: string, value: int|string}>,
     *     accionesRapidas: array<int, array{label: string, href: string, icon: string}>,
     *     atencion: array<int, array{label: string, value: int, href: string, tone: string}>
     * }
     */
    private function adminStats(): array
    {
        return [
            'stats' => [
                ['label' => 'Robots inscritos', 'value' => Inscripcion::distinct()->count('id_robot')],
                ['label' => 'Inscripciones pagadas', 'value' => Inscripcion::where('estado_pago', 'Pagado')->count()],
                ['label' => 'Inscripciones pendientes', 'value' => Inscripcion::where('estado_pago', 'Pendiente')->count()],
                ['label' => 'Total recaudado', 'value' => '$'.number_format((float) Inscripcion::where('estado_pago', 'Pagado')->sum('monto_pagado'), 2)],
                ['label' => 'Inspecciones pendientes', 'value' => InspeccionChecklist::where('estado_aprobacion', 'Pendiente')->count()],
            ],
            'accionesRapidas' => [
                ['label' => 'Inscribir robot', 'href' => route('inscripciones.index'), 'icon' => 'ClipboardList'],
                ['label' => 'Reportes y caja', 'href' => route('reportes.index'), 'icon' => 'BarChart3'],
                ['label' => 'Combate', 'href' => route('combate.index'), 'icon' => 'Swords'],
            ],
            'atencion' => [
                ['label' => 'Inscripciones pendientes de pago', 'value' => Inscripcion::where('estado_pago', 'Pendiente')->count(), 'href' => route('inscripciones.index'), 'tone' => 'warning'],
                ['label' => 'Inspecciones pendientes', 'value' => InspeccionChecklist::where('estado_aprobacion', 'Pendiente')->count(), 'href' => route('inspecciones.index'), 'tone' => 'warning'],
            ],
        ];
    }

    /**
     * @return array{
     *     stats: array<int, array{label: string, value: int|string}>,
     *     accionesRapidas: array<int, array{label: string, href: string, icon: string}>,
     *     atencion: array<int, array{label: string, value: int, href: string, tone: string}>
     * }
     */
    private function juezStats(): array
    {
        return [
            'stats' => [
                ['label' => 'Inspecciones pendientes', 'value' => InspeccionChecklist::where('estado_aprobacion', 'Pendiente')->count()],
                ['label' => 'Encuentros por resolver', 'value' => Encuentro::whereDoesntHave('participantes', fn ($q) => $q->where('es_ganador', true))->count()],
            ],
            'accionesRapidas' => [
                ['label' => 'Inspección', 'href' => route('inspecciones.index'), 'icon' => 'ClipboardCheck'],
                ['label' => 'Combate', 'href' => route('combate.index'), 'icon' => 'Swords'],
                ['label' => 'Tiempos', 'href' => route('tiempos.index'), 'icon' => 'Timer'],
            ],
            'atencion' => [
                ['label' => 'Inspecciones pendientes', 'value' => InspeccionChecklist::where('estado_aprobacion', 'Pendiente')->count(), 'href' => route('inspecciones.index'), 'tone' => 'warning'],
                ['label' => 'Encuentros por resolver', 'value' => Encuentro::whereDoesntHave('participantes', fn ($q) => $q->where('es_ganador', true))->count(), 'href' => route('combate.index'), 'tone' => 'accent'],
            ],
        ];
    }

    /**
     * @return array{
     *     stats: array<int, array{label: string, value: int|string}>,
     *     robots: array<int, array{id_robot: int, nombre: string, categoria: ?string, estado_pago: string}>,
     *     accionesRapidas: array<int, array{label: string, href: string, icon: string}>
     * }
     */
    private function robotOwnerStats(User $user): array
    {
        $robots = Robot::where('id_piloto', $user->id)
            ->with(['categoria', 'inscripciones'])
            ->get()
            ->map(fn (Robot $robot) => [
                'id_robot' => $robot->id_robot,
                'nombre' => $robot->nombre,
                'categoria' => $robot->categoria?->nombre,
                'estado_pago' => $robot->inscripciones->sortByDesc('id_inscripcion')->first()?->estado_pago ?? 'Sin inscripción',
            ])
            ->values()
            ->all();

        return [
            'stats' => [
                ['label' => 'Mis robots', 'value' => count($robots)],
            ],
            'robots' => $robots,
            'accionesRapidas' => [
                ['label' => 'Mis robots', 'href' => route('robots.index'), 'icon' => 'Bot'],
                ['label' => 'Inscripciones', 'href' => route('inscripciones.index'), 'icon' => 'ClipboardList'],
            ],
        ];
    }
}
