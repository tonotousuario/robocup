# RoboLeague — Fase 2.3: Inspección técnica (checklist) (Diseño)

**Fecha:** 2026-06-08
**Fase padre:** Fase 2 → sub-proyecto **2.3 Inspección técnica**. (2.0, 2.1a, 2.1b, 2.2 ya en `main`.)
**Alcance:** Registro de la inspección técnica (peso/dimensiones + veredicto) de inscripciones pagadas. Competencia (2.4) y reportes (2.5) son fases posteriores.

## Contexto

En `main`: Fase 1 (datos), 2.0 (roles + dashboard), 2.1a (instituciones/usuarios), 2.1b (robots con `RobotPolicy`), 2.2 (inscripciones/caja con `InscripcionPolicy` + `TarifaService`). Patrón establecido: controlador + Form Request + Policy auto-descubierta + `AuthorizesRequests` trait + Wayfinder + página índice React (tabla + modal `useForm` + `toast`), nav por rol (`NavItem.roles`), badge de estado con mapa de clases.

Modelos de Fase 1 relevantes:
- `InspeccionChecklist` (tabla `inspecciones_checklist`, PK `id_inspeccion`): `#[Fillable(['id_inscripcion','id_juez','peso_medido_g','dimensiones_medidas','estado_aprobacion','observaciones'])]`, `fecha_inspeccion` (useCurrent), `estado_aprobacion` CHECK ∈ {Pendiente,Aprobado,Rechazado,Descalificado}; relaciones `inscripcion()`, `juez()`.
- `Inscripcion` (`id_inscripcion`, `id_robot`, `estado_pago`, ...), relaciones `robot()`, `inspecciones()` (hasMany).
- `Robot` (`id_robot`, `nombre`, `id_piloto`, `id_categoria`), rel `categoria()`, `piloto()`.
- `Categoria` (`id_categoria`, `nombre`, `peso_maximo_g`, `dimensiones_maximas`).
- **Trigger T1 (BD)**: `BEFORE INSERT` en `inspecciones_checklist` aborta si la inscripción no tiene `estado_pago='Pagado'`. (Candado final; el controlador da la UX.)

## Decisiones de diseño

- **Autorización**: Juez y Admin inspeccionan; Piloto ve solo sus inspecciones (lectura); Coach sin acceso.
- **Una inspección por inscripción** (editable). Ausencia de registro = "Pendiente". El veredicto es `Aprobado`/`Rechazado`/`Descalificado` (no se crean filas con estado "Pendiente").
- **Límites de categoría como referencia visual** (el Juez decide; sin bloqueo automático de peso).
- `updateOrCreate` por `id_inscripcion` para evitar duplicados y permitir re-inspección.

## Backend

### `InspeccionPolicy` (`app/Policies/InspeccionPolicy.php`)
- `before(User, string): ?bool` → admin true, else null.
- `viewAny(User): bool` → `$user->isJuez() || $user->isPiloto()`.
- `guardar(User): bool` → `$user->isJuez()` (admin via before; Piloto/Coach false).
(Coach: false en todo → 403.)

### `InspeccionController` (trait `AuthorizesRequests`)
- **`index(Request)`**: `authorize('viewAny', InspeccionChecklist::class)`.
  - `$user = $request->user()`.
  - Si Juez/Admin: `Inscripcion::where('estado_pago','Pagado')->with(['robot.categoria','robot.piloto','inspecciones'])->orderBy('id_inscripcion')->get()`.
  - Si Piloto: las mismas pero `->whereHas('robot', fn($q)=>$q->where('id_piloto',$user->id))` (puede incluir no-Pagadas para que vea su estado; filtrar a sus robots). Para Piloto, mostrar sus inscripciones independientemente del pago (read-only).
  - Mapear a filas: `id_inscripcion, robot(nombre), categoria(nombre), piloto(name+apellidos), peso_maximo_g, dimensiones_maximas, estado_pago, inspeccion: {estado_aprobacion, peso_medido_g, dimensiones_medidas, observaciones}|null` (la inspección actual = `inscripciones.inspecciones->first()` o null).
  - `estado` mostrado = inspección?->estado_aprobacion ?? 'Pendiente'.
  - Render `inspecciones/index` con `puedeInspeccionar` = (Juez||Admin) para que el front decida mostrar acciones.
