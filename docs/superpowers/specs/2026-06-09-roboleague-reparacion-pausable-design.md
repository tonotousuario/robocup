# RoboLeague — Tiempo de reparación pausable (saldo de 5 min) (Diseño)

**Fecha:** 2026-06-09
**Origen:** Petición del usuario — pausar el tiempo de reparación y consumir el saldo de 5 min en varios tramos a lo largo de la competencia.
**Dependencia:** Construye sobre el cronómetro de reparación (en `main`). Conviene fusionar antes la rama del marcador-enriquecido para evitar solapes en `proyeccion/combate.tsx` y `models.ts`.
**Alcance:** Cambiar el modelo de reparación de "una vez, 5 min de corrido" a "saldo de 300 s consumible en tramos, con iniciar/pausar". Excluye historial auditado por tramo, alarmas y WebSockets.

## Contexto

En `main`: la reparación es `inscripciones.reparacion_usada` (bool) + `reparacion_iniciada_en` (timestamp). `EncuentroController::marcarReparacion` (PATCH `inscripciones/{inscripcion}/reparacion`) la marca usada y fija la hora; el panel de combate (`BotonReparacion` en `registrar-ganador-control.tsx`) y la franja de proyección (`proyeccion/combate.tsx` con `reparacionesActivas`) muestran una cuenta mm:ss vía `useCuentaRegresiva`. Tests que tocan reparación: `CronometroReparacionTest` y un caso en `CombateRoundsTest` (una sola vez).

## Decisiones de diseño (confirmadas)

- **Modelo saldo**: reemplazar `reparacion_usada` (bool) por:
  - `reparacion_segundos_consumidos` (int, default 0) — saldo gastado acumulado.
  - `reparacion_iniciada_en` (timestamp nullable) — no-null = cronómetro **corriendo** desde esa hora; null = en pausa/reposo.
- **Constante** `REPARACION_SEGUNDOS = 300`.
- **Restante** = `max(0, 300 − consumidos − (corriendo ? ahora − iniciada_en : 0))`.
- **Dos endpoints** explícitos: iniciar (reanudar) y pausar.
- Cuando el saldo se **agota corriendo**, al pausar/consultar se aplica clamp: consumidos no supera 300, restante 0, no reiniciable.
- Se **actualizan los tests existentes** de reparación al nuevo modelo.

## Backend

### Migración
- `Schema::table('inscripciones', ...)`:
  - `$table->integer('reparacion_segundos_consumidos')->default(0);`
  - (mantener `reparacion_iniciada_en` timestamp nullable, ya existe)
  - `$table->dropColumn('reparacion_usada');`
- `down()` inverso (re-añade `reparacion_usada` bool default false, dropea `reparacion_segundos_consumidos`).

### `Inscripcion`
- Quitar `reparacion_usada` de `#[Fillable]`/casts; añadir `reparacion_segundos_consumidos` a `#[Fillable]` + cast `integer`. Mantener `reparacion_iniciada_en` cast datetime.
- Helper de dominio (método en el modelo) `reparacionRestante(): int` = `max(0, 300 − consumidos − (iniciada_en ? now()->diffInSeconds(iniciada_en, true) : 0))` usando una constante `Inscripcion::REPARACION_SEGUNDOS = 300`. (Para `corriendo` usar el valor absoluto de diff.)

### `EncuentroController`
Reemplazar `marcarReparacion` por dos métodos (ambos `authorize('registrarGanador', Encuentro::class)`):
- **`iniciarReparacion(Inscripcion $inscripcion)`**: si `reparacion_iniciada_en` no null → error 'La reparación ya está corriendo.'; si `reparacionRestante() <= 0` → error 'Sin tiempo de reparación disponible.'; si no, `update(['reparacion_iniciada_en' => now()])`.
- **`pausarReparacion(Inscripcion $inscripcion)`**: si `reparacion_iniciada_en` null → error 'La reparación no está corriendo.'; si no, calcular `transcurrido = now()->diffInSeconds($inscripcion->reparacion_iniciada_en, true)`, `nuevoConsumido = min(300, $inscripcion->reparacion_segundos_consumidos + $transcurrido)`, `update(['reparacion_segundos_consumidos' => $nuevoConsumido, 'reparacion_iniciada_en' => null])`.
- `index` (combate): por participante exponer `reparacion_segundos_consumidos`, `reparacion_iniciada_en` (ISO|null), `reparacion_restante` (entero, `$inscripcion->reparacionRestante()`).
- `ProyeccionController::show` `reparacionesActivas`: ahora = inscripciones de la categoría con `reparacion_iniciada_en` **no null** (corriendo). Mapear `{ robot, reparacion_iniciada_en (ISO), reparacion_segundos_consumidos }` para que el front calcule el restante en vivo.

