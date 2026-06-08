# RoboLeague — Fase 2.4b: Brackets / Combate (Diseño)

**Fecha:** 2026-06-08
**Fase padre:** Fase 2 → sub-proyecto **2.4 Competencia** → **2.4b (Brackets/Combate)**. (2.4a Tiempos ya en `main`.)
**Alcance:** Generación de brackets de eliminación simple para categorías de **Combate** desde los robots aprobados, con avance automático de ganadores. Es la pieza más compleja de la Fase 2.

## Contexto

En `main`: Fases 1, 2.0–2.3, 2.4a. Patrón establecido: Service para lógica de negocio (ver `TarifaService`), Policy auto-descubierta por nombre de modelo, controlador con `AuthorizesRequests` + `$this->authorize()`, Wayfinder, página índice React con selector por `?categoria=`, `toast`, nav por rol, `ConfirmDeleteDialog`. Lección: `form.transform()` no encadenable en Inertia React.

Modelos de Fase 1 relevantes:
- `Encuentro` (tabla `encuentros`, PK `id_encuentro`): `#[Fillable(['id_categoria','ronda','id_encuentro_siguiente'])]`; rel `categoria()`, `siguiente()` (belongsTo self por `id_encuentro_siguiente`), `anteriores()` (hasMany self), `participantes()` (hasMany `ParticipanteEncuentro`). FK `id_categoria` cascadeOnDelete, `id_encuentro_siguiente` nullOnDelete (self).
- `ParticipanteEncuentro` (tabla `participantes_encuentro`, PK compuesta `id_encuentro`+`id_inscripcion`, `$incrementing=false`): `#[Fillable(['id_encuentro','id_inscripcion','puntos_obtenidos','es_ganador'])]`; rel `encuentro()`, `inscripcion()`. Ambas FK cascadeOnDelete.
- `Inscripcion` (rel `robot()`, `inspecciones()`), `Robot` (`id_categoria`, rel `categoria()`,`piloto()`), `Categoria` (`tipo_evaluacion`).
- **Trigger T2 (BD)**: `BEFORE INSERT` en `participantes_encuentro` aborta si la inscripción no tiene inspección `Aprobado` (candado final).

## Decisiones de diseño

- **Eliminación simple** (un `id_encuentro_siguiente` por encuentro).
- **Siembra al azar + byes automáticos**: barajar aprobados; `size` = potencia de 2 ≥ N; los primeros `size−N` (de la lista barajada) reciben bye (auto-avance). Mínimo 2 aprobados para generar.
- **Resultado = elegir ganador** (sin puntos); avance automático al encuentro siguiente.
- **Regenerar borra el bracket anterior** de la categoría (cascada) con confirmación.
- Autorización: Admin genera; Juez+Admin registran ganadores; todos ven.
- UI: bracket por **columnas de ronda** (sin conectores gráficos).

## Backend

### `EncuentroPolicy` (`app/Policies/EncuentroPolicy.php`)
- `before(User, string): ?bool` → admin true, else null.
- `viewAny(User): bool` → true.
- `generar(User): bool` → false (solo admin via before).
- `registrarGanador(User): bool` → `$user->isJuez()` (admin via before).

### `BracketService` (`app/Services/BracketService.php`)

**`generar(Categoria $categoria): void`**
1. Si `$categoria->tipo_evaluacion !== 'Combate'` → `throw new \InvalidArgumentException('La categoría no es de combate.')`.
2. Borrar encuentros existentes: `Encuentro::where('id_categoria', $categoria->id_categoria)->delete()` (cascada borra participantes).
3. Obtener aprobados:
   ```php
   $inscripciones = Inscripcion::whereHas('robot', fn($q)=>$q->where('id_categoria',$categoria->id_categoria))
       ->whereHas('inspecciones', fn($q)=>$q->where('estado_aprobacion','Aprobado'))
       ->pluck('id_inscripcion')->shuffle()->values();
   ```