- **`guardar(GuardarInspeccionRequest)`**: `authorize('guardar', InspeccionChecklist::class)`.
  - `$inscripcion = Inscripcion::findOrFail($data['id_inscripcion'])`.
  - Guarda: si `$inscripcion->estado_pago !== 'Pagado'` → `back()->withErrors(['id_inscripcion'=>'La inscripción no está pagada; no puede inspeccionarse.'])`.
  - `InspeccionChecklist::updateOrCreate(['id_inscripcion'=>$data['id_inscripcion']], ['id_juez'=>$request->user()->id, 'peso_medido_g'=>..., 'dimensiones_medidas'=>..., 'estado_aprobacion'=>..., 'observaciones'=>...])`.
  - `back()->with('success','Inspección registrada.')`.

### `GuardarInspeccionRequest`
- `authorize()` → true.
- Reglas: `id_inscripcion` required|integer|exists:inscripciones,id_inscripcion; `peso_medido_g` required|integer|min:0; `dimensiones_medidas` required|string|max:255; `estado_aprobacion` required|in:Aprobado,Rechazado,Descalificado; `observaciones` nullable|string.

### Rutas (`routes/web.php`, grupo `auth, verified`)
```php
Route::get('inspecciones', [InspeccionController::class, 'index'])->name('inspecciones.index');
Route::post('inspecciones', [InspeccionController::class, 'guardar'])->name('inspecciones.guardar');
```

## Frontend

### Tipos (`resources/js/types/models.ts`)
- `InspeccionEstado = 'Pendiente' | 'Aprobado' | 'Rechazado' | 'Descalificado'`.
- `InspeccionRow`: `{ id_inscripcion:number; robot:string|null; categoria:string|null; piloto:string|null; peso_maximo_g:number|null; dimensiones_maximas:string|null; estado_pago:string; estado:InspeccionEstado; inspeccion: { peso_medido_g:number; dimensiones_medidas:string; estado_aprobacion:string; observaciones:string|null } | null }`.

### Página / componentes
- `resources/js/pages/inspecciones/index.tsx`: tabla. Juez/Admin: columnas Robot, Categoría, Piloto, Estado (badge), Acciones ("Inspeccionar"/"Re-inspeccionar"). Piloto: Robot, Categoría, Estado (sin acciones). Usa `puedeInspeccionar` para mostrar acciones. Breadcrumbs.
- `resources/js/components/inspecciones/inspeccionar-dialog.tsx` (`useForm`): campos `peso_medido_g` (Input number), `dimensiones_medidas` (Input), `estado_aprobacion` (Select: Aprobado/Rechazado/Descalificado), `observaciones` (textarea/Input). Muestra referencia "Máx: {peso_maximo_g} g · {dimensiones_maximas}". Prefill desde `row.inspeccion` si existe. Submit a `InspeccionController.guardar.url()` con `id_inscripcion` incluido; `onError` → toast.
- Badge: Pendiente (ámbar), Aprobado (verde), Rechazado (rojo), Descalificado (gris oscuro).
- Nav: "Inspección" (icono `ClipboardCheck`) con `roles: ['Administrador','Juez','Piloto']`.

## Estrategia de pruebas (feature, PostgreSQL)

- Autorización: Coach 403 en index/guardar; Juez/Admin/Piloto ven index (200); Piloto recibe 403 en `guardar`.
- `guardar`: Juez inspecciona una inscripción **Pagada** → crea `inspecciones_checklist` con `id_juez`=él, estado dado; re-inspeccionar (segundo guardar mismo id_inscripcion) **actualiza** la misma fila (assertDatabaseCount = 1).
- No-pagada: `guardar` sobre inscripción Pendiente → error `id_inscripcion`, sin fila creada.
- Scope Piloto: index del Piloto solo incluye inscripciones de sus robots.
- Validación: `estado_aprobacion` inválido (p.ej. 'Pendiente' o 'X') → error.
- Setup: crear inscripción Pagada (`Inscripcion::factory()->pagada()`), juez (`User::factory()->juez()`), etc.

## Fuera de alcance (2.3)
Bitácora histórica multi-registro, adjuntos/fotos, validación automática de peso/dimensiones, competencia/brackets/tiempos.

## Criterios de aceptación (DoD)
1. `php artisan test` 100% (previos + nuevos).
2. Solo Juez/Admin inspeccionan; Piloto lectura de lo suyo; Coach 403 (tests).
3. `updateOrCreate` evita duplicados; re-inspección actualiza (test).
4. No se inspecciona inscripción no Pagada (test del guard).
5. UI con modal de inspección + referencia de límites; nav muestra "Inspección" a Admin/Juez/Piloto.
6. `vendor/bin/pint --dirty` limpio; `npm run build` sin errores TS.
