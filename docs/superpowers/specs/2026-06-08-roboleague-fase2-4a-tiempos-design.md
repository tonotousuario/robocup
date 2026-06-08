# RoboLeague — Fase 2.4a: Captura de Tiempos y Posiciones (Diseño)

**Fecha:** 2026-06-08
**Fase padre:** Fase 2 → sub-proyecto **2.4 Competencia**, dividido en **2.4a (Tiempos)** y 2.4b (Brackets/Combate, posterior).
**Alcance:** Captura de hasta 3 intentos cronometrados por inscripción aprobada de categorías de **Tiempo**, y tabla de posiciones (ranking) por mejor tiempo. Brackets de combate son 2.4b.

## Contexto

En `main`: Fases 1, 2.0, 2.1a/b, 2.2, 2.3. Patrón establecido: controlador + Form Request + Policy + `AuthorizesRequests` trait + Wayfinder + página índice React (tabla + modal `useForm` + `toast`), nav por rol, badge de estado. Aprendizaje reciente: la auto-discovery de policies usa `<Modelo>Policy` (nombrar la policy igual al modelo evita registro manual); y `form.transform()` no es encadenable en Inertia React.

Modelos de Fase 1 relevantes:
- `IntentoTiempo` (tabla `intentos_tiempos`, PK `id_intento`): `#[Fillable(['id_inscripcion','numero_vuelta','tiempo_logrado','penalizacion_segundos'])]`, rel `inscripcion()`. CHECK `numero_vuelta BETWEEN 1 AND 3`, UNIQUE (`id_inscripcion`,`numero_vuelta`). Trigger T3: bloquea insert si la inscripción no tiene inspección Aprobado.
- `Inscripcion` (rel `robot()`, `intentos()` hasMany, `inspecciones()`), `Robot` (rel `categoria()`), `Categoria` (`tipo_evaluacion` ∈ {Combate,Tiempo}).
- `vista_posiciones` (BD): mejor tiempo por inscripción para categorías Tiempo (se reserva para reportes 2.5; aquí el mejor tiempo se calcula en PHP desde los intentos).

## Decisiones de diseño

- **Autorización**: Juez y Admin capturan; **todos** los autenticados ven el ranking; Coach/Piloto no capturan.
- **Captura**: modal por robot con 3 filas (vueltas 1/2/3), `updateOrCreate` por (`id_inscripcion`,`numero_vuelta`); filas vacías se ignoran.
- **Selector de categoría** vía query param `?categoria=`; una sola tabla sirve de captura y ranking.
- **Mejor tiempo** calculado en PHP (`min(tiempo+penalización)`); `vista_posiciones` queda para 2.5.
- Policy nombrada `IntentoTiempoPolicy` (coincide con el modelo `IntentoTiempo` → auto-discovery sin registro manual).

## Backend

### `IntentoTiempoPolicy` (`app/Policies/IntentoTiempoPolicy.php`)
- `before(User, string): ?bool` → admin true, else null.
- `viewAny(User): bool` → true (cualquier usuario autenticado ve el ranking).
- `capturar(User): bool` → `$user->isJuez()` (admin via before; Coach/Piloto false).

### `TiempoController` (trait `AuthorizesRequests`)
- **`index(Request)`**: `authorize('viewAny', IntentoTiempo::class)`.
  - `categorias` = `Categoria::where('tipo_evaluacion','Tiempo')->orderBy('nombre')->get(['id_categoria','nombre'])`.
  - `categoriaSeleccionada` = `(int) $request->query('categoria')` o la primera categoría Tiempo (o null si no hay).
  - Si hay categoría: inscripciones de robots de esa categoría con inspección Aprobado:
    `Inscripcion::whereHas('robot', fn($q)=>$q->where('id_categoria',$catId))->whereHas('inspecciones', fn($q)=>$q->where('estado_aprobacion','Aprobado'))->with(['robot','intentos'])->get()`.
  - Mapear cada una a: `id_inscripcion, robot(nombre), intentos: [{numero_vuelta, tiempo_logrado, penalizacion_segundos}], mejor_tiempo` (= min sobre intentos de `tiempo_logrado+penalizacion_segundos`, o null si sin intentos).
  - Ordenar: con mejor_tiempo asc primero, los null al final → asignar `posicion` (1..n) solo a los que tienen tiempo.
  - Props: `categorias`, `categoriaSeleccionada`, `competidores` (filas ordenadas con posicion|null), `puedeCapturar` = isJuez||isAdministrador.
  - Render `tiempos/index`.
