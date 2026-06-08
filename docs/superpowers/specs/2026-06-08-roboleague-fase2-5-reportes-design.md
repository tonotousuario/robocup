# RoboLeague — Fase 2.5: Reportes (Diseño)

**Fecha:** 2026-06-08
**Fase padre:** Fase 2 → sub-proyecto **2.5 Reportes**. (2.0–2.4 ya en `main`.)
**Alcance:** Reportes operativos de solo lectura — caja financiera (RF5.3), posiciones (RF5.2) y emparejamientos vigentes (RF5.1) — en una sola página, usando las vistas de BD `vista_posiciones` y `vista_emparejamientos`.

## Contexto

En `main`: Fases 1, 2.0–2.4. Las posiciones interactivas viven en Tiempos (2.4a) y los brackets en Combate (2.4b); 2.5 añade vistas **consolidadas de solo lectura** + el **reporte de caja** (nuevo). Patrón establecido: controlador + `AuthorizesRequests`/middleware `role`, Wayfinder, página índice React, nav por rol. Vistas de BD de Fase 1 (hasta ahora sin usar): 
- `vista_posiciones`: `id_inscripcion, id_robot, robot, id_categoria, categoria, mejor_tiempo, intentos` (mejor tiempo por inscripción en categorías de Tiempo).
- `vista_emparejamientos`: `id_encuentro, ronda, categoria, id_inscripcion, robot, puntos_obtenidos, es_ganador` (una fila por participante de cada encuentro).

Modelos: `Inscripcion` (`estado_pago`, `monto_pagado`, rel `robot()`), `Robot` (rel `categoria()`), `Categoria` (`nombre`, `tipo_evaluacion`).

## Decisiones de diseño

- **Una sola página `/reportes`** con secciones; la sección de Caja se incluye solo para Admin.
- Autorización: ruta `role:Administrador,Juez`; Caja solo Admin (flag `puedeVerCaja`).
- Caja: totales + desglose **por categoría**.
- Emparejamientos "vigentes" = encuentros con 2 participantes y **sin ganador**.
- Posiciones y emparejamientos se leen de las **vistas de BD** vía `DB::table(...)`.
- Solo lectura (sin acciones).

## Backend

### Rutas (`routes/web.php`)
```php
Route::middleware(['auth', 'verified', 'role:Administrador,Juez'])->group(function () {
    Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
});
```
(Middleware `role` ya existe y acepta lista separada por comas.)

### `ReporteController@index(Request)`
- `$esAdmin = $request->user()->isAdministrador();`
- **Caja** (solo si `$esAdmin`, si no `null`):
  - `total_recaudado` = `(float) Inscripcion::where('estado_pago','Pagado')->sum('monto_pagado')` formateado a 2 decimales (string).
  - `pagadas` / `pendientes` / `canceladas` = counts por `estado_pago`.
  - `por_categoria`: para cada categoría, # inscripciones Pagadas y suma recaudada:
    ```php
    Categoria::orderBy('nombre')->get()->map(fn ($c) => [
        'categoria' => $c->nombre,
        'pagadas' => Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $c->id_categoria))->where('estado_pago', 'Pagado')->count(),
        'recaudado' => number_format((float) Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $c->id_categoria))->where('estado_pago', 'Pagado')->sum('monto_pagado'), 2, '.', ''),
    ]);
    ```
    (Solo incluir categorías con al menos 1 pagada, o todas — incluir todas con 0 está bien.)
- **Posiciones** (RF5.2): `DB::table('vista_posiciones')->orderBy('categoria')->orderBy('mejor_tiempo')->get()` → mapear a `{ id_inscripcion, robot, categoria, mejor_tiempo, intentos }`. El front agrupa por `categoria` y numera posiciones.
- **Emparejamientos vigentes** (RF5.1): leer `vista_emparejamientos`; agrupar por `id_encuentro`; quedarse con encuentros que tengan exactamente 2 participantes y ninguno `es_ganador`; mapear a `{ id_encuentro, categoria, ronda, robots: [robotA, robotB] }`.
- Render `reportes/index` con `puedeVerCaja => $esAdmin`, `caja`, `posiciones`, `emparejamientos`.

## Frontend

### Tipos (`resources/js/types/models.ts`)
- `CajaPorCategoria`: `{ categoria:string; pagadas:number; recaudado:string }`.
- `ReporteCaja`: `{ total_recaudado:string; pagadas:number; pendientes:number; canceladas:number; por_categoria: CajaPorCategoria[] }`.
- `PosicionReporte`: `{ id_inscripcion:number; robot:string|null; categoria:string|null; mejor_tiempo:string|null; intentos:number }`.
- `EmparejamientoVigente`: `{ id_encuentro:number; categoria:string|null; ronda:string; robots:(string|null)[] }`.

### Página (`resources/js/pages/reportes/index.tsx`)
- Sección **Caja** (si `puedeVerCaja`): tarjetas (Total recaudado, Pagadas, Pendientes, Canceladas) reusando `StatCard`; tabla por categoría (Categoría · Pagadas · Recaudado).
- Sección **Posiciones**: agrupar `posiciones` por categoría; por grupo, tabla Pos · Robot · Mejor tiempo.
- Sección **Emparejamientos vigentes**: agrupar por categoría; lista "Robot A vs Robot B (ronda)".
- Empty states por sección. Breadcrumbs.
- Nav: "Reportes" (icono `BarChart3`) con `roles: ['Administrador','Juez']`.

## Estrategia de pruebas (feature, PostgreSQL)

- Autorización: Coach y Piloto → 403 en `/reportes`; Juez → 200 con `puedeVerCaja=false` y `caja=null`; Admin → 200 con `caja` presente.
- Caja: con inscripciones en distintos estados, `total_recaudado` = suma de `monto_pagado` de las Pagadas; `pagadas`/`pendientes`/`canceladas` correctos; `por_categoria` agrega correctamente por categoría del robot.
- Posiciones: sembrar intentos en una categoría de Tiempo y verificar que `posiciones` (desde `vista_posiciones`) trae el mejor tiempo ordenado por categoría/tiempo.
- Emparejamientos: generar un bracket; un encuentro con 2 participantes y sin ganador aparece en `emparejamientos`; un encuentro ya resuelto (con ganador) NO aparece.
- Usar `assertInertia` para props y `actingAs` con factories.

## Fuera de alcance (2.5)
Exportación CSV/PDF, gráficas, históricos inter-torneos (excluido por el doc), edición (es solo lectura), telemetría ESP32.

## Criterios de aceptación (DoD)
1. `php artisan test` 100% (previos + nuevos).
2. Caja solo Admin; posiciones/emparejamientos para Juez+Admin; Coach/Piloto 403 (tests).
3. Caja: totales y desglose por categoría correctos (tests).
4. Posiciones desde `vista_posiciones`; emparejamientos vigentes desde `vista_emparejamientos` (filtrados) (tests).
5. UI de una página con secciones condicionales; nav "Reportes" para Admin/Juez.
6. `vendor/bin/pint --dirty` limpio; `npm run build` sin errores TS.