4. `$n = $inscripciones->count();` si `$n < 2` → `throw new \DomainException('Se requieren al menos 2 robots aprobados.')`.
5. `$size = 2 ** (int) ceil(log($n, 2));` (potencia de 2 ≥ n; para n=2 → 2).
6. Construir el árbol desde la Final hacia abajo y devolver la lista de matches de ronda 1, en orden:
   - `$rondas = (int) log($size, 2);` (n.º de rondas).
   - Crear la Final: `Encuentro::create(['id_categoria'=>id,'ronda'=>nombreRonda(1, $rondas),'id_encuentro_siguiente'=>null])`.
   - Mantener `$nivelActual = collect([$final])`. Para `$nivel` de 2 hasta `$rondas`: por cada padre en `$nivelActual`, crear 2 encuentros con `id_encuentro_siguiente = padre->id_encuentro` y `ronda = nombreRonda($nivel, $rondas)`; acumular en `$siguienteNivel`. Al terminar, `$nivelActual = $siguienteNivel`.
   - Tras el último nivel, `$nivelActual` son los `size/2` matches de ronda 1, en orden de creación.
   - `nombreRonda($nivel, $rondas)`: nivel 1=Final; usar el mapa por "rondas desde el fondo": número de matches en ese nivel = `2**($nivel-1)`. Nombre por matches: 1→Final, 2→Semifinal, 4→Cuartos, 8→Octavos, 16→Dieciseisavos.
7. Colocar competidores en los `size/2` matches de ronda 1:
   - `$byes = $size - $n;` (primeros `$byes` matches reciben 1 competidor; el resto, 2).
   - Recorrer matches de ronda 1 en orden, consumiendo `$inscripciones`: para `$i < $byes` → 1 participante (bye); para el resto → 2 participantes.
   - Insertar `ParticipanteEncuentro::create(['id_encuentro'=>m,'id_inscripcion'=>ins])` por cada uno.
8. Auto-avance de byes: para cada match de ronda 1 con exactamente 1 participante → `marcarGanador($match, esaInscripcion)`.
- Todo dentro de `DB::transaction(...)`.

**`registrarGanador(Encuentro $encuentro, int $idInscripcion): void`**
- Marcar el participante: `ParticipanteEncuentro::where('id_encuentro',$encuentro->id_encuentro)->where('id_inscripcion',$idInscripcion)->update(['es_ganador'=>true])`.
- Si `$encuentro->id_encuentro_siguiente` no es null: `ParticipanteEncuentro::firstOrCreate(['id_encuentro'=>$encuentro->id_encuentro_siguiente,'id_inscripcion'=>$idInscripcion])` (avanza; firstOrCreate evita duplicar si ya estaba).

### `EncuentroController` (trait `AuthorizesRequests`)
- **`index(Request)`** (`authorize viewAny`): `categorias` = Combate; `categoriaSeleccionada` por `?categoria=` (default primera Combate). Para la categoría: encuentros con `participantes.inscripcion.robot`, ordenados por... (se agrupan por ronda en el front). Mapear cada encuentro a `{ id_encuentro, ronda, id_encuentro_siguiente, participantes: [{id_inscripcion, robot, es_ganador}] }`. Props: `categorias`, `categoriaSeleccionada`, `encuentros`, `puedeGenerar` (isAdministrador), `puedeRegistrar` (isJuez||isAdministrador), `aprobadosCount` (n.º aprobados de la categoría).
- **`generar(GenerarBracketRequest)`** (`authorize generar`): valida `id_categoria` Combate; `try { BracketService::generar($cat) } catch (DomainException $e) { return back()->withErrors(['id_categoria'=>$e->getMessage()]) }`. Éxito → `back()->with('success', ...)`.
- **`registrarGanador(RegistrarGanadorRequest, Encuentro $encuentro)`** (`authorize registrarGanador`): valida que `id_inscripcion` sea participante del encuentro y que el encuentro tenga 2 participantes y sin ganador; si ya tiene ganador → error; llama al servicio.

### Form Requests
- `GenerarBracketRequest`: `id_categoria` required|integer|exists:categorias,id_categoria.
- `RegistrarGanadorRequest`: `id_inscripcion` required|integer; (la pertenencia al encuentro se valida en el controlador con mensaje claro).

### Rutas (`routes/web.php`, grupo `auth, verified`)
```php
Route::get('combate', [EncuentroController::class, 'index'])->name('combate.index');
Route::post('combate/generar', [EncuentroController::class, 'generar'])->name('combate.generar');
Route::patch('encuentros/{encuentro}/ganador', [EncuentroController::class, 'registrarGanador'])->name('encuentros.ganador');
```

## Frontend

