# RoboLeague — Fase 2.2: Inscripciones / Financiero (Diseño)

**Fecha:** 2026-06-07
**Fase padre:** Fase 2 → sub-proyecto **2.2 Inscripciones / Financiero**. (2.0, 2.1a, 2.1b ya en `main`.)
**Alcance:** Inscripción de robots con cálculo automático de tarifa por fecha y gestión del estado de pago (caja). Inspección/competencia/reportes son fases posteriores.

## Contexto

En `main`: Fase 1 (datos), 2.0 (roles `RolUsuario`, middleware `role`, dashboard), 2.1a (CRUD instituciones+usuarios), 2.1b (CRUD robots con `RobotPolicy` de propiedad). Patrón establecido: controlador resourceful + Form Request + Policy (cuando hay propiedad) + Wayfinder + página índice React (tabla + modal `useForm` + `ConfirmDeleteDialog`), errores vía `toast` de sonner, nav por rol (`NavItem.roles`).

Modelos de Fase 1 relevantes:
- `Inscripcion` (tabla `inscripciones`, PK `id_inscripcion`): `#[Fillable(['id_robot','id_tarifa','monto_pagado','estado_pago'])]`, `fecha_registro` (timestamp useCurrent), `estado_pago` CHECK ∈ {Pendiente,Pagado,Cancelado}; relaciones `robot()`, `tarifa()`. **No hay campo de folio.**
- `Robot` (`id_robot`, `nombre`, `id_piloto`, `id_categoria`, `id_institucion`), relaciones `piloto()`, `categoria()`, `inscripciones()`.
- `Tarifa` (`id_tarifa`, `descripcion`, `fecha_inicio_cobro` date, `fecha_fin_cobro` date, `monto` decimal). Hay `TarifaSeeder` (Preventa/Fase Regular/Tardía/Demostración).
- Trigger T1 (BD): inspección requiere inscripción `estado_pago='Pagado'` (relevante para 2.3, no para esta fase).

## Decisiones de diseño

- **Flujo**: Piloto inscribe sus robots (crea Pendiente); Admin gestiona la caja (marca Pagado / Cancela). Admin también puede inscribir cualquier robot.
- **Tarifa automática por fecha** (RF2.1): se calcula con la fecha de inscripción; si no hay tarifa vigente ese día, **se bloquea** la inscripción.
- **Monto al pagar**: al marcar Pagado, `monto_pagado` = `monto` de la tarifa de esa inscripción (automático).
- **Duplicados**: un robot solo puede tener **una inscripción activa** (estado ≠ Cancelado); tras cancelar, se puede re-inscribir.
- Acciones de caja explícitas (`pagar`/`cancelar`) en vez de un update genérico.
- UI lista + modal (patrón 2.1).

## Backend

### `TarifaService` (`app/Services/TarifaService.php`)
- `vigenteParaHoy(): ?Tarifa` → delega en `vigentePara(CarbonInterface $fecha)`.
- `vigentePara(\Carbon\CarbonInterface $fecha): ?Tarifa`: `Tarifa::whereDate('fecha_inicio_cobro','<=',$fecha)->whereDate('fecha_fin_cobro','>=',$fecha)->orderBy('fecha_inicio_cobro')->first()`. Devuelve null si ninguna.
- Unidad aislada y testeable (la fecha es parámetro; `vigenteParaHoy` usa `now()`).

### `InscripcionPolicy` (`app/Policies/InscripcionPolicy.php`)
- `before(User $user, string $ability): ?bool` → admin true, else null.
- `viewAny(User $user): bool` → `$user->isPiloto()`.
- `create(User $user): bool` → `$user->isPiloto()`.
- `pagar(User $user, Inscripcion $i): bool` → `false` (solo admin via before).
- `cancelar(User $user, Inscripcion $i): bool` → `false` (solo admin via before).
(Juez/Coach: false en todo → 403.)

### `InscripcionController`
- Usa el trait `AuthorizesRequests` con `$this->authorize(...)` explícito por método (el `Controller` base es plano; mismo patrón que `RobotController`).
- **`index(Request)`**: `authorize('viewAny', Inscripcion::class)`.
  - Inscripciones: Admin → todas; Piloto → solo de sus robots (`whereHas('robot', fn($q)=>$q->where('id_piloto',$user->id))`). Eager-load `robot.piloto`, `robot.categoria`, `tarifa`. Mapear a filas planas: `id_inscripcion, robot(nombre), categoria(nombre), piloto(name+apellidos), tarifa(descripcion|null), monto_pagado, estado_pago`.
  - `robotsInscribibles`: robots sin inscripción activa (estado ≠ Cancelado). Piloto → solo propios; Admin → todos. Forma `{id_robot, nombre}`.
  - `tarifaVigente`: `TarifaService::vigenteParaHoy()` mapeada a `{descripcion, monto}` o null.
  - Render `inscripciones/index`.
