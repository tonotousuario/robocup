# RoboLeague — Podio de la competencia (match 3er lugar + podio en proyección) · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) o superpowers:executing-plans para ejecutar tarea por tarea. Los pasos usan checkbox (`- [ ]`).

**Goal:** Añadir un encuentro por el 3er lugar al bracket y mostrar un podio 🥇🥈🥉 en la proyección cuando la final está decidida.

**Architecture:** `BracketService::generar` crea un encuentro `ronda='Tercer lugar'` cuando hay semifinales; `registrarGanador` enruta el perdedor de cada semifinal a ese encuentro. `ProyeccionController::show` expone `podio` (1º/2º de la Final + 3º del match de tercer lugar) o null. La proyección muestra un componente de podio a pantalla completa cuando `podio` no es null. Sin cambios de esquema (reusa `encuentros`/`es_ganador`).

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL, Inertia v3, React 19, Tailwind v4, PHPUnit 12, Pint.

**Convenciones/contexto (verificado en main):**
- Baseline: 169 pruebas.
- `BracketService::generar` construye el árbol desde la Final (`ronda='Final'`, siguiente null); coloca participantes en ronda1; auto-avanza byes llamando `registrarGanador`. `$size = 2**ceil(log(n,2))`; hay semifinales cuando `$size >= 4`.
- `registrarGanador($encuentro,$idInscripcion)`: marca `es_ganador=true` y, si `id_encuentro_siguiente` no null, añade el ganador al siguiente (`firstOrCreate`). Lo usan `decidirEncuentro` (rounds/default/descalificación) y el auto-avance de byes.
- `ProyeccionController::show(Categoria)` renderiza `proyeccion/combate` con categoria/encuentros/enVivo/posiciones/reparacionesActivas. `enVivo` ordena rondas con un mapa (Final=1..Dieciseisavos=5; otras → 99).
- `proyeccion/combate.tsx`: tras la barra de control y la franja de reparación, renderiza el bloque `vista==='marcador' && enVivo`, luego el `vista==='rotar'? standings : <ProjectionBracket>`. Polling `router.reload({ only: ['encuentros','enVivo','posiciones','reparacionesActivas'] })`.
- `ProjectionBracket` filtra por `ORDEN_RONDAS = ['Dieciseisavos','Octavos','Cuartos','Semifinal','Final']` → el 'Tercer lugar' NO aparece en el árbol (intencional; el podio lo cubre).
- Modelos: `Encuentro` (rel `participantes`), `ParticipanteEncuentro` (`es_ganador`, rel `inscripcion.robot`).
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Tests `php artisan test --compact --filter=...`. Frontend gate `npm run build`. RefreshDatabase. Factories: `Categoria::factory()->combate()`, `Robot::factory()`, `Inscripcion::factory()->pagada()`, `InspeccionChecklist::factory()->aprobado()`. `BracketService` se instancia con `new BracketService`.

---

## File Structure

**Backend:**
- Modify: `app/Services/BracketService.php` (crear match 3er lugar + enrutar perdedores)
- Modify: `app/Http/Controllers/ProyeccionController.php` (prop `podio`)
- Test: `tests/Feature/PodioTest.php`

