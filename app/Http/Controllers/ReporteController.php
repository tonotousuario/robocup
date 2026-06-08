<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Inscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReporteController extends Controller
{
    public function index(Request $request): Response
    {
        $esAdmin = $request->user()->isAdministrador();

        return Inertia::render('reportes/index', [
            'puedeVerCaja' => $esAdmin,
            'caja' => $esAdmin ? $this->caja() : null,
            'posiciones' => $this->posiciones(),
            'emparejamientos' => $this->emparejamientosVigentes(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function caja(): array
    {
        return [
            'total_recaudado' => number_format((float) Inscripcion::where('estado_pago', 'Pagado')->sum('monto_pagado'), 2, '.', ''),
            'pagadas' => Inscripcion::where('estado_pago', 'Pagado')->count(),
            'pendientes' => Inscripcion::where('estado_pago', 'Pendiente')->count(),
            'canceladas' => Inscripcion::where('estado_pago', 'Cancelado')->count(),
            'por_categoria' => Categoria::orderBy('nombre')->get()->map(fn (Categoria $c) => [
                'categoria' => $c->nombre,
                'pagadas' => Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $c->id_categoria))->where('estado_pago', 'Pagado')->count(),
                'recaudado' => number_format((float) Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $c->id_categoria))->where('estado_pago', 'Pagado')->sum('monto_pagado'), 2, '.', ''),
            ])->values(),
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
                'id_inscripcion' => (int) $f->id_inscripcion,
                'robot' => $f->robot,
                'categoria' => $f->categoria,
                'mejor_tiempo' => $f->mejor_tiempo !== null ? (string) $f->mejor_tiempo : null,
                'intentos' => (int) $f->intentos,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function emparejamientosVigentes(): Collection
    {
        return DB::table('vista_emparejamientos')
            ->whereNotNull('id_inscripcion')
            ->select('id_encuentro', 'ronda', 'categoria', 'robot', DB::raw('es_ganador::int as ganador'))
            ->get()
            ->groupBy('id_encuentro')
            ->filter(fn ($filas) => $filas->count() === 2 && $filas->every(fn ($f) => (int) $f->ganador === 0))
            ->map(fn ($filas) => [
                'id_encuentro' => (int) $filas->first()->id_encuentro,
                'categoria' => $filas->first()->categoria,
                'ronda' => $filas->first()->ronda,
                'robots' => $filas->pluck('robot')->values(),
            ])
            ->values();
    }
}
