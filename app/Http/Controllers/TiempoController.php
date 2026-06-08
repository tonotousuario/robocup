<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarTiemposRequest;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\IntentoTiempo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TiempoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', IntentoTiempo::class);

        $categorias = Categoria::where('tipo_evaluacion', 'Tiempo')->orderBy('nombre')->get(['id_categoria', 'nombre']);
        $categoriaSeleccionada = (int) $request->query('categoria', (string) ($categorias->first()->id_categoria ?? 0));

        $competidores = collect();
        if ($categoriaSeleccionada > 0) {
            $inscripciones = Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $categoriaSeleccionada))
                ->whereHas('inspecciones', fn ($q) => $q->where('estado_aprobacion', 'Aprobado'))
                ->with(['robot', 'intentos'])
                ->get();

            $competidores = $inscripciones->map(function (Inscripcion $i) {
                $mejor = $i->intentos->isNotEmpty()
                    ? $i->intentos->min(fn (IntentoTiempo $t) => (float) $t->tiempo_logrado + (float) $t->penalizacion_segundos)
                    : null;

                return [
                    'id_inscripcion' => $i->id_inscripcion,
                    'robot' => $i->robot?->nombre,
                    'mejor_tiempo' => $mejor !== null ? number_format($mejor, 3, '.', '') : null,
                    'intentos' => $i->intentos->map(fn (IntentoTiempo $t) => [
                        'numero_vuelta' => $t->numero_vuelta,
                        'tiempo_logrado' => $t->tiempo_logrado,
                        'penalizacion_segundos' => $t->penalizacion_segundos,
                    ])->values(),
                ];
            })
                ->sortBy(fn (array $c) => $c['mejor_tiempo'] === null ? PHP_FLOAT_MAX : (float) $c['mejor_tiempo'])
                ->values();

            $posicion = 0;
            $competidores = $competidores->map(function (array $c) use (&$posicion) {
                if ($c['mejor_tiempo'] !== null) {
                    $posicion++;
                    $c['posicion'] = $posicion;
                } else {
                    $c['posicion'] = null;
                }

                return $c;
            });
        }

        return Inertia::render('tiempos/index', [
            'categorias' => $categorias,
            'categoriaSeleccionada' => $categoriaSeleccionada > 0 ? $categoriaSeleccionada : null,
            'competidores' => $competidores->values(),
            'puedeCapturar' => $request->user()->isJuez() || $request->user()->isAdministrador(),
        ]);
    }

    public function guardar(GuardarTiemposRequest $request): RedirectResponse
    {
        $this->authorize('capturar', IntentoTiempo::class);

        $data = $request->validated();
        $inscripcion = Inscripcion::with('robot.categoria')->findOrFail($data['id_inscripcion']);

        if ($inscripcion->robot?->categoria?->tipo_evaluacion !== 'Tiempo') {
            return back()->withErrors(['id_inscripcion' => 'La categoría no es de tiempo.']);
        }

        if (! $inscripcion->inspecciones()->where('estado_aprobacion', 'Aprobado')->exists()) {
            return back()->withErrors(['id_inscripcion' => 'El robot no está aprobado.']);
        }

        foreach ($data['intentos'] as $intento) {
            if (($intento['tiempo_logrado'] ?? null) === null) {
                continue;
            }

            IntentoTiempo::updateOrCreate(
                ['id_inscripcion' => $inscripcion->id_inscripcion, 'numero_vuelta' => $intento['numero_vuelta']],
                ['tiempo_logrado' => $intento['tiempo_logrado'], 'penalizacion_segundos' => $intento['penalizacion_segundos'] ?? 0],
            );
        }

        return back()->with('success', 'Tiempos registrados.');
    }
}