**Frontend:**
- Create: `resources/js/components/proyeccion/projection-podium.tsx`
- Modify: `resources/js/types/models.ts` (`Podio`)
- Modify: `resources/js/pages/proyeccion/combate.tsx` (render + polling)

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-podio
```
Expected: `Switched to a new branch 'feature/roboleague-podio'`. (Si ya existe, continuar en ella.)

---

## Task 1: BracketService — match de 3er lugar + enrutar perdedores

**Files:**
- Modify: `app/Services/BracketService.php`
- Test: `tests/Feature/PodioTest.php`

- [ ] **Step 1: Escribir los tests que fallan**

`tests/Feature/PodioTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\Robot;
use App\Services\BracketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PodioTest extends TestCase
{
    use RefreshDatabase;

    private function categoriaConAprobados(int $n): Categoria
    {
        $categoria = Categoria::factory()->combate()->create();
        for ($i = 0; $i < $n; $i++) {
            $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
            $ins = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
            InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $ins->id_inscripcion]);
        }

        return $categoria;
    }

    public function test_generar_crea_match_de_tercer_lugar_con_semifinales(): void
    {
        $categoria = $this->categoriaConAprobados(4);
        (new BracketService)->generar($categoria);

        $this->assertDatabaseHas('encuentros', [
            'id_categoria' => $categoria->id_categoria,
            'ronda' => 'Tercer lugar',
        ]);
    }

    public function test_generar_no_crea_tercer_lugar_con_dos_robots(): void
    {
        $categoria = $this->categoriaConAprobados(2);
        (new BracketService)->generar($categoria);

        $this->assertDatabaseMissing('encuentros', [
            'id_categoria' => $categoria->id_categoria,
            'ronda' => 'Tercer lugar',
        ]);
    }

    public function test_perdedores_de_semifinal_van_al_tercer_lugar(): void
    {
        $categoria = $this->categoriaConAprobados(4);
        $service = new BracketService;
        $service->generar($categoria);

        $semis = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->get();
        $this->assertCount(2, $semis);

        // Decidir cada semifinal por su primer participante.
        $perdedores = [];
        foreach ($semis as $semi) {
            $ids = $semi->participantes()->pluck('id_inscripcion')->all();
            $ganador = $ids[0];
            $perdedores[] = $ids[1];
            $service->registrarGanador($semi, $ganador);
        }

        $tercerLugar = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Tercer lugar')->first();
        $idsTercer = $tercerLugar->participantes()->pluck('id_inscripcion')->sort()->values()->all();
        sort($perdedores);
        $this->assertSame($perdedores, $idsTercer);
    }
}
```

- [ ] **Step 2: Ejecutar (debe fallar)**

Run: `php artisan test --compact --filter=PodioTest`
Expected: FAIL (no se crea 'Tercer lugar' ni se enrutan perdedores).

- [ ] **Step 3: Crear el match de 3er lugar en `generar`**

En `app/Services/BracketService.php`, dentro de `generar`, JUSTO DESPUÉS del bucle `foreach ($ronda1 as $index => $match)` que coloca participantes y ANTES del bloque `// Auto-avance de byes.`, añadir:
```php
            // Encuentro por el 3er lugar (solo si hay semifinales).
            if ($size >= 4) {
                Encuentro::create([
                    'id_categoria' => $categoria->id_categoria,
                    'ronda' => 'Tercer lugar',
                    'id_encuentro_siguiente' => null,
                ]);
            }
```
(Debe crearse antes del auto-avance de byes para que, si un bye cae en semifinal, el enrutado encuentre el encuentro; con bye no hay perdedor, así que no añade nada — pero el orden es correcto.)

- [ ] **Step 4: Enrutar el perdedor en `registrarGanador`**

En `app/Services/BracketService.php`, al final de `registrarGanador` (después del bloque `if ($encuentro->id_encuentro_siguiente !== null) {...}`), añadir:
```php
        if ($encuentro->ronda === 'Semifinal') {
            $tercerLugar = Encuentro::where('id_categoria', $encuentro->id_categoria)
                ->where('ronda', 'Tercer lugar')
                ->first();

            if ($tercerLugar !== null) {
                $idPerdedor = ParticipanteEncuentro::where('id_encuentro', $encuentro->id_encuentro)
                    ->where('id_inscripcion', '!=', $idInscripcion)
                    ->value('id_inscripcion');

                if ($idPerdedor !== null) {
                    ParticipanteEncuentro::firstOrCreate([
                        'id_encuentro' => $tercerLugar->id_encuentro,
                        'id_inscripcion' => $idPerdedor,
                    ]);
                }
            }
        }
```
(`Encuentro` y `ParticipanteEncuentro` ya están importados en el servicio.)

- [ ] **Step 5: Ejecutar (debe pasar)**

Run: `php artisan test --compact --filter=PodioTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Verificar que el combate sigue verde**

Run: `php artisan test --compact --filter='BracketService|CombateRounds|Proyeccion'`
Expected: PASS (el enrutado solo añade lógica en semifinales; no rompe el avance del ganador).

- [ ] **Step 7: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/BracketService.php tests/Feature/PodioTest.php
git commit -m "feat(podio): match por el 3er lugar y enrutado de perdedores de semifinal"
```

---

## Task 2: ProyeccionController — prop `podio`

**Files:**
- Modify: `app/Http/Controllers/ProyeccionController.php`
- Test: `tests/Feature/PodioTest.php`

- [ ] **Step 1: Añadir tests que fallan**