- **`store(StoreInscripcionRequest)`**: `authorize('create', Inscripcion::class)`.
  - `$robot = Robot::findOrFail($request->id_robot)`.
  - Si Piloto y `$robot->id_piloto !== $user->id` → `back()->withErrors(['id_robot'=>'Ese robot no te pertenece.'])`.
  - Duplicado: si `$robot->inscripciones()->where('estado_pago','!=','Cancelado')->exists()` → error 'Este robot ya tiene una inscripción activa.'.
  - `$tarifa = TarifaService::vigenteParaHoy()`; si null → error 'No hay una tarifa vigente para hoy.'.
  - `Inscripcion::create(['id_robot'=>$robot->id_robot,'id_tarifa'=>$tarifa->id_tarifa,'monto_pagado'=>0,'estado_pago'=>'Pendiente'])`.
- **`pagar(Inscripcion $inscripcion)`**: `authorize('pagar', $inscripcion)`. Solo si Pendiente; set `estado_pago='Pagado'`, `monto_pagado = $inscripcion->tarifa->monto` (si tarifa null, 0). `back()->with('success',...)`.
- **`cancelar(Inscripcion $inscripcion)`**: `authorize('cancelar', $inscripcion)`. set `estado_pago='Cancelado'`.

### `StoreInscripcionRequest`
- `authorize()` → true. Reglas: `id_robot` → required|integer|exists:robots,id_robot.

### Rutas (`routes/web.php`, grupo `auth, verified`)
```php
Route::get('inscripciones', [InscripcionController::class, 'index'])->name('inscripciones.index');
Route::post('inscripciones', [InscripcionController::class, 'store'])->name('inscripciones.store');
Route::patch('inscripciones/{inscripcion}/pagar', [InscripcionController::class, 'pagar'])->name('inscripciones.pagar');
Route::patch('inscripciones/{inscripcion}/cancelar', [InscripcionController::class, 'cancelar'])->name('inscripciones.cancelar');
```

## Frontend

### Tipos (`resources/js/types/models.ts`)
- `InscripcionRow`: `{ id_inscripcion:number; robot:string; categoria:string|null; piloto:string|null; tarifa:string|null; monto_pagado:string; estado_pago:'Pendiente'|'Pagado'|'Cancelado' }`.
- `RobotInscribible`: `{ id_robot:number; nombre:string }`.
- `TarifaVigente`: `{ descripcion:string; monto:string }`.

### Página / componentes
- `resources/js/pages/inscripciones/index.tsx`: tabla (Robot, Categoría, [Piloto si admin], Tarifa, Monto, Estado con badge, Acciones). Botón "Inscribir robot" (modal). Admin: por fila con estado Pendiente, botones "Marcar pagado" (PATCH pagar) y "Cancelar" (ConfirmDeleteDialog reutilizado para confirmar, o botón directo) → `router.patch(...)`. Breadcrumbs.
- `resources/js/components/inscripciones/inscribir-robot-dialog.tsx`: modal `useForm` con `Select` de `robotsInscribibles`; muestra la `tarifaVigente` (descripción + monto) o un aviso "No hay tarifa vigente" que deshabilita el submit; `onError` → toast.
- Badge de estado: Pendiente (amber), Pagado (verde), Cancelado (gris).

### Navegación
- Ítem "Inscripciones" (icono `Receipt` o `Wallet` de lucide) con `roles: ['Administrador','Piloto']`.

## Estrategia de pruebas (feature + unit, PostgreSQL)

- **Unit `TarifaServiceTest`**: con tarifas sembradas/factory, `vigentePara(fecha)` devuelve la del rango correcto; null cuando la fecha cae fuera de todo rango.
- **`InscripcionTest` (feature):**
  - Autorización: Juez/Coach 403 en index/store; Piloto ve solo sus inscripciones; Piloto recibe 403 al `pagar`/`cancelar`.
  - Store: Piloto inscribe su robot → estado Pendiente, `id_tarifa` = vigente, `monto_pagado` 0; Piloto NO puede inscribir robot ajeno (error); sin tarifa vigente → error y no se crea; robot con inscripción activa → error de duplicado; tras `cancelar`, re-inscribir funciona.
  - `pagar` (Admin): estado → Pagado y `monto_pagado` = monto de la tarifa.
  - `cancelar` (Admin): estado → Cancelado.
- Usar `actingAs` + factories; controlar la fecha con `Carbon::setTestNow()` para las ventanas de tarifa.

## Fuera de alcance (2.2)
Pasarela de pago real, folios/recibos, reportes de caja agregados (2.5), edición libre de monto, descuentos.

## Criterios de aceptación (DoD)
1. `php artisan test` 100% (previos + nuevos).
2. Tarifa vigente se calcula por fecha; sin tarifa → inscripción bloqueada (test).
3. Duplicado bloqueado; re-inscripción tras cancelar permitida (test).
4. `pagar` fija monto = tarifa; `pagar`/`cancelar` solo Admin (test).
5. Piloto solo ve/inscribe sus robots (test).
6. UI con modal de inscripción + acciones de caja; nav muestra "Inscripciones" a Admin y Piloto.
7. `vendor/bin/pint --dirty` limpio; `npm run build` sin errores TS.
