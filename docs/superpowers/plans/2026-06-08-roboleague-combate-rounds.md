# RoboLeague — Combate por rounds, amonestaciones y resultados especiales · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Evolucionar el combate a mejor de 3 rounds con repetición de round, bitácora de amonestaciones, victoria por default, descalificación y tiempo de reparación por robot; más sembrar categorías Amateur/Pro de sumo.

**Architecture:** Dos tablas nuevas (`rounds_encuentro`, `amonestaciones`) + dos columnas (`encuentros.tipo_resultado`, `inscripciones.reparacion_usada`). `BracketService` gana métodos para registrar round / default / descalificación / amonestación (reusando `registrarGanador` para avanzar el bracket). `EncuentroController` expone esas acciones (Juez+Admin); la UI de combate reemplaza el "1 clic = ganador" por un panel de rounds. El ganador del encuentro sigue en `es_ganador` → bracket y proyección no cambian.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL, Inertia v3, React 19, Tailwind v4, Wayfinder, PHPUnit 12, Pint.

**Convenciones/contexto:**
- Baseline: `main` tiene 139 pruebas (el Modo Proyección está en su rama, sin fusionar — no afecta esto).
- Migraciones nuevas con `php artisan make:migration`. `enum()` en Postgres → CHECK nativo. Modelos `#[Fillable([...])]`, `$timestamps=false` en tablas de dominio, PK propias.
- `BracketService` actual: `generar`, `registrarGanador` (marca `es_ganador` + avanza vía `id_encuentro_siguiente` con firstOrCreate). El auto-avance de byes en `generar` llama `registrarGanador` directo (no debe fijar `tipo_resultado`).
- `EncuentroController` usa `AuthorizesRequests` + `$this->authorize('...', Encuentro::class)`. `EncuentroPolicy` tiene `registrarGanador` (isJuez; admin via before). Reusamos esa ability para todas las acciones de gestión.
- Wayfinder default imports; `resources/js/actions`/`resources/js/routes` gitignored (regenerar). `combate/index.tsx` ya existe (panel a modificar). `ConfirmDeleteDialog`/`toast` disponibles.
- Tras PHP: `vendor/bin/pint --dirty --format agent`. Tests `php artisan test --compact --filter=...`. Frontend gate `npm run build`. RefreshDatabase. Factories: `Categoria::factory()->combate()`, `Robot::factory()`, `Inscripcion::factory()->pagada()`, `InspeccionChecklist::factory()->aprobado()`, `User::factory()->juez()/coach()`.

---

## File Structure

**Backend:**
- Create: `database/migrations/<ts>_create_rounds_encuentro_table.php`
- Create: `database/migrations/<ts>_create_amonestaciones_table.php`
- Create: `database/migrations/<ts>_add_tipo_resultado_to_encuentros_table.php`
- Create: `database/migrations/<ts>_add_reparacion_usada_to_inscripciones_table.php`
- Create: `app/Models/RoundEncuentro.php`, `app/Models/Amonestacion.php`
- Modify: `app/Models/Encuentro.php` (fillable tipo_resultado + rel rounds/amonestaciones), `app/Models/Inscripcion.php` (fillable+cast reparacion_usada)
- Modify: `app/Services/BracketService.php` (métodos nuevos)
- Create: `app/Http/Requests/RegistrarRoundRequest.php`, `app/Http/Requests/AmonestarRequest.php`
- Modify: `app/Http/Controllers/EncuentroController.php` (acciones + index enriquecido)
- Modify: `routes/web.php` (rutas nuevas)
- Modify: `database/seeders/CategoriaSeeder.php` (Amateur/Pro idempotente)

**Frontend:**
- Modify: `resources/js/types/models.ts` (`RoundData`, `AmonestacionRow`, marcador en `EncuentroBracket`)
- Modify: `resources/js/components/combate/registrar-ganador-control.tsx` → reemplazar por panel de rounds (o nuevo `panel-encuentro.tsx`)
- Modify: `resources/js/pages/combate/index.tsx`

**Tests:** `tests/Feature/CombateRoundsTest.php` (esquema+servicio+controlador), `tests/Feature/Database/EsquemaTest.php` (no), `tests/Feature/CategoriaSeeder` cubierto en SeedersTest existente o nuevo.

---

## Task 0: Rama de trabajo

- [ ] **Step 1: Crear la rama desde main**

Run:
```bash
git checkout main && git checkout -b feature/roboleague-combate-rounds
```
Expected: `Switched to a new branch 'feature/roboleague-combate-rounds'`.

---

## Task 1: Migraciones y modelos (capa de datos)

**Files:**
- Create: 4 migraciones (ver File Structure)
- Create: `app/Models/RoundEncuentro.php`, `app/Models/Amonestacion.php`
- Modify: `app/Models/Encuentro.php`, `app/Models/Inscripcion.php`
- Test: `tests/Feature/CombateRoundsTest.php`

- [ ] **Step 1: Escribir el test que falla (esquema/modelos)**