- **`guardar(GuardarTiemposRequest)`**: `authorize('capturar', IntentoTiempo::class)`.
  - `$inscripcion = Inscripcion::with('robot.categoria')->findOrFail($data['id_inscripcion'])`.
  - Guarda: si `robot.categoria.tipo_evaluacion !== 'Tiempo'` → error `id_inscripcion` 'La categoría no es de tiempo.'.
  - Guarda: si no existe inspección Aprobado → error `id_inscripcion` 'El robot no está aprobado.'.
  - Por cada `intento` en `$data['intentos']` con `tiempo_logrado` presente: `IntentoTiempo::updateOrCreate(['id_inscripcion'=>$id,'numero_vuelta'=>$intento['numero_vuelta']], ['tiempo_logrado'=>..., 'penalizacion_segundos'=>$intento['penalizacion_segundos'] ?? 0])`.
  - `back()->with('success','Tiempos registrados.')`.

### `GuardarTiemposRequest`
- `authorize()` → true.
- Reglas:
  - `id_inscripcion` → required|integer|exists:inscripciones,id_inscripcion.
  - `intentos` → required|array|max:3.
  - `intentos.*.numero_vuelta` → required|integer|in:1,2,3.
  - `intentos.*.tiempo_logrado` → nullable|numeric|gt:0.
  - `intentos.*.penalizacion_segundos` → nullable|numeric|min:0.
  - (Distinción de vueltas la garantiza el UNIQUE de BD; el front envía 1,2,3 fijos.)

### Rutas (`routes/web.php`, grupo `auth, verified`)
```php
Route::get('tiempos', [TiempoController::class, 'index'])->name('tiempos.index');
Route::post('tiempos', [TiempoController::class, 'guardar'])->name('tiempos.guardar');
```

## Frontend

### Tipos (`resources/js/types/models.ts`)
- `IntentoTiempoData`: `{ numero_vuelta:number; tiempo_logrado:string; penalizacion_segundos:string }`.
- `CompetidorTiempo`: `{ id_inscripcion:number; robot:string|null; posicion:number|null; mejor_tiempo:string|null; intentos: IntentoTiempoData[] }`.
- `CategoriaTiempoOpcion`: `{ id_categoria:number; nombre:string }`.

### Página / componentes
- `resources/js/pages/tiempos/index.tsx`: Select de categoría (navega con `router.get(tiempos.index().url, {categoria}, {preserveState:true})`); tabla Pos · Robot · V1 · V2 · V3 · Mejor · [Acciones]. Cada Vn muestra el tiempo del intento de esa vuelta (+penalización si >0) o '—'. "Mejor" en negrita. Si `puedeCapturar`, botón "Capturar" por fila. Empty state. Breadcrumbs.
- `resources/js/components/tiempos/capturar-tiempos-dialog.tsx` (`useForm`): 3 filas (vueltas 1,2,3), cada una con input tiempo (number step 0.001) y penalización (number); prefill desde `competidor.intentos` (match por numero_vuelta); al enviar arma `intentos: [{numero_vuelta:1,...},{2,...},{3,...}]` y hace `form.post(tiempos.guardar.url())`. `onError`→toast.
- Nav: "Tiempos" (icono `Timer`) con `roles: ['Administrador','Juez','Coach','Piloto']`.

## Estrategia de pruebas (feature, PostgreSQL)

- Autorización: todos los roles ven `GET /tiempos` (200); Coach y Piloto reciben 403 en `POST /tiempos`.
- Captura: Juez registra 3 intentos de una inscripción Aprobada de categoría Tiempo → 3 filas en `intentos_tiempos`; re-capturar (mismo id + vueltas) **actualiza** (assertDatabaseCount sigue en 3, valores nuevos).
- Guardas: capturar sobre inscripción **no Aprobada** → error `id_inscripcion`, sin filas; capturar sobre categoría **Combate** → error.
- Ranking: con dos competidores de tiempos distintos, `index` los ordena por mejor tiempo (menor primero) y asigna posiciones; un competidor sin intentos queda al final con `posicion=null`.
- Validación: `numero_vuelta=4` rechazado; `tiempo_logrado=0` rechazado.
- Setup: categoría Tiempo (`Categoria::factory()->tiempo()`), inscripción `pagada()` + inspección `aprobado()`, juez.

## Fuera de alcance (2.4a)
Brackets de combate (2.4b), borrado individual de intentos, reportes/exportaciones (2.5), telemetría ESP32 (fase final), uso de `vista_posiciones` (reservada para 2.5).

## Criterios de aceptación (DoD)
1. `php artisan test` 100% (previos + nuevos).
2. Solo Juez/Admin capturan; todos ven ranking; Coach/Piloto 403 en captura (tests).
3. `updateOrCreate` por vuelta evita duplicados; re-captura actualiza (test).
4. Guardas de categoría-Tiempo y Aprobado (tests); trigger T3/CHECK como candado final.
5. Ranking ordenado por mejor tiempo con posiciones; sin tiempos al final (test).
6. UI: selector de categoría + tabla captura/ranking + modal de 3 vueltas; nav "Tiempos" para los 4 roles.
7. `vendor/bin/pint --dirty` limpio; `npm run build` sin errores TS.
