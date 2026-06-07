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
     * @return array{stats: array<int, array{label: string, value: int|string}>}
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
        ];
    }

    /**
     * @return array{stats: array<int, array{label: string, value: int|string}>}
     */
    private function juezStats(): array
    {
        return [
            'stats' => [
                ['label' => 'Inspecciones pendientes', 'value' => InspeccionChecklist::where('estado_aprobacion', 'Pendiente')->count()],
                ['label' => 'Encuentros por resolver', 'value' => Encuentro::whereDoesntHave('participantes', fn ($q) => $q->where('es_ganador', true))->count()],
            ],
        ];
    }

    /**
     * @return array{stats: array<int, array{label: string, value: int|string}>, robots: array<int, array{id_robot: int, nombre: string, categoria: ?string, estado_pago: string}>}
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
                'estado_pago' => $robot->inscripciones->last()?->estado_pago ?? 'Sin inscripción',
            ])
            ->values()
            ->all();

        return [
            'stats' => [
                ['label' => 'Mis robots', 'value' => count($robots)],
            ],
            'robots' => $robots,
        ];
    }
}