Añadir a `tests/Feature/PodioTest.php` (con `use Inertia\Testing\AssertableInertia as Assert;`):
```php
public function test_show_podio_null_si_final_sin_decidir(): void
{
    $categoria = $this->categoriaConAprobados(4);
    (new BracketService)->generar($categoria);

    $this->get('/proyeccion/combate/'.$categoria->id_categoria)
        ->assertInertia(fn (Assert $page) => $page->where('podio', null));
}

public function test_show_podio_con_final_decidida(): void
{
    $categoria = $this->categoriaConAprobados(2); // solo final, sin tercer lugar
    $service = new BracketService;
    $service->generar($categoria);

    $final = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Final')->first();
    $ids = $final->participantes()->pluck('id_inscripcion')->all();
    $service->registrarGanador($final, $ids[0]);

    $this->get('/proyeccion/combate/'.$categoria->id_categoria)
        ->assertInertia(fn (Assert $page) => $page
            ->has('podio')
            ->where('podio.tercero', null)
            ->whereNot('podio.campeon', null)
            ->whereNot('podio.subcampeon', null)
        );
}

public function test_show_podio_incluye_tercero(): void
{
    $categoria = $this->categoriaConAprobados(4);
    $service = new BracketService;
    $service->generar($categoria);

    // Decidir semifinales (enruta perdedores al tercer lugar).
    foreach (Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Semifinal')->get() as $semi) {
        $service->registrarGanador($semi, $semi->participantes()->pluck('id_inscripcion')->first());
    }
    // Decidir final y tercer lugar.
    $final = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Final')->first();
    $service->registrarGanador($final, $final->participantes()->pluck('id_inscripcion')->first());
    $tercer = Encuentro::where('id_categoria', $categoria->id_categoria)->where('ronda', 'Tercer lugar')->first();
    $service->registrarGanador($tercer, $tercer->participantes()->pluck('id_inscripcion')->first());

    $this->get('/proyeccion/combate/'.$categoria->id_categoria)
        ->assertInertia(fn (Assert $page) => $page->whereNot('podio.tercero', null));
}
```

- [ ] **Step 2: Ejecutar (debe fallar)**

Run: `php artisan test --compact --filter=PodioTest`
Expected: FAIL (no existe la prop `podio`).

- [ ] **Step 3: Método `podio` + prop en `show`**

En `app/Http/Controllers/ProyeccionController.php`, añadir el método privado:
```php
/**
 * @return array<string, string|null>|null
 */
private function podio(Categoria $categoria): ?array
{
    $final = Encuentro::where('id_categoria', $categoria->id_categoria)
        ->where('ronda', 'Final')
        ->with('participantes.inscripcion.robot')
        ->first();

    if ($final === null) {
        return null;
    }

    $ganador = $final->participantes->firstWhere('es_ganador', true);
    if ($ganador === null) {
        return null;
    }

    $subcampeon = $final->participantes->first(fn (ParticipanteEncuentro $p) => $p->id_inscripcion !== $ganador->id_inscripcion);

    $tercerLugar = Encuentro::where('id_categoria', $categoria->id_categoria)
        ->where('ronda', 'Tercer lugar')
        ->with('participantes.inscripcion.robot')
        ->first();

    $tercero = $tercerLugar?->participantes->firstWhere('es_ganador', true);

    return [
        'campeon' => $ganador->inscripcion?->robot?->nombre,
        'subcampeon' => $subcampeon?->inscripcion?->robot?->nombre,
        'tercero' => $tercero?->inscripcion?->robot?->nombre,
    ];
}
```
Y en el array de `Inertia::render('proyeccion/combate', [...])` de `show`, añadir:
```php
            'podio' => $this->podio($categoria),
```

- [ ] **Step 4: Ejecutar (debe pasar)**

Run: `php artisan test --compact --filter=PodioTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ProyeccionController.php tests/Feature/PodioTest.php
git commit -m "feat(podio): ProyeccionController expone el podio (1o/2o/3o)"
```

---

## Task 3: Frontend — tipo, componente de podio y render

**Files:**
- Modify: `resources/js/types/models.ts`
- Create: `resources/js/components/proyeccion/projection-podium.tsx`
- Modify: `resources/js/pages/proyeccion/combate.tsx`

- [ ] **Step 1: Tipo `Podio`**

En `resources/js/types/models.ts`, añadir:
```ts
export type Podio = {
    campeon: string | null;
    subcampeon: string | null;
    tercero: string | null;
};
```

- [ ] **Step 2: Componente de podio**

