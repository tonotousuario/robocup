# RoboLeague — Combate por rounds, amonestaciones y resultados especiales (Diseño)

**Fecha:** 2026-06-08
**Origen:** Análisis de brechas vs Reglamento RGO 2025 (`docs/2026-06-08-analisis-brechas-reglamento-rgo.md`), prioridad #1.
**Alcance:** Evolucionar el módulo de Combate (2.4b) de "un ganador por encuentro" a **mejor de 3 rounds** con repetición de round, bitácora de amonestaciones, victoria por default, descalificación a mitad de combate y tiempo de reparación por robot. Incluye sembrar categorías Amateur/Pro de sumo. Excluye Soccer/Drone/combate por puntos.

## Contexto

En `main`: Fases 1–2.5 + tema C0. El combate vive en `EncuentroController` + `BracketService` + `combate/index.tsx`; hoy el juez da 1 clic = ganador del encuentro (`registrarGanador` marca `es_ganador` en `participantes_encuentro` y avanza vía `id_encuentro_siguiente`). La proyección (`proyeccion/combate`) lee el ganador del encuentro vía `es_ganador`.

Modelos Fase 1 relevantes: `Encuentro` (`id_encuentro`, `id_categoria`, `ronda`, `id_encuentro_siguiente`; rel `participantes()`), `ParticipanteEncuentro` (PK compuesta, `es_ganador` bool, `puntos_obtenidos` sin uso), `Inscripcion` (rel `robot()`), `Categoria` (`tipo_evaluacion`). Trigger T2: participante requiere inspección Aprobado.

## Decisiones de diseño

- **Mejor de 3**: el juez registra ganador por round; el primer robot con **2 rounds ganados** gana el encuentro y avanza. No se exige jugar el 3er round si ya es 2-0.
- **Repetición de round**: un round puede marcarse `repetido` (ganador null) → no cuenta, queda en bitácora, se juega otro.
- **Amonestaciones**: bitácora pura (robot, motivo, round, juez, fecha); el juez decide el round por separado, informado por ellas.
- **Default** y **descalificación**: el juez decide el ganador del encuentro sin/deteniendo rounds; se registra `tipo_resultado`.
- **Tiempo de reparación**: **por robot** (bandera en `inscripciones`), usable una sola vez.
- `es_ganador` sigue representando el **ganador del encuentro** → bracket y proyección no cambian. `puntos_obtenidos` queda en desuso (los rounds se cuentan desde la tabla nueva).
- Autorización: reusa `EncuentroPolicy` (Juez+Admin gestionan; todos ven). Coach/Piloto 403.

## Modelo de datos (migraciones nuevas)

### `rounds_encuentro` (tabla nueva)
- `id_round` serial PK.
- `id_encuentro` int FK→encuentros (cascadeOnDelete).
- `numero_round` int (índice secuencial del intento dentro del encuentro: 1,2,3,…; un round repetido también ocupa un número).
- `id_inscripcion_ganador` int FK→inscripciones (nullable; null si `repetido`). ON DELETE: cascade junto al encuentro (los participantes ya cascada).
- `repetido` bool default false.
- `fecha` timestamp useCurrent.
- Modelo `RoundEncuentro` (`#[Fillable]`, `$timestamps=false`, cast `repetido` bool, rel `encuentro()`, `ganador()`).

### `amonestaciones` (tabla nueva)
- `id_amonestacion` serial PK.
- `id_encuentro` int FK→encuentros (cascadeOnDelete).
- `id_inscripcion` int FK→inscripciones (cascadeOnDelete).
- `id_juez` int FK→users (No Action).
- `numero_round` int nullable.
- `motivo` text.
- `fecha` timestamp useCurrent.
- Modelo `Amonestacion` (`#[Fillable]`, `$timestamps=false`, rel `encuentro()`, `inscripcion()`, `juez()`).

### Columnas nuevas
- `encuentros.tipo_resultado` varchar nullable, CHECK ∈ {Rounds, Default, Descalificacion} (null = sin resolver). Migración `Schema::table` + CHECK por `DB::statement`.
- `inscripciones.reparacion_usada` boolean default false.

## Backend

### `BracketService` (métodos nuevos; conserva `generar`/`registrarGanador`)
- `private decidirEncuentro(Encuentro $e, int $idGanador, string $tipo)`: marca `es_ganador=true` en ese participante, set `encuentros.tipo_resultado=$tipo`, y avanza al `id_encuentro_siguiente` (firstOrCreate, como hoy). Reutilizado por las tres vías.
- `registrarRound(Encuentro $e, ?int $idGanador, bool $repetido=false)`: crea `RoundEncuentro` con `numero_round` = (count actual + 1); si `!$repetido`, recalcula victorias por inscripción desde `rounds_encuentro`; si alguna llega a 2 → `decidirEncuentro($e, $idGanador, 'Rounds')`.
- `ganarPorDefault(Encuentro $e, int $idGanador)`: `decidirEncuentro($e, $idGanador, 'Default')`.
- `descalificar(Encuentro $e, int $idPerdedor)`: el otro participante (≠ idPerdedor) es ganador → `decidirEncuentro($e, $idOtro, 'Descalificacion')`.
- `amonestar(Encuentro $e, int $idInscripcion, string $motivo, int $idJuez, ?int $numeroRound)`: crea `Amonestacion`.
- (Reparación se maneja en el controlador sobre `Inscripcion`.)

