# RoboLeague — Vista "Marcador" de proyección enriquecida · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) o superpowers:executing-plans para ejecutar tarea por tarea. Los pasos usan checkbox (`- [ ]`).

**Goal:** Mostrar en la vista "Marcador" de la proyección el marcador de rounds y las amonestaciones del encuentro en vivo.

**Architecture:** `ProyeccionController::enVivo` amplía su retorno con `marcador` (rounds ganados por robot del encuentro vigente, repetidos no cuentan) y `amonestaciones` (robot+motivo), recargando el encuentro vigente con sus relaciones `rounds`/`amonestaciones`. La vista Marcador del front los renderiza bajo el "vs". Reusa el polling de 5 s.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL, Inertia v3, React 19, Tailwind v4, PHPUnit 12, Pint.

**Convenciones/contexto (verificado):**
- **Dependencia**: la rama del cronómetro de reparación debe estar fusionada a `main` ANTES de esta. Baseline esperado: 161 pruebas.
- `ProyeccionController::show` mapea `$encuentros` (cada uno `{id_encuentro,ronda,id_encuentro_siguiente,participantes:[{id_inscripcion,robot,es_ganador}]}`) y llama `$this->enVivo($encuentros)`. Hay constante `REPARACION_SEGUNDOS`.
- `enVivo(Collection $encuentros): ?array` actual filtra vigentes (2 participantes, sin ganador), ordena por ronda (`Final=1..Dieciseisavos=5`), toma el primero, devuelve `{id_encuentro, ronda, robots}` o null.
- Modelos: `Encuentro` rel `rounds()` (RoundEncuentro: `id_inscripcion_ganador` nullable; repetido→ganador null), `amonestaciones()` (Amonestacion: `id_inscripcion`, `motivo`, rel `inscripcion()`), `participantes.inscripcion.robot`. `ParticipanteEncuentro` rel `inscripcion`.
- Front: `proyeccion/combate.tsx`, bloque `vista === 'marcador' && enVivo` (líneas ~92-99) muestra ronda + "robots[0] vs robots[1]". Tipo `ProyeccionEnVivo` en `@/types`.
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Tests `php artisan test --compact --filter=...`. Frontend gate `npm run build`. RefreshDatabase. Factories: `Categoria::factory()->combate()`, `Robot::factory()`, `Inscripcion::factory()->pagada()`, `InspeccionChecklist::factory()->aprobado()`, `Encuentro::factory()`, `ParticipanteEncuentro`, `RoundEncuentro`, `Amonestacion`, `User::factory()->juez()`.

---

## File Structure

**Backend:**
- Modify: `app/Http/Controllers/ProyeccionController.php` (`enVivo` amplía marcador+amonestaciones)
- Test: `tests/Feature/ProyeccionTest.php` (añadir tests)

**Frontend:**
- Modify: `resources/js/types/models.ts` (`ProyeccionEnVivo` con marcador+amonestaciones)
- Modify: `resources/js/pages/proyeccion/combate.tsx` (render bajo el "vs")

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Confirmar que el cronómetro está en main y crear la rama**

Run:
```bash
git checkout main && git pull --ff-only 2>/dev/null; git log --oneline -1
git checkout -b feature/roboleague-proyeccion-marcador
```
Expected: `main` incluye el commit del cronómetro (`feat(reparacion): cuenta regresiva...`). Si NO está, detente y avisa: esta mejora depende del cronómetro fusionado.

---

## Task 1: Backend — `enVivo` amplía marcador y amonestaciones

**Files:**
- Modify: `app/Http/Controllers/ProyeccionController.php`
- Test: `tests/Feature/ProyeccionTest.php`

- [ ] **Step 1: Añadir los tests que fallan**

