# RoboLeague — Cronómetro de tiempo de reparación (Diseño)

**Fecha:** 2026-06-09
**Origen:** Petición del usuario (refinado de UI) — cuenta regresiva visible de los 5 min de reparación del reglamento RGO.
**Alcance:** Hacer visible y sincronizada la cuenta regresiva de 5 min del tiempo de reparación (ya registrado por robot), en el panel de Combate y en la pantalla de Proyección. Excluye pausar/reanudar, alarmas y reinicio.

## Contexto

En `main` ya están: el tiempo de reparación **por robot** (`inscripciones.reparacion_usada` bool + acción `EncuentroController::marcarReparacion` + botón en el panel de Combate `registrar-ganador-control.tsx`/`PanelEncuentro`) y el **Modo Proyección** (`ProyeccionController::show` renderiza `proyeccion/combate` con bracket/enVivo/posiciones; layout full-screen; polling 5 s). Falta que la reparación tenga una **cuenta regresiva** y que sea **sincronizada entre pantallas**.

## Decisiones de diseño

- **Persistir la hora de inicio**: nueva columna `inscripciones.reparacion_iniciada_en` (timestamp nullable). "Iniciar reparación" fija `reparacion_usada=true` + `reparacion_iniciada_en=now()` (sigue siendo una sola vez por robot).
- **Cuenta en cliente desde el timestamp persistido**: `fin = reparacion_iniciada_en + 300s`; `restante = fin − ahora`. Todas las pantallas coinciden porque parten del mismo inicio (confirmado por el usuario). El servidor NO envía segundos restantes por poll.
- **Constante** `REPARACION_SEGUNDOS = 300` (5 min) en backend y front.
- **Mostrar** en panel de Combate (Juez/Admin) y en una franja superior de la Proyección (todas las reparaciones activas de la categoría proyectada).
- Autorización sin cambios (Juez+Admin inician; misma ruta/policy).

## Backend

### Migración
- `Schema::table('inscripciones', ...)`: `$table->timestamp('reparacion_iniciada_en')->nullable();`.

### `Inscripcion`
- Añadir `reparacion_iniciada_en` a `#[Fillable]` y `casts()` → `'reparacion_iniciada_en' => 'datetime'`.

### `EncuentroController::marcarReparacion` (modificar)
- Guard existente: si `reparacion_usada` → `back()->withErrors(['reparacion' => 'Este robot ya usó su tiempo de reparación.'])`.
- Si no: `$inscripcion->update(['reparacion_usada' => true, 'reparacion_iniciada_en' => now()]);`.
- (Política y ruta `inscripciones/{inscripcion}/reparacion` sin cambios.)

### Combate `index` (modificar el mapeo de participantes)
- Añadir a cada participante `reparacion_iniciada_en` (ISO string o null) además del `reparacion_usada` existente.

### `ProyeccionController::show` (añadir prop)
- `reparacionesActivas`: inscripciones de robots de esa categoría cuyo `reparacion_iniciada_en` no es null y está dentro de los últimos `REPARACION_SEGUNDOS` segundos:
  ```php
  Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $categoria->id_categoria))
      ->whereNotNull('reparacion_iniciada_en')
      ->where('reparacion_iniciada_en', '>=', now()->subSeconds(300))
      ->with('robot')
      ->get()
      ->map(fn ($i) => ['robot' => $i->robot?->nombre, 'reparacion_iniciada_en' => $i->reparacion_iniciada_en?->toIso8601String()]);
  ```
- Definir `REPARACION_SEGUNDOS = 300` como constante de clase (o `App\Support`).

## Frontend

### Hook `useCuentaRegresiva(finIso: string)` (`resources/js/hooks/use-cuenta-regresiva.ts`)
- Calcula `segundosRestantes = max(0, floor((Date.parse(finIso) - Date.now())/1000))`; tick con `setInterval(1000)`; cleanup al desmontar. Devuelve `{ segundosRestantes, mmss }` (`mmss` formateado `M:SS`). Fin de la cuenta cuando `segundosRestantes === 0`.

### Tipos (`resources/js/types/models.ts`)
- `ParticipanteBracket`: añadir `reparacion_iniciada_en: string | null` (junto al `reparacion_usada` existente).
- `ReparacionActiva`: `{ robot: string | null; reparacion_iniciada_en: string }`.
- (La página de proyección `proyeccion/combate` recibe `reparacionesActivas: ReparacionActiva[]`.)

### Panel de combate (`registrar-ganador-control.tsx`)
- El botón de reparación: si `!reparacion_usada` → "Iniciar reparación (5 min)" (patch a `marcarReparacion`). Si `reparacion_usada` y `reparacion_iniciada_en` con restante > 0 → mostrar **cuenta regresiva mm:ss** (usando `useCuentaRegresiva(inicio+300s)`). Si restante 0 → "Reparación terminada". Calcular `fin` = `reparacion_iniciada_en` + 300 s en el cliente.

### Proyección (`proyeccion/combate.tsx`)
- Franja superior (visible en las 3 vistas) que mapea `reparacionesActivas`; cada una muestra `"{robot} · reparación M:SS"` con `useCuentaRegresiva`. Si la lista está vacía o todas llegaron a 0, la franja no se muestra. El polling de 5 s existente refresca la lista; el tick por segundo es local.

## Estrategia de pruebas (feature, PostgreSQL)

- Migración: `reparacion_iniciada_en` existe y es nullable (default null).
- `marcarReparacion` (HTTP): primera vez → `reparacion_usada=true` y `reparacion_iniciada_en` ≈ now (assert no null / dentro de margen); segunda vez → error de sesión.
- Combate `index`: el participante incluye `reparacion_iniciada_en` (null si no iniciada).
- Proyección `show`: un robot con `reparacion_iniciada_en = now()` aparece en `reparacionesActivas`; uno con inicio hace 6 min (`now()->subMinutes(6)`) NO aparece. Usar `Carbon::setTestNow` para controlar el tiempo.
- Autorización: Coach/Piloto 403 al iniciar reparación (ruta existente; ya cubierto, confirmar sigue verde).
- (El tick por segundo y el formato mm:ss son de cliente; verificación visual.)

## Fuera de alcance
Pausar/reanudar el cronómetro, alarma/sonido al terminar, reinicio de la reparación (es una sola vez), sincronización por WebSockets (se usa el polling existente + cómputo desde el timestamp).

## Criterios de aceptación (DoD)
1. `reparacion_iniciada_en` persiste al iniciar; reparación sigue siendo una sola vez (tests).
2. Combate `index` expone `reparacion_iniciada_en`; el panel muestra cuenta regresiva mm:ss tras iniciar (visual).
3. Proyección expone `reparacionesActivas` (solo las dentro de 5 min) y muestra la franja con el conteo (test backend + visual).
4. Misma cuenta en panel y proyección (parten del timestamp persistido).
5. Autorización Juez+Admin intacta (test).
6. `php artisan test` 100%; `npm run build` sin errores; Pint limpio.