### `EncuentroController` (acciones nuevas; reusa policy)
- `registrarRound(Request, Encuentro)`: authorize `registrarGanador`; valida 2 participantes, sin ganador aún; valida `id_inscripcion_ganador` pertenece (cuando no es repetido). Llama al servicio.
- `ganarPorDefault(Request, Encuentro)` / `descalificar(Request, Encuentro)`: authorize; valida sin ganador + pertenencia; llama al servicio.
- `amonestar(Request, Encuentro)`: authorize; valida `id_inscripcion` pertenece + `motivo` required; llama al servicio.
- `marcarReparacion(Request, Inscripcion)`: authorize (misma policy/`registrarGanador` ability, o nueva `gestionar`); si `reparacion_usada` ya true → `back()->withErrors`; si no, set true.
- `index` (modificar): además de los encuentros, para cada uno incluir `rounds` (lista) + `marcador` (victorias por inscripción) + `amonestaciones`; y exponer `reparacion_usada` por participante.
- Form Requests: `RegistrarRoundRequest` (`id_inscripcion_ganador` nullable|integer, `repetido` boolean), `AmonestarRequest` (`id_inscripcion` required|integer, `motivo` required|string, `numero_round` nullable|integer).

### Rutas (`routes/web.php`, grupo `auth, verified`)
```php
Route::patch('encuentros/{encuentro}/round', [EncuentroController::class, 'registrarRound'])->name('encuentros.round');
Route::patch('encuentros/{encuentro}/default', [EncuentroController::class, 'ganarPorDefault'])->name('encuentros.default');
Route::patch('encuentros/{encuentro}/descalificar', [EncuentroController::class, 'descalificar'])->name('encuentros.descalificar');
Route::post('encuentros/{encuentro}/amonestacion', [EncuentroController::class, 'amonestar'])->name('encuentros.amonestar');
Route::patch('inscripciones/{inscripcion}/reparacion', [EncuentroController::class, 'marcarReparacion'])->name('inscripciones.reparacion');
```
(Se conserva `encuentros/{encuentro}/ganador` por compatibilidad, o se retira si la UI ya no lo usa — ver plan.)

## Frontend (`combate/index.tsx` + componentes)

Reemplazar el control de "Marcar ganador" por un **panel de encuentro** para `puedeRegistrar`, en encuentros con 2 participantes y sin ganador:
- **Marcador de rounds**: "RobotA m – n RobotB" (desde `marcador`), botones "Gana round: {A}/{B}" y "Repetir round" → `router.patch(...round, {id_inscripcion_ganador|repetido})`.
- **Resultados especiales** (menú): "Default {A}/{B}" → `...default`; "Descalificar {A}/{B}" → `...descalificar`.
- **Amonestar**: dialog con select de robot + textarea motivo → `router.post(...amonestacion)`. Lista de amonestaciones del encuentro debajo.
- **Reparación**: por participante, toggle "Reparación usada" → `router.patch(...reparacion)` (deshabilitado si ya usada).
- `onError` → `toast`. Tipos nuevos en `models.ts` (`RoundData`, `Amonestacion`, marcador).

## Seeders
- Ampliar `CategoriaSeeder`: añadir categorías de sumo **Amateur/Pro** del reglamento: Mini Sumo Autónomo Amateur (350 g) / Pro (500 g), Mini Sumo RC Amateur/Pro, Micro Sumo (100 g), Nano Sumo (25 g), todas `tipo_evaluacion=Combate` con su `peso_maximo_g`/`dimensiones_maximas`. (Idempotente: usar `firstOrCreate` por nombre para no duplicar al re-sembrar.)

## Estrategia de pruebas (feature, PostgreSQL)

- **Best-of-3**: registrar 2 rounds al mismo robot → `es_ganador`, `tipo_resultado='Rounds'`, avanza al siguiente; con 1-1, un 3er round define.
- **Round repetido**: `repetido=true` no incrementa el marcador (encuentro sin ganador).
- **Default**: marca ganador + `tipo_resultado='Default'` + avance; bloqueado si ya hay ganador.
- **Descalificación**: el rival gana + `tipo_resultado='Descalificacion'` + avance.
- **Amonestación**: se registra (juez, motivo, round); no cambia el resultado ni el marcador.
- **Reparación**: `marcarReparacion` pone true; segunda vez → error; queda en BD.
- **Autorización**: Coach/Piloto 403 en round/default/descalificar/amonestar/reparación; Juez y Admin sí; todos ven `index`.
- **Compat**: el bracket/proyección siguen mostrando el ganador del encuentro vía `es_ganador`.

## Fuera de alcance
Soccer, Drone, combate por puntos (agresión/daño/innovación) de 1/3 lb, fase de grupos con tabla, cronómetro en vivo de los 2 min.

## Criterios de aceptación (DoD)
1. Mejor de 3 con avance automático a 2 rounds; repetición no cuenta (tests).
2. Default y descalificación deciden+avanzan con `tipo_resultado` correcto; bloqueados con ganador ya definido (tests).
3. Bitácora de amonestaciones registrada por juez sin alterar resultado (tests).
4. Reparación por robot una sola vez (test).
5. Autorización Juez+Admin; Coach/Piloto 403; todos ven (tests).
6. UI: panel de rounds + especiales + amonestar + reparación; proyección/bracket intactos.
7. Categorías Amateur/Pro de sumo sembradas (idempotente).
8. `php artisan test` 100%; `npm run build` sin errores; Pint limpio.