Añadir a `tests/Feature/ProyeccionTest.php` (con `use App\Models\RoundEncuentro;` y `use App\Models\Amonestacion;` y `use App\Models\User;` si faltan). Reusar el helper existente `categoriaCombateConBracket(int $n)` que ya genera el bracket; los tests obtienen una semifinal vigente:
```php
public function test_envivo_incluye_marcador_de_rounds(): void
{
    $categoria = $this->categoriaCombateConBracket(4);
    $semi = \App\Models\Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
    $participantes = $semi->participantes()->pluck('id_inscripcion')->all();
    [$a, $b] = $participantes;

    // A gana un round; un round repetido no cuenta.
    \App\Models\RoundEncuentro::create(['id_encuentro' => $semi->id_encuentro, 'numero_round' => 1, 'id_inscripcion_ganador' => $a, 'repetido' => false]);
    \App\Models\RoundEncuentro::create(['id_encuentro' => $semi->id_encuentro, 'numero_round' => 2, 'id_inscripcion_ganador' => null, 'repetido' => true]);

    $this->get('/proyeccion/combate/'.$categoria->id_categoria)
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('enVivo.marcador', 2)
            ->where('enVivo.marcador.0.rounds', fn ($r) => in_array($r, [0, 1], true))
        );

    // El total de rounds ganados (no repetidos) entre ambos = 1.
    $marcador = collect($this->get('/proyeccion/combate/'.$categoria->id_categoria)->viewData('page')['props']['enVivo']['marcador'] ?? []);
    // (verificación robusta abajo con assertInertia)
    $this->assertTrue(true);
}

public function test_envivo_incluye_amonestaciones(): void
{
    $categoria = $this->categoriaCombateConBracket(4);
    $semi = \App\Models\Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
    $idB = $semi->participantes()->pluck('id_inscripcion')->last();
    $juez = \App\Models\User::factory()->juez()->create();

    \App\Models\Amonestacion::create([
        'id_encuentro' => $semi->id_encuentro,
        'id_inscripcion' => $idB,
        'id_juez' => $juez->id,
        'numero_round' => 1,
        'motivo' => 'Tocó el robot en el dohyo',
    ]);

    $this->get('/proyeccion/combate/'.$categoria->id_categoria)
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('enVivo.amonestaciones', 1)
            ->where('enVivo.amonestaciones.0.motivo', 'Tocó el robot en el dohyo')
        );
}

public function test_envivo_null_sin_encuentro_vigente(): void
{
    // Categoría de combate sin bracket → no hay encuentros → enVivo null.
    $categoria = Categoria::factory()->combate()->create();

    $this->get('/proyeccion/combate/'.$categoria->id_categoria)
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('enVivo', null));
}
```
(Nota: simplificar `test_envivo_incluye_marcador_de_rounds` para verificar con assertInertia que la suma de rounds del marcador es 1. Reemplazar el cuerpo por el del Step siguiente si se prefiere una aserción única — ver Step 1b.)

- [ ] **Step 1b: Simplificar el test del marcador (aserción única y robusta)**

Reemplazar el cuerpo de `test_envivo_incluye_marcador_de_rounds` por:
```php
public function test_envivo_incluye_marcador_de_rounds(): void
{
    $categoria = $this->categoriaCombateConBracket(4);
    $semi = \App\Models\Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->first();
    [$a, $b] = $semi->participantes()->pluck('id_inscripcion')->all();

    \App\Models\RoundEncuentro::create(['id_encuentro' => $semi->id_encuentro, 'numero_round' => 1, 'id_inscripcion_ganador' => $a, 'repetido' => false]);
    \App\Models\RoundEncuentro::create(['id_encuentro' => $semi->id_encuentro, 'numero_round' => 2, 'id_inscripcion_ganador' => null, 'repetido' => true]);

    $this->get('/proyeccion/combate/'.$categoria->id_categoria)
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('enVivo.marcador', 2)
            ->where('enVivo.marcador', function ($marcador) use ($a) {
                $col = collect($marcador);
                // suma total de rounds = 1 (el repetido no cuenta)
                if ($col->sum('rounds') !== 1) {
                    return false;
                }
                // el robot A tiene 1, el otro 0 — el marcador trae robot+rounds (no id);
                // basta validar que exista exactamente una entrada con rounds=1 y una con rounds=0.
                return $col->where('rounds', 1)->count() === 1 && $col->where('rounds', 0)->count() === 1;
            })
        );
}
```
(Eliminar el bloque `viewData(...)`/`assertTrue(true)` del Step 1.)

- [ ] **Step 2: Ejecutar (debe fallar)**

Run: `php artisan test --compact --filter=ProyeccionTest`
Expected: FAIL (enVivo aún no trae `marcador`/`amonestaciones`).

- [ ] **Step 3: Ampliar `enVivo`**

En `app/Http/Controllers/ProyeccionController.php`, reemplazar el cuerpo de `enVivo` por (añadir `use App\Models\Encuentro;` ya está; añadir `use App\Models\Amonestacion;` y `use App\Models\RoundEncuentro;` si se usan directamente — aquí se usan vía relaciones del modelo recargado, así que basta `Encuentro`):
```php
private function enVivo(Collection $encuentros): ?array
{
    $orden = ['Final' => 1, 'Semifinal' => 2, 'Cuartos' => 3, 'Octavos' => 4, 'Dieciseisavos' => 5];

    $vigentes = $encuentros
        ->filter(fn (array $e) => count($e['participantes']) === 2
            && collect($e['participantes'])->every(fn (array $p) => ! $p['es_ganador']))
        ->sortBy(fn (array $e) => $orden[$e['ronda']] ?? 99);

    $e = $vigentes->first();
    if ($e === null) {
        return null;
    }

    $encuentro = Encuentro::with(['participantes.inscripcion.robot', 'rounds', 'amonestaciones.inscripcion.robot'])
        ->find($e['id_encuentro']);

    $marcador = $encuentro->participantes->map(fn ($p) => [
        'robot' => $p->inscripcion?->robot?->nombre,
        'rounds' => $encuentro->rounds
            ->where('id_inscripcion_ganador', $p->id_inscripcion)
            ->count(),
    ])->values();

    $amonestaciones = $encuentro->amonestaciones->map(fn ($a) => [
        'robot' => $a->inscripcion?->robot?->nombre,
        'motivo' => $a->motivo,
    ])->values();

    return [
        'id_encuentro' => $e['id_encuentro'],
        'ronda' => $e['ronda'],
        'robots' => collect($e['participantes'])->pluck('robot')->values(),
        'marcador' => $marcador,
        'amonestaciones' => $amonestaciones,
    ];
}
```
(`rounds->where('id_inscripcion_ganador', $id)->count()` cuenta solo rounds con ganador; los repetidos tienen `id_inscripcion_ganador` null → no se cuentan.)