`tests/Feature/CombateRoundsTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Amonestacion;
use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\Robot;
use App\Models\RoundEncuentro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CombateRoundsTest extends TestCase
{
    use RefreshDatabase;

    /** Crea una inscripción aprobada en una categoría de combate. */
    private function inscripcionAprobada(Categoria $categoria): Inscripcion
    {
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $inscripcion = Inscripcion::factory()->pagada()->create(['id_robot' => $robot->id_robot]);
        InspeccionChecklist::factory()->aprobado()->create(['id_inscripcion' => $inscripcion->id_inscripcion]);

        return $inscripcion;
    }

    public function test_tablas_y_columnas_existen(): void
    {
        $categoria = Categoria::factory()->combate()->create();
        $a = $this->inscripcionAprobada($categoria);
        $b = $this->inscripcionAprobada($categoria);
        $encuentro = Encuentro::factory()->create(['id_categoria' => $categoria->id_categoria]);
        \App\Models\ParticipanteEncuentro::create(['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $a->id_inscripcion]);
        \App\Models\ParticipanteEncuentro::create(['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $b->id_inscripcion]);

        $round = RoundEncuentro::create([
            'id_encuentro' => $encuentro->id_encuentro,
            'numero_round' => 1,
            'id_inscripcion_ganador' => $a->id_inscripcion,
            'repetido' => false,
        ]);
        $this->assertDatabaseHas('rounds_encuentro', ['id_round' => $round->id_round, 'numero_round' => 1]);

        $juez = User::factory()->juez()->create();
        $amon = Amonestacion::create([
            'id_encuentro' => $encuentro->id_encuentro,
            'id_inscripcion' => $b->id_inscripcion,
            'id_juez' => $juez->id,
            'numero_round' => 1,
            'motivo' => 'Tocó el robot en el dohyo',
        ]);
        $this->assertDatabaseHas('amonestaciones', ['id_amonestacion' => $amon->id_amonestacion, 'motivo' => 'Tocó el robot en el dohyo']);

        $encuentro->update(['tipo_resultado' => 'Rounds']);
        $this->assertDatabaseHas('encuentros', ['id_encuentro' => $encuentro->id_encuentro, 'tipo_resultado' => 'Rounds']);

        $this->assertFalse($a->fresh()->reparacion_usada);
        $a->update(['reparacion_usada' => true]);
        $this->assertTrue($a->fresh()->reparacion_usada);
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --compact --filter=CombateRoundsTest`
Expected: FAIL (modelos/tablas/columnas inexistentes).

- [ ] **Step 3: Migración `rounds_encuentro`**

Run: `php artisan make:migration create_rounds_encuentro_table --no-interaction`
```php
Schema::create('rounds_encuentro', function (Blueprint $table) {
    $table->bigIncrements('id_round');

    $table->unsignedBigInteger('id_encuentro');
    $table->foreign('id_encuentro')->references('id_encuentro')->on('encuentros')->cascadeOnDelete();

    $table->integer('numero_round');

    $table->unsignedBigInteger('id_inscripcion_ganador')->nullable();
    $table->foreign('id_inscripcion_ganador')->references('id_inscripcion')->on('inscripciones')->nullOnDelete();

    $table->boolean('repetido')->default(false);
    $table->timestamp('fecha')->useCurrent();
});
```
(`down()`: `Schema::dropIfExists('rounds_encuentro');`.)

- [ ] **Step 4: Migración `amonestaciones`**

Run: `php artisan make:migration create_amonestaciones_table --no-interaction`
```php
Schema::create('amonestaciones', function (Blueprint $table) {
    $table->bigIncrements('id_amonestacion');

    $table->unsignedBigInteger('id_encuentro');
    $table->foreign('id_encuentro')->references('id_encuentro')->on('encuentros')->cascadeOnDelete();

    $table->unsignedBigInteger('id_inscripcion');
    $table->foreign('id_inscripcion')->references('id_inscripcion')->on('inscripciones')->cascadeOnDelete();

    $table->unsignedBigInteger('id_juez');
    $table->foreign('id_juez')->references('id')->on('users')->onDelete('no action');

    $table->integer('numero_round')->nullable();
    $table->text('motivo');
    $table->timestamp('fecha')->useCurrent();
});
```
(`down()`: `Schema::dropIfExists('amonestaciones');`.)

- [ ] **Step 5: Migración `encuentros.tipo_resultado`**

Run: `php artisan make:migration add_tipo_resultado_to_encuentros_table --no-interaction`
```php
public function up(): void
{
    Schema::table('encuentros', function (Blueprint $table) {
        $table->enum('tipo_resultado', ['Rounds', 'Default', 'Descalificacion'])->nullable()->after('ronda');
    });
}

public function down(): void
{
    Schema::table('encuentros', function (Blueprint $table) {
        $table->dropColumn('tipo_resultado');
    });
}
```

- [ ] **Step 6: Migración `inscripciones.reparacion_usada`**

Run: `php artisan make:migration add_reparacion_usada_to_inscripciones_table --no-interaction`
```php
public function up(): void
{
    Schema::table('inscripciones', function (Blueprint $table) {
        $table->boolean('reparacion_usada')->default(false);
    });
}

public function down(): void
{
    Schema::table('inscripciones', function (Blueprint $table) {
        $table->dropColumn('reparacion_usada');
    });
}
```

- [ ] **Step 7: Modelo `RoundEncuentro`**

`app/Models/RoundEncuentro.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_encuentro', 'numero_round', 'id_inscripcion_ganador', 'repetido'])]
class RoundEncuentro extends Model
{
    protected $table = 'rounds_encuentro';

    protected $primaryKey = 'id_round';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero_round' => 'integer',
            'repetido' => 'boolean',
            'fecha' => 'datetime',
        ];
    }

    /** @return BelongsTo<Encuentro, $this> */
    public function encuentro(): BelongsTo
    {
        return $this->belongsTo(Encuentro::class, 'id_encuentro', 'id_encuentro');
    }

    /** @return BelongsTo<Inscripcion, $this> */
    public function ganador(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'id_inscripcion_ganador', 'id_inscripcion');
    }
}
```