### Tipos (`resources/js/types/models.ts`)
- `ParticipanteBracket`: `{ id_inscripcion:number; robot:string|null; es_ganador:boolean }`.
- `EncuentroBracket`: `{ id_encuentro:number; ronda:string; id_encuentro_siguiente:number|null; participantes: ParticipanteBracket[] }`.
- `CategoriaCombateOpcion`: `{ id_categoria:number; nombre:string }`.

### Página / componentes
- `resources/js/pages/combate/index.tsx`: selector de categoría (navega `?categoria=`); si `puedeGenerar`, botón "Generar bracket" (si ya hay encuentros, envolver en confirmación tipo `ConfirmDeleteDialog` con texto "se borrará el bracket actual"); el botón se deshabilita si `aprobadosCount < 2` (con aviso). Render del bracket: agrupar `encuentros` por `ronda` en columnas ordenadas (Dieciseisavos→Octavos→Cuartos→Semifinal→Final); cada encuentro = tarjeta con sus participantes (ganador en negrita/check). Empty state si no hay encuentros. Breadcrumbs.
- `resources/js/components/combate/registrar-ganador-control.tsx`: para Juez/Admin, en encuentros con 2 participantes y sin ganador, botones (uno por participante) "Gana {robot}" → `router.patch(EncuentroController.registrarGanador.url(id_encuentro), {id_inscripcion})`; `onError`→toast.
- Orden de columnas: derivar de un orden fijo `['Dieciseisavos','Octavos','Cuartos','Semifinal','Final']` filtrado a las rondas presentes.
- Nav: "Combate" (icono `Swords`) con `roles: ['Administrador','Juez','Coach','Piloto']`.

## Estrategia de pruebas (feature, PostgreSQL)

Helper: crear N inscripciones aprobadas en una categoría Combate.
- **Generación 4**: tras `generar`, hay 3 encuentros (2 ronda 1 + Final); los 2 de ronda 1 tienen `id_encuentro_siguiente` = id de la Final; 4 participantes en ronda 1; rondas = {Semifinal×2, Final×1}.
- **Generación 8**: 7 encuentros; rondas Cuartos(4)/Semifinal(2)/Final(1).
- **Byes (5 aprobados)**: `size=8`, `byes=3`; 3 participantes ya avanzados a Semifinal (auto-avance), y 1 encuentro de ronda 1 con 2 participantes pendiente. (Assert: 3 participantes en encuentros de ronda Cuartos... ojo: con 5, rondas=3 (Cuartos/Semis/Final); los 3 byes saltan de Cuartos a Semifinal.) Verificar que hay 3 `es_ganador=true` por byes y 3 participantes en Semifinal.
- **Mínimo**: 1 aprobado → `generar` responde error `id_categoria`, sin encuentros.
- **Regenerar**: generar dos veces → la 2ª borra los encuentros de la 1ª (assert count de encuentros = el del 2º intento, no acumulado).
- **registrarGanador**: en un encuentro de ronda 1 con 2 participantes, marcar ganador → ese participante `es_ganador=true` y aparece como participante del `id_encuentro_siguiente`. Ganador de la Final → `es_ganador=true`, sin nuevo encuentro.
- **Autorización**: Juez recibe 403 en `generar`; Admin puede `generar`; Juez y Admin pueden `registrarGanador`; Coach/Piloto 403 en ambas acciones; los 4 roles ven `index` (200).
- **Categoría no-Combate**: `generar` sobre categoría de Tiempo → error.

## Fuera de alcance (2.4b)
Doble eliminación, repechaje, puntos por round, conectores gráficos SVG, reportes (2.5), telemetría ESP32, edición manual del emparejamiento.

## Criterios de aceptación (DoD)
1. `php artisan test` 100% (previos + nuevos).
2. Generación correcta de la estructura (encuentros, cableado `id_encuentro_siguiente`, rondas) para potencias de 2 y con byes (tests).
3. Avance automático: bye y ganador registrado insertan al competidor en el siguiente (tests).
4. Mínimo 2 aprobados; regenerar borra el anterior (tests).
5. Autorización: Admin genera, Juez+Admin registran, todos ven (tests).
6. UI: selector de categoría + bracket por columnas + control de ganador (Juez/Admin) + generar/confirmar (Admin); nav "Combate".
7. `vendor/bin/pint --dirty` limpio; `npm run build` sin errores TS.