### Rutas (`routes/web.php`, grupo `auth, verified`)
Reemplazar la ruta `inscripciones/{inscripcion}/reparacion` por:
```php
Route::patch('inscripciones/{inscripcion}/reparacion/iniciar', [EncuentroController::class, 'iniciarReparacion'])->name('inscripciones.reparacion.iniciar');
Route::patch('inscripciones/{inscripcion}/reparacion/pausar', [EncuentroController::class, 'pausarReparacion'])->name('inscripciones.reparacion.pausar');
```

## Frontend

### Hook (`use-cuenta-regresiva.ts`)
- Mantener `useCuentaRegresiva(finIso)`. Para reparación, el "fin" se calcula como `iniciada_en + (300 − consumidos) s`. Se computa donde se use (panel y franja) a partir de `iniciada_en` + `consumidos`.

### Tipos (`resources/js/types/models.ts`)
- `ParticipanteBracket`: quitar `reparacion_usada`; añadir `reparacion_segundos_consumidos: number`, `reparacion_iniciada_en: string | null`, `reparacion_restante: number`.
- `ReparacionActiva`: `{ robot: string|null; reparacion_iniciada_en: string; reparacion_segundos_consumidos: number }`.

### Panel de combate (`BotonReparacion`)
- Si `reparacion_iniciada_en` no null (corriendo): cuenta mm:ss (fin = iniciada_en + (300−consumidos)) + botón **"Pausar"** (`pausarReparacion`).
- Si en reposo y `reparacion_restante > 0`: botón **"Iniciar reparación {robot} ({mm:ss disp.})"** (`iniciarReparacion`); el "disp." formatea `reparacion_restante`.
- Si `reparacion_restante === 0`: texto "Reparación agotada" (sin botón).
- `onError` → toast.

### Franja de proyección (`FranjaReparacion`)
- Para cada `reparacionActiva` (corriendo), cuenta mm:ss en vivo desde `iniciada_en + (300 − consumidos)`; si llega a 0, no se muestra (y el polling la quita al pausarse/agotarse).

## Estrategia de pruebas (feature, PostgreSQL; `Carbon::setTestNow`)

- **Iniciar**: fija `reparacion_iniciada_en` ≈ now; iniciar dos veces seguidas → segundo da error 'ya está corriendo'.
- **Pausar acumula**: iniciar en T0; avanzar 60 s; pausar → `reparacion_segundos_consumidos` = 60, `reparacion_iniciada_en` null.
- **Dos tramos**: tras pausar con 60, iniciar de nuevo; avanzar 30 s; pausar → consumidos = 90.
- **Sin saldo**: con consumidos = 300, iniciar → error 'sin tiempo disponible'.
- **Pausar sin correr**: pausar cuando `iniciada_en` null → error.
- **Clamp**: iniciar, avanzar 400 s (más que el saldo), pausar → consumidos = 300 (no 400+), restante 0.
- **Proyección**: una inscripción corriendo aparece en `reparacionesActivas`; una en pausa NO.
- **index**: el participante expone `reparacion_restante` correcto en reposo.
- **Autorización**: Coach/Piloto 403 en iniciar y pausar.
- Actualizar los tests existentes (`CronometroReparacionTest`, el caso de `CombateRoundsTest`) al nuevo modelo (sin `reparacion_usada`).

## Criterios de aceptación (DoD)
1. Modelo saldo (consumidos + iniciada_en) reemplaza el bool; restante con clamp a [0,300] (tests).
2. Iniciar/pausar funcionan, acumulan en varios tramos, bloquean sin saldo / sin correr (tests).
3. Proyección lista solo las corriendo; index expone restante (tests).
4. Panel: iniciar/pausar/agotada con cuenta mm:ss; franja en proyección con cuenta en vivo.
5. Autorización Juez+Admin intacta (test).
6. Tests existentes de reparación migrados al nuevo modelo; `php artisan test` 100%; `npm run build` sin errores; Pint limpio.