- [ ] **Step 8: Modelo `Amonestacion`**

`app/Models/Amonestacion.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_encuentro', 'id_inscripcion', 'id_juez', 'numero_round', 'motivo'])]
class Amonestacion extends Model
{
    protected $table = 'amonestaciones';

    protected $primaryKey = 'id_amonestacion';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero_round' => 'integer',
            'fecha' => 'datetime',
        ];
    }

    /** @return BelongsTo<Encuentro, $this> */
    public function encuentro(): BelongsTo
    {
        return $this->belongsTo(Encuentro::class, 'id_encuentro', 'id_encuentro');
    }

    /** @return BelongsTo<Inscripcion, $this> */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'id_inscripcion', 'id_inscripcion');
    }

    /** @return BelongsTo<User, $this> */
    public function juez(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_juez', 'id');
    }
}
```

- [ ] **Step 9: Actualizar `Encuentro` e `Inscripcion`**

En `app/Models/Encuentro.php`:
- Añadir `tipo_resultado` al atributo `#[Fillable([...])]` (queda `['id_categoria', 'ronda', 'id_encuentro_siguiente', 'tipo_resultado']`).
- Añadir relaciones (con `use Illuminate\Database\Eloquent\Relations\HasMany;` si falta):
```php
/** @return HasMany<RoundEncuentro, $this> */
public function rounds(): HasMany
{
    return $this->hasMany(RoundEncuentro::class, 'id_encuentro', 'id_encuentro');
}

/** @return HasMany<Amonestacion, $this> */
public function amonestaciones(): HasMany
{
    return $this->hasMany(Amonestacion::class, 'id_encuentro', 'id_encuentro');
}
```

En `app/Models/Inscripcion.php`:
- Añadir `reparacion_usada` al `#[Fillable([...])]`.
- En `casts()`, añadir `'reparacion_usada' => 'boolean',`.

- [ ] **Step 10: Ejecutar el test (debe pasar)**

Run: `php artisan test --compact --filter=CombateRoundsTest`
Expected: PASS (1 test). Correr `php artisan migrate:fresh` antes si hace falta para aplicar migraciones al test DB (RefreshDatabase lo hace por sí mismo).