- [ ] **Step 4: Ejecutar (debe pasar)**

Run: `php artisan test --compact --filter=ProyeccionTest`
Expected: PASS (los previos de proyección + 3 nuevos).

- [ ] **Step 5: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ProyeccionController.php tests/Feature/ProyeccionTest.php
git commit -m "feat(proyeccion): enVivo expone marcador de rounds y amonestaciones"
```

---

## Task 2: Frontend — tipos + render en la vista Marcador

**Files:**
- Modify: `resources/js/types/models.ts`
- Modify: `resources/js/pages/proyeccion/combate.tsx`

- [ ] **Step 1: Tipos**

En `resources/js/types/models.ts`, en `ProyeccionEnVivo`, añadir los dos campos:
```ts
    marcador: { robot: string | null; rounds: number }[];
    amonestaciones: { robot: string | null; motivo: string }[];
```
(Mantener los campos existentes `id_encuentro`, `ronda`, `robots`.)

- [ ] **Step 2: Render bajo el "vs"**

En `resources/js/pages/proyeccion/combate.tsx`, dentro del bloque `{vista === 'marcador' && enVivo && ( ... )}`, justo después del párrafo del "robots[0] vs robots[1]", añadir el marcador y las amonestaciones:
```tsx
                    {enVivo.marcador.length === 2 && (
                        <p className="mt-4 text-center font-display text-4xl">
                            {enVivo.marcador[0].robot ?? '—'}{' '}
                            <span className="text-primary">{enVivo.marcador[0].rounds}</span>
                            {' – '}
                            <span className="text-primary">{enVivo.marcador[1].rounds}</span>{' '}
                            {enVivo.marcador[1].robot ?? '—'}
                        </p>
                    )}
                    {enVivo.amonestaciones.length > 0 && (
                        <ul className="mt-4 flex flex-col items-center gap-1 text-xl text-amber-300">
                            {enVivo.amonestaciones.map((a, i) => (
                                <li key={`${i}-${a.robot}`}>⚠ {a.robot ?? '—'}: {a.motivo}</li>
                            ))}
                        </ul>
                    )}
```
(El bloque va dentro del contenedor del panel en vivo, no cambia la condición externa; se refresca con el polling de 5 s ya existente.)

- [ ] **Step 3: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/types/models.ts resources/js/pages/proyeccion/combate.tsx
git commit -m "feat(proyeccion): mostrar marcador de rounds y amonestaciones en vista Marcador"
```

---

## Task 3: Verificación integral

**Files:** ninguno.

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todas PASS (161 baseline + 3 = 164).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: Verificación visual manual**

Con `composer run dev`: genera un bracket (Admin en `/combate`), registra 1 round y 1 amonestación en una semifinal (panel del juez), luego abre `/proyeccion/combate/{id}` → vista **Marcador** → confirma que bajo "RobotA vs RobotB" aparece el marcador "RobotA 1 – 0 RobotB" y la lista "⚠ RobotB: {motivo}", y que se actualiza solo en ~5 s.

- [ ] **Step 5: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(proyeccion): verificacion integral marcador enriquecido" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- `enVivo` expone `marcador` (rounds por robot, repetidos no cuentan) + `amonestaciones` (robot+motivo) del vigente → Task 1 ✓
- Vista Marcador muestra marcador de rounds + lista de amonestaciones bajo el "vs"; refresca con polling → Task 2 ✓
- Sin encuentro vigente → `enVivo` null sin romper → Task 1 (test) ✓
- Tipos `ProyeccionEnVivo` ampliados → Task 2 ✓
- DoD: suite 100%, build, pint, visual → Task 3 ✓

**Notas/riesgos:**
- (Dependencia) Esta rama parte de `main` con el cronómetro ya fusionado (Task 0 lo verifica). Baseline 161 → 164.
- (`enVivo` recarga) hace una consulta extra (`Encuentro::with(...)->find`) solo para el encuentro vigente — costo O(1), aceptable.
- (Recuento de rounds) `rounds->where('id_inscripcion_ganador', $id)->count()` ignora repetidos (ganador null) por construcción.
- (assertInertia marcador) el test valida la suma de rounds y la distribución 1/0 sin depender del orden de los participantes.