`resources/js/components/proyeccion/projection-podium.tsx`:
```tsx
import type { Podio } from '@/types';

type Props = {
    podio: Podio;
};

type Escalon = {
    lugar: number;
    medalla: string;
    robot: string | null;
    alturaClase: string;
    destacado: boolean;
};

export default function ProjectionPodium({ podio }: Props) {
    const escalones: Escalon[] = [
        { lugar: 2, medalla: '🥈', robot: podio.subcampeon, alturaClase: 'h-40', destacado: false },
        { lugar: 1, medalla: '🥇', robot: podio.campeon, alturaClase: 'h-56', destacado: true },
        { lugar: 3, medalla: '🥉', robot: podio.tercero, alturaClase: 'h-28', destacado: false },
    ].filter((e) => e.lugar !== 3 || e.robot !== null);

    return (
        <div className="flex flex-col items-center gap-10 py-10">
            <h2 className="font-display text-5xl font-bold uppercase tracking-widest">Podio</h2>
            <div className="flex items-end justify-center gap-6">
                {escalones.map((e) => (
                    <div key={e.lugar} className="flex w-64 flex-col items-center gap-3">
                        <span className="text-6xl">{e.medalla}</span>
                        <span
                            className={
                                e.destacado
                                    ? 'font-display text-4xl font-bold text-primary'
                                    : 'font-display text-3xl text-foreground/80'
                            }
                        >
                            {e.robot ?? '—'}
                        </span>
                        <div
                            className={`flex w-full items-start justify-center rounded-t-xl border-2 pt-3 ${e.alturaClase} ${
                                e.destacado ? 'border-primary bg-primary/15' : 'border-sidebar-border/70 bg-card'
                            }`}
                        >
                            <span className="font-display text-2xl text-muted-foreground">{e.lugar}°</span>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
```

- [ ] **Step 3: Render del podio en la proyección (precede a las vistas)**

En `resources/js/pages/proyeccion/combate.tsx`:
- Añadir import: `import ProjectionPodium from '@/components/proyeccion/projection-podium';` y el tipo `Podio` a la lista de `@/types`.
- En `PageProps`, añadir `podio: Podio | null;` y extraer `podio` de `usePage`.
- En el polling, añadir `'podio'` al `only`:
```tsx
            router.reload({ only: ['encuentros', 'enVivo', 'posiciones', 'reparacionesActivas', 'podio'] });
```
- Envolver el bloque de vistas: cuando `podio` no es null, mostrar SOLO el podio; si no, las vistas actuales. Justo después del bloque de la franja de reparación (el `{reparacionesActivas.length > 0 && (...)}`), insertar:
```tsx
            {podio ? (
                <ProjectionPodium podio={podio} />
            ) : (
                <>
```
y CERRAR el fragmento `</>` justo antes del cierre del `<>...</>` que envuelve el `return` (es decir, las vistas `marcador`/`rotar`/bracket quedan dentro del `else`). Concretamente, las líneas existentes del bloque `vista==='marcador' && enVivo`, el marcador, y el `vista === 'rotar' ? ... : <ProjectionBracket/>` quedan dentro de ese `<>...</>`. Cerrar con:
```tsx
                </>
            )}
```
(Resultado: si hay podio, la pantalla muestra el podio; la barra de control superior y la franja de reparación se mantienen arriba en ambos casos.)

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/types/models.ts resources/js/components/proyeccion/projection-podium.tsx resources/js/pages/proyeccion/combate.tsx
git commit -m "feat(podio): componente de podio en la proyeccion al decidirse la final"
```

---

## Task 4: Verificación integral

**Files:** ninguno.

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todas PASS (169 baseline + PodioTest 6 = 175).

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: Verificación visual manual**

Con `composer run dev`: como Admin generar un bracket de 4 robots; como Juez decidir ambas semifinales, el match de 3er lugar y la final. Abrir `/proyeccion/combate/{id}` → al decidirse la final aparece el **podio** 🥇🥈🥉 (campeón destacado; 3º del match de tercer lugar) sin recargar (polling). Con 2 robots, el podio muestra solo 1º/2º.

- [ ] **Step 5: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(podio): verificacion integral" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- `generar` crea 'Tercer lugar' solo con semifinales (size≥4) → Task 1 ✓
- Perdedores de semifinal enrutados al 'Tercer lugar' (sin perdedor en byes) → Task 1 ✓
- `show` expone `podio` (1º/2º con final decidida, 3º si tercer lugar decidido, null si final abierta) → Task 2 ✓
- Componente de podio a pantalla completa; precede a las vistas; aparece vía polling → Task 3 ✓
- Compat: bracket/enVivo/marcador/reparación intactos → Task 1 Step 6 + suite ✓
- DoD: suite, build, pint, visual → Task 4 ✓

**Notas/riesgos:**
- El 'Tercer lugar' NO se dibuja en el árbol del bracket (fuera de `ORDEN_RONDAS`) — intencional; el podio lo cubre. `enVivo` lo trata con orden 99 (solo se elige "en vivo" si es el único pendiente).
- Si el match de 3er lugar se juega DESPUÉS de la final, el podio aparece con `tercero` null y se completa al decidirlo (vía polling). Aceptado en el alcance.
- El enrutado en `registrarGanador` solo consulta el 'Tercer lugar' cuando `ronda === 'Semifinal'` (sin coste extra en otras decisiones). Idempotente por `firstOrCreate`.
- No hay migración → no requiere `php artisan migrate` en dev para esta feature.