- [ ] **Step 11: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Models/RoundEncuentro.php app/Models/Amonestacion.php app/Models/Encuentro.php app/Models/Inscripcion.php tests/Feature/CombateRoundsTest.php
git commit -m "feat(combate): esquema de rounds, amonestaciones, tipo_resultado y reparacion"
```

---

## Task 2: Lógica en `BracketService`

**Files:**
- Modify: `app/Services/BracketService.php`
- Test: `tests/Feature/CombateRoundsTest.php` (añadir tests de servicio)

- [ ] **Step 1: Añadir tests que fallan**

Añadir a `tests/Feature/CombateRoundsTest.php` (con `use App\Services\BracketService;` y `use App\Models\ParticipanteEncuentro;`). Helper para un encuentro con 2 aprobados:
```php
/** @return array{0: Encuentro, 1: int, 2: int} [encuentro, idA, idB] */
private function encuentroConDos(): array
{
    $categoria = Categoria::factory()->combate()->create();
    $a = $this->inscripcionAprobada($categoria);
    $b = $this->inscripcionAprobada($categoria);
    // Encuentro de semifinal con un siguiente (final) para probar el avance.
    $final = Encuentro::factory()->create(['id_categoria' => $categoria->id_categoria]);
    $encuentro = Encuentro::factory()->create(['id_categoria' => $categoria->id_categoria, 'id_encuentro_siguiente' => $final->id_encuentro]);
    ParticipanteEncuentro::create(['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $a->id_inscripcion]);
    ParticipanteEncuentro::create(['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $b->id_inscripcion]);

    return [$encuentro, $a->id_inscripcion, $b->id_inscripcion];
}

public function test_mejor_de_tres_decide_a_dos_rounds(): void
{
    [$encuentro, $a, $b] = $this->encuentroConDos();
    $service = new BracketService;

    $service->registrarRound($encuentro, $a);
    $this->assertNull($encuentro->fresh()->tipo_resultado); // 1-0, sin ganador aún

    $service->registrarRound($encuentro, $a); // 2-0
    $encuentro->refresh();
    $this->assertSame('Rounds', $encuentro->tipo_resultado);
    $this->assertDatabaseHas('participantes_encuentro', ['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $a, 'es_ganador' => true]);
    // avanzó al siguiente
    $this->assertDatabaseHas('participantes_encuentro', ['id_encuentro' => $encuentro->id_encuentro_siguiente, 'id_inscripcion' => $a]);
}

public function test_round_repetido_no_cuenta(): void
{
    [$encuentro, $a, $b] = $this->encuentroConDos();
    $service = new BracketService;

    $service->registrarRound($encuentro, $a);            // 1-0
    $service->registrarRound($encuentro, null, true);    // repetido, no cuenta
    $this->assertNull($encuentro->fresh()->tipo_resultado);
    $this->assertSame(2, $encuentro->fresh()->rounds()->count());
}

public function test_default_decide_y_avanza(): void
{
    [$encuentro, $a, $b] = $this->encuentroConDos();
    (new BracketService)->ganarPorDefault($encuentro, $a);

    $encuentro->refresh();
    $this->assertSame('Default', $encuentro->tipo_resultado);
    $this->assertDatabaseHas('participantes_encuentro', ['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $a, 'es_ganador' => true]);
    $this->assertDatabaseHas('participantes_encuentro', ['id_encuentro' => $encuentro->id_encuentro_siguiente, 'id_inscripcion' => $a]);
}

public function test_descalificacion_da_el_encuentro_al_rival(): void
{
    [$encuentro, $a, $b] = $this->encuentroConDos();
    (new BracketService)->descalificar($encuentro, $a); // descalifica A → gana B

    $encuentro->refresh();
    $this->assertSame('Descalificacion', $encuentro->tipo_resultado);
    $this->assertDatabaseHas('participantes_encuentro', ['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $b, 'es_ganador' => true]);
}

public function test_amonestar_registra_sin_alterar_resultado(): void
{
    [$encuentro, $a, $b] = $this->encuentroConDos();
    $juez = User::factory()->juez()->create();

    (new BracketService)->amonestar($encuentro, $a, 'Colocó tarde', $juez->id, 1);

    $this->assertDatabaseHas('amonestaciones', ['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $a, 'motivo' => 'Colocó tarde']);
    $this->assertNull($encuentro->fresh()->tipo_resultado);
}
```

- [ ] **Step 2: Ejecutar (debe fallar)**

Run: `php artisan test --compact --filter=CombateRoundsTest`
Expected: FAIL (métodos del servicio inexistentes).

- [ ] **Step 3: Implementar los métodos en `BracketService`**

En `app/Services/BracketService.php`, añadir `use App\Models\Amonestacion;` y `use App\Models\RoundEncuentro;` arriba, y estos métodos a la clase:
```php
public function registrarRound(Encuentro $encuentro, ?int $idGanador, bool $repetido = false): void
{
    $numero = $encuentro->rounds()->count() + 1;

    RoundEncuentro::create([
        'id_encuentro' => $encuentro->id_encuentro,
        'numero_round' => $numero,
        'id_inscripcion_ganador' => $repetido ? null : $idGanador,
        'repetido' => $repetido,
    ]);

    if ($repetido || $idGanador === null) {
        return;
    }

    $victorias = RoundEncuentro::where('id_encuentro', $encuentro->id_encuentro)
        ->where('id_inscripcion_ganador', $idGanador)
        ->count();

    if ($victorias >= 2) {
        $this->decidirEncuentro($encuentro, $idGanador, 'Rounds');
    }
}

public function ganarPorDefault(Encuentro $encuentro, int $idGanador): void
{
    $this->decidirEncuentro($encuentro, $idGanador, 'Default');
}

public function descalificar(Encuentro $encuentro, int $idPerdedor): void
{
    $idGanador = (int) ParticipanteEncuentro::where('id_encuentro', $encuentro->id_encuentro)
        ->where('id_inscripcion', '!=', $idPerdedor)
        ->value('id_inscripcion');

    $this->decidirEncuentro($encuentro, $idGanador, 'Descalificacion');
}

public function amonestar(Encuentro $encuentro, int $idInscripcion, string $motivo, int $idJuez, ?int $numeroRound = null): void
{
    Amonestacion::create([
        'id_encuentro' => $encuentro->id_encuentro,
        'id_inscripcion' => $idInscripcion,
        'id_juez' => $idJuez,
        'numero_round' => $numeroRound,
        'motivo' => $motivo,
    ]);
}

private function decidirEncuentro(Encuentro $encuentro, int $idGanador, string $tipo): void
{
    $encuentro->update(['tipo_resultado' => $tipo]);
    $this->registrarGanador($encuentro, $idGanador);
}
```

- [ ] **Step 4: Ejecutar (debe pasar)**

Run: `php artisan test --compact --filter=CombateRoundsTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/BracketService.php tests/Feature/CombateRoundsTest.php
git commit -m "feat(combate): logica de rounds, default, descalificacion y amonestaciones en BracketService"
```

---

## Task 3: `EncuentroController` — acciones HTTP, requests, rutas

**Files:**
- Create: `app/Http/Requests/RegistrarRoundRequest.php`, `app/Http/Requests/AmonestarRequest.php`
- Modify: `app/Http/Controllers/EncuentroController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CombateRoundsTest.php` (añadir tests HTTP/auth)

- [ ] **Step 1: Añadir tests HTTP que fallan**

Añadir a `tests/Feature/CombateRoundsTest.php`:
```php
private function juez(): User
{
    return User::factory()->juez()->create();
}

private function admin(): User
{
    return User::factory()->create(['rol' => 'Administrador']);
}

public function test_juez_registra_round_via_http(): void
{
    [$encuentro, $a, $b] = $this->encuentroConDos();

    $this->actingAs($this->juez())
        ->patch("/encuentros/{$encuentro->id_encuentro}/round", ['id_inscripcion_ganador' => $a, 'repetido' => false])
        ->assertRedirect();

    $this->assertDatabaseHas('rounds_encuentro', ['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion_ganador' => $a]);
}

public function test_coach_y_piloto_no_gestionan_combate(): void
{
    [$encuentro, $a, $b] = $this->encuentroConDos();

    foreach ([User::factory()->coach()->create(), User::factory()->create(['rol' => 'Piloto'])] as $user) {
        $this->actingAs($user)
            ->patch("/encuentros/{$encuentro->id_encuentro}/round", ['id_inscripcion_ganador' => $a])
            ->assertForbidden();
        $this->actingAs($user)
            ->patch("/encuentros/{$encuentro->id_encuentro}/default", ['id_inscripcion' => $a])
            ->assertForbidden();
        $this->actingAs($user)
            ->post("/encuentros/{$encuentro->id_encuentro}/amonestacion", ['id_inscripcion' => $a, 'motivo' => 'x'])
            ->assertForbidden();
    }
}

public function test_admin_default_via_http(): void
{
    [$encuentro, $a, $b] = $this->encuentroConDos();

    $this->actingAs($this->admin())
        ->patch("/encuentros/{$encuentro->id_encuentro}/default", ['id_inscripcion' => $a])
        ->assertRedirect();

    $this->assertSame('Default', $encuentro->fresh()->tipo_resultado);
}

public function test_descalificar_via_http(): void
{
    [$encuentro, $a, $b] = $this->encuentroConDos();

    $this->actingAs($this->juez())
        ->patch("/encuentros/{$encuentro->id_encuentro}/descalificar", ['id_inscripcion' => $a])
        ->assertRedirect();

    $this->assertSame('Descalificacion', $encuentro->fresh()->tipo_resultado);
    $this->assertDatabaseHas('participantes_encuentro', ['id_encuentro' => $encuentro->id_encuentro, 'id_inscripcion' => $b, 'es_ganador' => true]);
}

public function test_no_gestionar_si_ya_hay_ganador(): void
{
    [$encuentro, $a, $b] = $this->encuentroConDos();
    (new BracketService)->ganarPorDefault($encuentro, $a);

    $this->actingAs($this->juez())
        ->patch("/encuentros/{$encuentro->id_encuentro}/round", ['id_inscripcion_ganador' => $b])
        ->assertSessionHasErrors();
}

public function test_amonestar_via_http(): void
{
    [$encuentro, $a, $b] = $this->encuentroConDos();

    $this->actingAs($this->juez())
        ->post("/encuentros/{$encuentro->id_encuentro}/amonestacion", ['id_inscripcion' => $a, 'motivo' => 'Tocó el robot', 'numero_round' => 1])
        ->assertRedirect();

    $this->assertDatabaseHas('amonestaciones', ['id_encuentro' => $encuentro->id_encuentro, 'motivo' => 'Tocó el robot']);
}

public function test_reparacion_una_sola_vez(): void
{
    $categoria = Categoria::factory()->combate()->create();
    $inscripcion = $this->inscripcionAprobada($categoria);

    $this->actingAs($this->juez())
        ->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion")
        ->assertRedirect();
    $this->assertTrue($inscripcion->fresh()->reparacion_usada);

    $this->actingAs($this->juez())
        ->patch("/inscripciones/{$inscripcion->id_inscripcion}/reparacion")
        ->assertSessionHasErrors();
}
```

- [ ] **Step 2: Ejecutar (debe fallar)**

Run: `php artisan test --compact --filter=CombateRoundsTest`
Expected: FAIL (rutas/acciones inexistentes).

- [ ] **Step 3: Form Requests**

`app/Http/Requests/RegistrarRoundRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_inscripcion_ganador' => ['nullable', 'integer'],
            'repetido' => ['boolean'],
        ];
    }
}
```

`app/Http/Requests/AmonestarRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AmonestarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_inscripcion' => ['required', 'integer'],
            'motivo' => ['required', 'string', 'max:1000'],
            'numero_round' => ['nullable', 'integer'],
        ];
    }
}
```

- [ ] **Step 4: Acciones en `EncuentroController`**

En `app/Http/Controllers/EncuentroController.php`, añadir imports (`use App\Http\Requests\RegistrarRoundRequest; use App\Http\Requests\AmonestarRequest; use App\Models\Inscripcion;`) y métodos. Helper privado de guardas y las 5 acciones:
```php
private function asegurarGestionable(Encuentro $encuentro): ?\Illuminate\Http\RedirectResponse
{
    $participantes = $encuentro->participantes;

    if ($participantes->count() < 2) {
        return back()->withErrors(['encuentro' => 'El encuentro aún no tiene dos participantes.']);
    }

    if ($participantes->firstWhere('es_ganador', true) !== null) {
        return back()->withErrors(['encuentro' => 'El encuentro ya tiene un ganador.']);
    }

    return null;
}

public function registrarRound(RegistrarRoundRequest $request, Encuentro $encuentro): RedirectResponse
{
    $this->authorize('registrarGanador', Encuentro::class);

    if ($error = $this->asegurarGestionable($encuentro)) {
        return $error;
    }

    $repetido = $request->boolean('repetido');
    $idGanador = $request->input('id_inscripcion_ganador') !== null ? $request->integer('id_inscripcion_ganador') : null;

    if (! $repetido) {
        if ($idGanador === null || ! $encuentro->participantes->contains('id_inscripcion', $idGanador)) {
            return back()->withErrors(['id_inscripcion_ganador' => 'Selecciona un robot participante como ganador del round.']);
        }
    }

    $this->bracket->registrarRound($encuentro, $idGanador, $repetido);

    return back()->with('success', 'Round registrado.');
}

public function ganarPorDefault(Request $request, Encuentro $encuentro): RedirectResponse
{
    $this->authorize('registrarGanador', Encuentro::class);

    if ($error = $this->asegurarGestionable($encuentro)) {
        return $error;
    }

    $id = $request->integer('id_inscripcion');
    if (! $encuentro->participantes->contains('id_inscripcion', $id)) {
        return back()->withErrors(['id_inscripcion' => 'Ese robot no participa en este encuentro.']);
    }

    $this->bracket->ganarPorDefault($encuentro, $id);

    return back()->with('success', 'Ganador por default registrado.');
}

public function descalificar(Request $request, Encuentro $encuentro): RedirectResponse
{
    $this->authorize('registrarGanador', Encuentro::class);

    if ($error = $this->asegurarGestionable($encuentro)) {
        return $error;
    }

    $id = $request->integer('id_inscripcion');
    if (! $encuentro->participantes->contains('id_inscripcion', $id)) {
        return back()->withErrors(['id_inscripcion' => 'Ese robot no participa en este encuentro.']);
    }

    $this->bracket->descalificar($encuentro, $id);

    return back()->with('success', 'Robot descalificado.');
}

public function amonestar(AmonestarRequest $request, Encuentro $encuentro): RedirectResponse
{
    $this->authorize('registrarGanador', Encuentro::class);

    $id = $request->integer('id_inscripcion');
    if (! $encuentro->participantes->contains('id_inscripcion', $id)) {
        return back()->withErrors(['id_inscripcion' => 'Ese robot no participa en este encuentro.']);
    }

    $this->bracket->amonestar($encuentro, $id, $request->string('motivo')->toString(), $request->user()->id, $request->input('numero_round') !== null ? $request->integer('numero_round') : null);

    return back()->with('success', 'Amonestación registrada.');
}

public function marcarReparacion(Request $request, Inscripcion $inscripcion): RedirectResponse
{
    $this->authorize('registrarGanador', Encuentro::class);

    if ($inscripcion->reparacion_usada) {
        return back()->withErrors(['reparacion' => 'Este robot ya usó su tiempo de reparación.']);
    }

    $inscripcion->update(['reparacion_usada' => true]);

    return back()->with('success', 'Tiempo de reparación registrado.');
}
```

- [ ] **Step 5: Enriquecer `index`**

En el `map()` de `index` de `EncuentroController`, añadir a cada encuentro (cargar relaciones): cambiar el `with([...])` a `->with(['participantes.inscripcion.robot', 'rounds', 'amonestaciones'])` y añadir al array mapeado:
```php
'tipo_resultado' => $e->tipo_resultado,
'marcador' => $e->rounds->whereNull('id_inscripcion_ganador', '!=')->groupBy('id_inscripcion_ganador')->map->count(),
'amonestaciones' => $e->amonestaciones->map(fn ($am) => [
    'id_amonestacion' => $am->id_amonestacion,
    'id_inscripcion' => $am->id_inscripcion,
    'motivo' => $am->motivo,
    'numero_round' => $am->numero_round,
])->values(),
```
Y en `participantes`, añadir `reparacion_usada` desde `$p->inscripcion?->reparacion_usada`. (Implementación exacta del marcador: contar rounds con ganador por inscripción; ver nota abajo si `whereNull(...,'!=')` no aplica — usar `->filter(fn($r)=>$r->id_inscripcion_ganador!==null)->groupBy('id_inscripcion_ganador')->map->count()`.)

> Nota: usar exactamente:
> ```php
> 'marcador' => $e->rounds->filter(fn ($r) => $r->id_inscripcion_ganador !== null)
>     ->groupBy('id_inscripcion_ganador')->map->count(),
> ```

- [ ] **Step 6: Rutas**

En `routes/web.php`, dentro del grupo `['auth','verified']` (donde están las rutas de combate), añadir:
```php
    Route::patch('encuentros/{encuentro}/round', [EncuentroController::class, 'registrarRound'])->name('encuentros.round');
    Route::patch('encuentros/{encuentro}/default', [EncuentroController::class, 'ganarPorDefault'])->name('encuentros.default');
    Route::patch('encuentros/{encuentro}/descalificar', [EncuentroController::class, 'descalificar'])->name('encuentros.descalificar');
    Route::post('encuentros/{encuentro}/amonestacion', [EncuentroController::class, 'amonestar'])->name('encuentros.amonestar');
    Route::patch('inscripciones/{inscripcion}/reparacion', [EncuentroController::class, 'marcarReparacion'])->name('inscripciones.reparacion');
```
(Conservar `encuentros/{encuentro}/ganador` existente; la UI dejará de usarlo pero su test sigue verde.)

- [ ] **Step 7: Ejecutar (debe pasar)**

Run: `php artisan test --compact --filter=CombateRoundsTest`
Expected: PASS (todos). Si `assertInertia` no aplica aquí (estos tests usan redirect), ok.

- [ ] **Step 8: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/RegistrarRoundRequest.php app/Http/Requests/AmonestarRequest.php app/Http/Controllers/EncuentroController.php routes/web.php tests/Feature/CombateRoundsTest.php
git commit -m "feat(combate): acciones HTTP de round, default, descalificacion, amonestacion y reparacion"
```

---

## Task 4: Seeder de categorías Amateur/Pro (idempotente)

**Files:**
- Modify: `database/seeders/CategoriaSeeder.php`
- Test: `tests/Feature/Database/SeedersTest.php` (ajustar conteo) — ver nota

- [ ] **Step 1: Reescribir `CategoriaSeeder` (idempotente + Amateur/Pro)**

Reemplazar `database/seeders/CategoriaSeeder.php` por:
```php
<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['nombre' => 'Mini Sumo Autónomo Amateur', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 350, 'dimensiones_maximas' => '10x10 cm'],
            ['nombre' => 'Mini Sumo Autónomo Profesional', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 500, 'dimensiones_maximas' => '10x10 cm'],
            ['nombre' => 'Mini Sumo RC Amateur', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 350, 'dimensiones_maximas' => '10x10 cm'],
            ['nombre' => 'Mini Sumo RC Profesional', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 500, 'dimensiones_maximas' => '10x10 cm'],
            ['nombre' => 'Micro Sumo', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 100, 'dimensiones_maximas' => '5x5 cm'],
            ['nombre' => 'Nano Sumo', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 25, 'dimensiones_maximas' => '2.5x2.5 cm'],
            ['nombre' => 'Seguidor de Línea Amateur', 'tipo_evaluacion' => 'Tiempo', 'peso_maximo_g' => 1000, 'dimensiones_maximas' => '25x25 cm'],
            ['nombre' => 'Seguidor de Línea Profesional', 'tipo_evaluacion' => 'Tiempo', 'peso_maximo_g' => 1000, 'dimensiones_maximas' => '25x25 cm'],
        ];

        foreach ($categorias as $categoria) {
            Categoria::firstOrCreate(['nombre' => $categoria['nombre']], $categoria);
        }
    }
}
```

- [ ] **Step 2: Ajustar el test de seeders**

En `tests/Feature/Database/SeedersTest.php`, el test que afirma `assertDatabaseCount('categorias', 4)` debe pasar a `8` (el nuevo número). Si el test verifica nombres específicos antiguos (p. ej. 'Mini Sumo'), actualizarlos a los nuevos (p. ej. `assertDatabaseHas('categorias', ['nombre' => 'Mini Sumo Autónomo Profesional'])`). Mantener la estructura del test.

- [ ] **Step 3: Ejecutar seeders + su test**

Run: `php artisan test --compact --filter=SeedersTest`
Expected: PASS. Además `php artisan migrate:fresh --seed` corre limpio y crea 8 categorías; correrlo dos veces no duplica (idempotente).

- [ ] **Step 4: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders/CategoriaSeeder.php tests/Feature/Database/SeedersTest.php
git commit -m "feat(catalogo): categorias Amateur/Pro de sumo y seguidor (idempotente)"
```

---

## Task 5: Wayfinder + tipos + UI del panel de combate

**Files:**
- Modify: `resources/js/types/models.ts`
- Modify: `resources/js/components/combate/registrar-ganador-control.tsx`
- Modify: `resources/js/pages/combate/index.tsx`

- [ ] **Step 1: Regenerar Wayfinder**

Run: `php artisan wayfinder:generate`
Expected: el `EncuentroController` generado incluye `registrarRound`, `ganarPorDefault`, `descalificar`, `amonestar`, `marcarReparacion`. Sin errores.

- [ ] **Step 2: Tipos**

En `resources/js/types/models.ts`:
- Añadir:
```ts
export type AmonestacionRow = {
    id_amonestacion: number;
    id_inscripcion: number;
    motivo: string;
    numero_round: number | null;
};
```
- Extender `EncuentroBracket` (añadir campos): `tipo_resultado: string | null;`, `marcador: Record<string, number>;`, `amonestaciones: AmonestacionRow[];`. Y a `ParticipanteBracket` añadir `reparacion_usada: boolean;`.

- [ ] **Step 3: Panel de combate (reemplaza el control de ganador)**

Reemplazar `resources/js/components/combate/registrar-ganador-control.tsx` por un panel completo:
```tsx
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import EncuentroController from '@/actions/App/Http/Controllers/EncuentroController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EncuentroBracket } from '@/types';

type Props = {
    encuentro: EncuentroBracket;
};

const onError = (errors: Record<string, string>) => {
    const message = Object.values(errors)[0];
    if (message) {
        toast.error(message);
    }
};

export default function PanelEncuentro({ encuentro }: Props) {
    const [motivo, setMotivo] = useState('');
    const [amonestado, setAmonestado] = useState<number | null>(null);

    const round = (idGanador: number | null, repetido = false) => {
        router.patch(
            EncuentroController.registrarRound.url(encuentro.id_encuentro),
            { id_inscripcion_ganador: idGanador, repetido },
            { preserveScroll: true, onError },
        );
    };

    const accion = (ruta: string, idInscripcion: number) => {
        router.patch(ruta, { id_inscripcion: idInscripcion }, { preserveScroll: true, onError });
    };

    const amonestar = (idInscripcion: number) => {
        router.post(
            EncuentroController.amonestar.url(encuentro.id_encuentro),
            { id_inscripcion: idInscripcion, motivo },
            {
                preserveScroll: true,
                onError,
                onSuccess: () => {
                    setMotivo('');
                    setAmonestado(null);
                },
            },
        );
    };

    return (
        <div className="mt-2 flex flex-col gap-2 border-t border-sidebar-border/40 pt-2">
            <p className="text-xs text-muted-foreground">
                Marcador:{' '}
                {encuentro.participantes
                    .map((p) => `${p.robot ?? '—'} ${encuentro.marcador[String(p.id_inscripcion)] ?? 0}`)
                    .join(' · ')}
            </p>

            <div className="flex flex-wrap gap-1">
                {encuentro.participantes.map((p) => (
                    <Button key={`round-${p.id_inscripcion}`} size="sm" variant="secondary" onClick={() => round(p.id_inscripcion)}>
                        Gana round {p.robot ?? '—'}
                    </Button>
                ))}
                <Button size="sm" variant="ghost" onClick={() => round(null, true)}>
                    Repetir round
                </Button>
            </div>

            <div className="flex flex-wrap gap-1">
                {encuentro.participantes.map((p) => (
                    <Button
                        key={`def-${p.id_inscripcion}`}
                        size="sm"
                        variant="ghost"
                        onClick={() => accion(EncuentroController.ganarPorDefault.url(encuentro.id_encuentro), p.id_inscripcion)}
                    >
                        Default {p.robot ?? '—'}
                    </Button>
                ))}
                {encuentro.participantes.map((p) => (
                    <Button
                        key={`dq-${p.id_inscripcion}`}
                        size="sm"
                        variant="destructive"
                        onClick={() => accion(EncuentroController.descalificar.url(encuentro.id_encuentro), p.id_inscripcion)}
                    >
                        Descalificar {p.robot ?? '—'}
                    </Button>
                ))}
            </div>

            <Dialog>
                <DialogTrigger asChild>
                    <Button size="sm" variant="outline">
                        Amonestar
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Registrar amonestación</DialogTitle>
                    </DialogHeader>
                    <div className="flex flex-col gap-3">
                        <div className="flex gap-2">
                            {encuentro.participantes.map((p) => (
                                <Button
                                    key={`am-${p.id_inscripcion}`}
                                    size="sm"
                                    variant={amonestado === p.id_inscripcion ? 'default' : 'secondary'}
                                    onClick={() => setAmonestado(p.id_inscripcion)}
                                >
                                    {p.robot ?? '—'}
                                </Button>
                            ))}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`motivo-${encuentro.id_encuentro}`}>Motivo</Label>
                            <Input
                                id={`motivo-${encuentro.id_encuentro}`}
                                value={motivo}
                                onChange={(e) => setMotivo(e.target.value)}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            disabled={amonestado === null || motivo.trim() === ''}
                            onClick={() => amonestado !== null && amonestar(amonestado)}
                        >
                            Registrar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {encuentro.amonestaciones.length > 0 && (
                <ul className="text-xs text-muted-foreground">
                    {encuentro.amonestaciones.map((a) => (
                        <li key={a.id_amonestacion}>⚠ {a.motivo}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}
```

- [ ] **Step 4: Usar el panel en `combate/index.tsx`**

En `resources/js/pages/combate/index.tsx`:
- Cambiar el import `RegistrarGanadorControl` por `PanelEncuentro` (mismo archivo/ruta `@/components/combate/registrar-ganador-control`, ahora exporta `PanelEncuentro`). Ajustar el import a:
  `import PanelEncuentro from '@/components/combate/registrar-ganador-control';`
- Donde hoy se renderiza `<RegistrarGanadorControl encuentro={encuentro} />` (en encuentros con 2 participantes sin ganador), reemplazar por `<PanelEncuentro encuentro={encuentro} />`.
- (El resto de la página —selector de categoría, generar/regenerar, render del bracket— no cambia.)

- [ ] **Step 5: Verificar build**

Run: `npm run build`
Expected: exitoso, sin errores TS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/types/models.ts resources/js/components/combate/registrar-ganador-control.tsx resources/js/pages/combate/index.tsx
git commit -m "feat(combate): panel de rounds, especiales y amonestaciones en la UI"
```

---

## Task 6: Verificación integral

**Files:** ninguno.

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todas PASS (139 baseline + nuevos de CombateRoundsTest; SeedersTest ajustado). Reportar el total.

- [ ] **Step 2: Estilo PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 3: Build frontend**

Run: `npm run build`
Expected: exitoso.

- [ ] **Step 4: migrate:fresh --seed limpio**

Run: `php artisan migrate:fresh --seed`
Expected: corre sin errores; 8 categorías sembradas.

- [ ] **Step 5: Commit final si hubo ajustes**

```bash
git add -A && git commit -m "chore(combate): verificacion integral rounds/amonestaciones" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- `rounds_encuentro`, `amonestaciones`, `encuentros.tipo_resultado`, `inscripciones.reparacion_usada` + modelos → Task 1 ✓
- BracketService: registrarRound (mejor de 3, decide a 2), repetido no cuenta, default, descalificar, amonestar, decidirEncuentro → Task 2 ✓
- Controller: round/default/descalificar/amonestar/reparación con guardas (2 participantes, sin ganador, pertenencia) + index enriquecido (marcador, amonestaciones, reparacion_usada) → Task 3 ✓
- Rutas nuevas (conserva `ganador`) → Task 3 ✓
- Autorización Juez+Admin; Coach/Piloto 403 → Task 3 (tests) ✓
- Reparación por robot una sola vez → Task 3 (test) ✓
- Seeder Amateur/Pro idempotente → Task 4 ✓
- UI: panel de rounds + especiales + amonestar + (reparación: ver nota) → Task 5 ✓
- Compat bracket/proyección (es_ganador intacto) → Tasks 2,5 ✓
- DoD: suite, build, pint, migrate:fresh → Task 6 ✓

**Notas/riesgos:**
- (Reparación en UI) La Task 5 deja el toggle de reparación como mejora opcional dentro del panel; la ruta/acción y su test ya existen (Task 3). Si se quiere el botón visible, añadir en `PanelEncuentro` un `Button` por participante que llame `router.patch(EncuentroController.marcarReparacion.url(p.id_inscripcion))` usando `p.reparacion_usada` para deshabilitarlo. No bloquea el DoD.
- (Marcador) `marcador` es `Record<idInscripcion, conteo>`; en el front se accede con `marcador[String(p.id_inscripcion)]`.
- (SeedersTest) Si el test de seeders afirmaba 4 categorías o nombres viejos, actualizarlo (Task 4 Step 2) — es el único test preexistente afectado.
- (Wayfinder) `EncuentroController.registrarRound.url(id)` etc. con escalar; si no tipa, usar `{ encuentro: id }`. `marcarReparacion.url(id)` usa `{ inscripcion: id }` si el escalar no aplica.
- (`encuentros/{encuentro}/ganador`) se conserva; su `EncuentroTest` sigue verde.
