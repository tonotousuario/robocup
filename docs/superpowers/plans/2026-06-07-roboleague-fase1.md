# RoboLeague Fase 1 — Capa de Datos · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar el esquema relacional 3FN de RoboLeague en PostgreSQL con reglas de negocio forzadas nativamente (triggers + CHECK constraints), modelos Eloquent, factories, seeders y pruebas.

**Architecture:** Una migración Laravel por tabla de dominio; los enums se modelan con `$table->enum()` (genera CHECK constraint nativo en Postgres); triggers y vistas se versionan en migraciones dedicadas con `DB::unprepared()`. Modelos Eloquent con relaciones; factories y seeders coherentes con los triggers. Pruebas feature contra una BD PostgreSQL de testing (no sqlite, porque los triggers son nativos).

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 18, PHPUnit 12, Pint.

**Convenciones del repo (respetar):**
- Modelos usan atributos PHP: `#[Fillable([...])]` / `#[Hidden([...])]` (ver `app/Models/User.php`), no la propiedad `$fillable`.
- `users` PK es `id` (bigint). Tablas de dominio usan PK serial con nombre propio (`bigIncrements('id_xxx')`).
- Tras tocar PHP: `vendor/bin/pint --dirty --format agent`.
- Tests: `php artisan test --compact --filter=...`.

---

## File Structure

**Migraciones** (`database/migrations/`):
- `..._create_instituciones_table.php`
- `..._create_categorias_table.php`
- `..._add_roboleague_columns_to_users_table.php`
- `..._create_robots_table.php`
- `..._create_tarifas_table.php`
- `..._create_inscripciones_table.php`
- `..._create_inspecciones_checklist_table.php`
- `..._create_encuentros_table.php`
- `..._create_participantes_encuentro_table.php`
- `..._create_intentos_tiempos_table.php`
- `..._create_roboleague_triggers.php`
- `..._create_roboleague_views.php`

**Modelos** (`app/Models/`): `Institucion`, `Categoria`, `Robot`, `Tarifa`, `Inscripcion`, `InspeccionChecklist`, `Encuentro`, `ParticipanteEncuentro`, `IntentoTiempo` (+ extender `User`).

**Factories** (`database/factories/`): una por modelo de dominio.

**Seeders** (`database/seeders/`): `InstitucionSeeder`, `CategoriaSeeder`, `TarifaSeeder` (+ orquestar en `DatabaseSeeder`).

**Tests** (`tests/Feature/Database/`): `EsquemaTest`, `TriggersTest`, `CascadasTest`, `VistasTest`.

**Config:** `.env`, `.env.example`, `phpunit.xml`.

---

## Task 0: Configuración de PostgreSQL y entorno de testing

**Files:**
- Modify: `.env`
- Modify: `.env.example`
- Modify: `phpunit.xml`

- [ ] **Step 1: Crear las bases de datos en PostgreSQL**

Run:
```bash
createdb roboleague 2>/dev/null; createdb roboleague_testing 2>/dev/null; psql -lqt | cut -d'|' -f1 | grep -w 'roboleague\|roboleague_testing'
```
Expected: aparecen `roboleague` y `roboleague_testing`. Si `createdb` falla por credenciales, ajustar usuario (p.ej. `sudo -u postgres createdb -O $USER roboleague`).

- [ ] **Step 2: Configurar `.env` para usar PostgreSQL**

Reemplazar el bloque DB en `.env`:
```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=roboleague
DB_USERNAME=d4n
DB_PASSWORD=
```
(Ajustar `DB_USERNAME`/`DB_PASSWORD` a las credenciales reales de Postgres.)

- [ ] **Step 3: Reflejar el cambio en `.env.example`**

En `.env.example` cambiar `DB_CONNECTION=sqlite` → bloque pgsql equivalente (sin credenciales reales: `DB_USERNAME=roboleague`, `DB_PASSWORD=`).

- [ ] **Step 4: Apuntar las pruebas a PostgreSQL de testing**

En `phpunit.xml` reemplazar:
```xml
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
```
por:
```xml
        <env name="DB_CONNECTION" value="pgsql"/>
        <env name="DB_DATABASE" value="roboleague_testing"/>
```

- [ ] **Step 5: Verificar conexión y commit**

Run: `php artisan migrate:fresh && php artisan migrate:rollback`
Expected: corre sin error de conexión (migra solo las tablas base de Laravel).
```bash
git add .env.example phpunit.xml
git commit -m "chore(db): cambiar a PostgreSQL nativo y BD de testing pgsql"
```
(`.env` está en `.gitignore`, no se commitea.)

---

## Task 1: Tabla, modelo, factory y test de `instituciones`

**Files:**
- Create: `database/migrations/<ts>_create_instituciones_table.php`
- Create: `app/Models/Institucion.php`
- Create: `database/factories/InstitucionFactory.php`
- Create: `tests/Feature/Database/EsquemaTest.php`

- [ ] **Step 1: Crear la migración**

Run: `php artisan make:migration create_instituciones_table --no-interaction`

Contenido del método `up()` (y `down()` con `dropIfExists('instituciones')`):
```php
Schema::create('instituciones', function (Blueprint $table) {
    $table->bigIncrements('id_institucion');
    $table->string('nombre');
    $table->string('tipo');
    $table->string('estado');
});
```

- [ ] **Step 2: Crear el modelo**

`app/Models/Institucion.php`:
```php
<?php

namespace App\Models;

use Database\Factories\InstitucionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'tipo', 'estado'])]
class Institucion extends Model
{
    /** @use HasFactory<InstitucionFactory> */
    use HasFactory;

    protected $table = 'instituciones';

    protected $primaryKey = 'id_institucion';

    public $timestamps = false;

    /** @return HasMany<Robot, $this> */
    public function robots(): HasMany
    {
        return $this->hasMany(Robot::class, 'id_institucion', 'id_institucion');
    }
}
```

- [ ] **Step 3: Crear la factory**

`database/factories/InstitucionFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Institucion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Institucion>
 */
class InstitucionFactory extends Factory
{
    protected $model = Institucion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->company(),
            'tipo' => fake()->randomElement(['Pública', 'Privada', 'Independiente']),
            'estado' => fake()->state(),
        ];
    }
}
```

- [ ] **Step 4: Escribir el test que falla**

`tests/Feature/Database/EsquemaTest.php`:
```php
<?php

namespace Tests\Feature\Database;

use App\Models\Institucion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EsquemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_puede_crear_una_institucion(): void
    {
        $institucion = Institucion::factory()->create(['nombre' => 'TESCHA']);

        $this->assertDatabaseHas('instituciones', ['nombre' => 'TESCHA']);
        $this->assertNotNull($institucion->id_institucion);
    }
}
```

- [ ] **Step 5: Ejecutar el test**

Run: `php artisan test --compact --filter=test_se_puede_crear_una_institucion`
Expected: PASS.

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Institucion.php database/migrations database/factories/InstitucionFactory.php tests/Feature/Database/EsquemaTest.php
git commit -m "feat(db): tabla, modelo y factory de instituciones"
```

---

## Task 2: Tabla, modelo, factory y test de `categorias`

**Files:**
- Create: `database/migrations/<ts>_create_categorias_table.php`
- Create: `app/Models/Categoria.php`
- Create: `database/factories/CategoriaFactory.php`
- Modify: `tests/Feature/Database/EsquemaTest.php`

- [ ] **Step 1: Crear la migración**

Run: `php artisan make:migration create_categorias_table --no-interaction`
```php
Schema::create('categorias', function (Blueprint $table) {
    $table->bigIncrements('id_categoria');
    $table->string('nombre');
    $table->enum('tipo_evaluacion', ['Combate', 'Tiempo']);
    $table->integer('peso_maximo_g');
    $table->string('dimensiones_maximas')->nullable();
});
```
(`enum()` genera en Postgres un `varchar` con `CHECK (tipo_evaluacion in ('Combate','Tiempo'))`.)

- [ ] **Step 2: Crear el modelo**

`app/Models/Categoria.php`:
```php
<?php

namespace App\Models;

use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'tipo_evaluacion', 'peso_maximo_g', 'dimensiones_maximas'])]
class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use HasFactory;

    protected $table = 'categorias';

    protected $primaryKey = 'id_categoria';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['peso_maximo_g' => 'integer'];
    }

    /** @return HasMany<Robot, $this> */
    public function robots(): HasMany
    {
        return $this->hasMany(Robot::class, 'id_categoria', 'id_categoria');
    }

    /** @return HasMany<Encuentro, $this> */
    public function encuentros(): HasMany
    {
        return $this->hasMany(Encuentro::class, 'id_categoria', 'id_categoria');
    }
}
```

- [ ] **Step 3: Crear la factory**

`database/factories/CategoriaFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement(['Mini Sumo', 'Guerra', 'Seguidor de Línea', 'Laberinto']),
            'tipo_evaluacion' => fake()->randomElement(['Combate', 'Tiempo']),
            'peso_maximo_g' => fake()->randomElement([500, 1000, 30000]),
            'dimensiones_maximas' => '20x20 cm',
        ];
    }

    public function tiempo(): static
    {
        return $this->state(fn (array $a) => ['tipo_evaluacion' => 'Tiempo']);
    }

    public function combate(): static
    {
        return $this->state(fn (array $a) => ['tipo_evaluacion' => 'Combate']);
    }
}
```

- [ ] **Step 4: Escribir test que falla (incluye el CHECK del enum)**

Añadir a `tests/Feature/Database/EsquemaTest.php` (agregar `use Illuminate\Database\QueryException;` y `use App\Models\Categoria;` arriba):
```php
public function test_se_puede_crear_una_categoria(): void
{
    Categoria::factory()->create(['nombre' => 'Mini Sumo']);

    $this->assertDatabaseHas('categorias', ['nombre' => 'Mini Sumo']);
}

public function test_tipo_evaluacion_invalido_es_rechazado_por_check(): void
{
    $this->expectException(QueryException::class);

    Categoria::factory()->create(['tipo_evaluacion' => 'Invalido']);
}
```

- [ ] **Step 5: Ejecutar los tests**

Run: `php artisan test --compact --filter=EsquemaTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Categoria.php database/migrations database/factories/CategoriaFactory.php tests/Feature/Database/EsquemaTest.php
git commit -m "feat(db): tabla, modelo y factory de categorias con CHECK de tipo_evaluacion"
```

---

## Task 3: Extender `users` con columnas de RoboLeague

**Files:**
- Create: `database/migrations/<ts>_add_roboleague_columns_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Modify: `tests/Feature/Database/EsquemaTest.php`

- [ ] **Step 1: Crear la migración**

Run: `php artisan make:migration add_roboleague_columns_to_users_table --no-interaction`
```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('apellidos')->after('name');
        $table->string('telefono')->nullable()->after('email');
        $table->enum('rol', ['Administrador', 'Juez', 'Coach', 'Piloto'])->default('Piloto')->after('telefono');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['apellidos', 'telefono', 'rol']);
    });
}
```
(Nota: la migración base de `users` no se modifica; esta migración corre después porque su timestamp es posterior.)

- [ ] **Step 2: Actualizar el modelo `User`**

En `app/Models/User.php` cambiar el atributo Fillable para incluir las nuevas columnas:
```php
#[Fillable(['name', 'apellidos', 'email', 'telefono', 'rol', 'password'])]
```
Y añadir relaciones antes del cierre de la clase (con imports `use Illuminate\Database\Eloquent\Relations\HasMany;`):
```php
/** @return HasMany<Robot, $this> */
public function robotsComoPiloto(): HasMany
{
    return $this->hasMany(Robot::class, 'id_piloto', 'id');
}

/** @return HasMany<InspeccionChecklist, $this> */
public function inspecciones(): HasMany
{
    return $this->hasMany(InspeccionChecklist::class, 'id_juez', 'id');
}
```

- [ ] **Step 3: Actualizar `UserFactory`**

En `database/factories/UserFactory.php`, dentro de `definition()`, añadir al array:
```php
'apellidos' => fake()->lastName(),
'telefono' => fake()->phoneNumber(),
'rol' => 'Piloto',
```
Y añadir estados:
```php
public function juez(): static
{
    return $this->state(fn (array $a) => ['rol' => 'Juez']);
}

public function coach(): static
{
    return $this->state(fn (array $a) => ['rol' => 'Coach']);
}
```

- [ ] **Step 4: Escribir test que falla**

Añadir a `EsquemaTest.php` (con `use App\Models\User;`):
```php
public function test_usuario_tiene_columnas_de_roboleague(): void
{
    $juez = User::factory()->juez()->create(['apellidos' => 'Pérez']);

    $this->assertDatabaseHas('users', ['apellidos' => 'Pérez', 'rol' => 'Juez']);
}

public function test_rol_invalido_es_rechazado_por_check(): void
{
    $this->expectException(\Illuminate\Database\QueryException::class);

    User::factory()->create(['rol' => 'Hacker']);
}
```

- [ ] **Step 5: Ejecutar los tests**

Run: `php artisan test --compact --filter=EsquemaTest`
Expected: PASS.

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/User.php database/factories/UserFactory.php database/migrations tests/Feature/Database/EsquemaTest.php
git commit -m "feat(db): extender users con apellidos, telefono y rol (CHECK)"
```

---

## Task 4: Tabla, modelo, factory y test de `robots`

**Files:**
- Create: `database/migrations/<ts>_create_robots_table.php`
- Create: `app/Models/Robot.php`
- Create: `database/factories/RobotFactory.php`
- Modify: `tests/Feature/Database/EsquemaTest.php`

- [ ] **Step 1: Crear la migración**

Run: `php artisan make:migration create_robots_table --no-interaction`
```php
Schema::create('robots', function (Blueprint $table) {
    $table->bigIncrements('id_robot');
    $table->string('nombre');

    $table->unsignedBigInteger('id_piloto');
    $table->foreign('id_piloto')->references('id')->on('users')->onDelete('no action');

    $table->unsignedBigInteger('id_institucion')->nullable();
    $table->foreign('id_institucion')->references('id_institucion')->on('instituciones')->nullOnDelete();

    $table->unsignedBigInteger('id_categoria');
    $table->foreign('id_categoria')->references('id_categoria')->on('categorias')->onDelete('no action');
});
```

- [ ] **Step 2: Crear el modelo**

`app/Models/Robot.php`:
```php
<?php

namespace App\Models;

use Database\Factories\RobotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'id_piloto', 'id_institucion', 'id_categoria'])]
class Robot extends Model
{
    /** @use HasFactory<RobotFactory> */
    use HasFactory;

    protected $table = 'robots';

    protected $primaryKey = 'id_robot';

    public $timestamps = false;

    /** @return BelongsTo<User, $this> */
    public function piloto(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_piloto', 'id');
    }

    /** @return BelongsTo<Institucion, $this> */
    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'id_institucion', 'id_institucion');
    }

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    /** @return HasMany<Inscripcion, $this> */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'id_robot', 'id_robot');
    }
}
```

- [ ] **Step 3: Crear la factory**

`database/factories/RobotFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Institucion;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Robot>
 */
class RobotFactory extends Factory
{
    protected $model = Robot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->word(),
            'id_piloto' => User::factory(),
            'id_institucion' => Institucion::factory(),
            'id_categoria' => Categoria::factory(),
        ];
    }
}
```

- [ ] **Step 4: Escribir test que falla**

Añadir a `EsquemaTest.php` (con `use App\Models\Robot;`):
```php
public function test_se_puede_crear_un_robot_con_relaciones(): void
{
    $robot = Robot::factory()->create(['nombre' => 'Trueno']);

    $this->assertDatabaseHas('robots', ['nombre' => 'Trueno']);
    $this->assertInstanceOf(\App\Models\User::class, $robot->piloto);
    $this->assertInstanceOf(\App\Models\Categoria::class, $robot->categoria);
}
```

- [ ] **Step 5: Ejecutar el test**

Run: `php artisan test --compact --filter=test_se_puede_crear_un_robot_con_relaciones`
Expected: PASS.

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Robot.php database/migrations database/factories/RobotFactory.php tests/Feature/Database/EsquemaTest.php
git commit -m "feat(db): tabla, modelo y factory de robots con FKs"
```

---

## Task 5: Tabla, modelo, factory y test de `tarifas`

**Files:**
- Create: `database/migrations/<ts>_create_tarifas_table.php`
- Create: `app/Models/Tarifa.php`
- Create: `database/factories/TarifaFactory.php`
- Modify: `tests/Feature/Database/EsquemaTest.php`

- [ ] **Step 1: Crear la migración**

Run: `php artisan make:migration create_tarifas_table --no-interaction`
```php
Schema::create('tarifas', function (Blueprint $table) {
    $table->bigIncrements('id_tarifa');
    $table->string('descripcion');
    $table->date('fecha_inicio_cobro');
    $table->date('fecha_fin_cobro');
    $table->decimal('monto', 10, 2)->default(0.00);
});
```

- [ ] **Step 2: Crear el modelo**

`app/Models/Tarifa.php`:
```php
<?php

namespace App\Models;

use Database\Factories\TarifaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['descripcion', 'fecha_inicio_cobro', 'fecha_fin_cobro', 'monto'])]
class Tarifa extends Model
{
    /** @use HasFactory<TarifaFactory> */
    use HasFactory;

    protected $table = 'tarifas';

    protected $primaryKey = 'id_tarifa';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio_cobro' => 'date',
            'fecha_fin_cobro' => 'date',
            'monto' => 'decimal:2',
        ];
    }
}
```

- [ ] **Step 3: Crear la factory**

`database/factories/TarifaFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Tarifa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tarifa>
 */
class TarifaFactory extends Factory
{
    protected $model = Tarifa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->randomElement(['Preventa', 'Fase Regular', 'Tardía']),
            'fecha_inicio_cobro' => '2026-01-01',
            'fecha_fin_cobro' => '2026-12-31',
            'monto' => fake()->randomElement([150.00, 250.00, 400.00]),
        ];
    }
}
```

- [ ] **Step 4: Escribir test que falla**

Añadir a `EsquemaTest.php` (con `use App\Models\Tarifa;`):
```php
public function test_se_puede_crear_una_tarifa(): void
{
    Tarifa::factory()->create(['descripcion' => 'Preventa', 'monto' => 150.00]);

    $this->assertDatabaseHas('tarifas', ['descripcion' => 'Preventa']);
}
```

- [ ] **Step 5: Ejecutar el test**

Run: `php artisan test --compact --filter=test_se_puede_crear_una_tarifa`
Expected: PASS.

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Tarifa.php database/migrations database/factories/TarifaFactory.php tests/Feature/Database/EsquemaTest.php
git commit -m "feat(db): tabla, modelo y factory de tarifas"
```

---

## Task 6: Tabla, modelo, factory y test de `inscripciones`

**Files:**
- Create: `database/migrations/<ts>_create_inscripciones_table.php`
- Create: `app/Models/Inscripcion.php`
- Create: `database/factories/InscripcionFactory.php`
- Modify: `tests/Feature/Database/EsquemaTest.php`

- [ ] **Step 1: Crear la migración**

Run: `php artisan make:migration create_inscripciones_table --no-interaction`
```php
Schema::create('inscripciones', function (Blueprint $table) {
    $table->bigIncrements('id_inscripcion');

    $table->unsignedBigInteger('id_robot');
    $table->foreign('id_robot')->references('id_robot')->on('robots')->cascadeOnDelete();

    $table->unsignedBigInteger('id_tarifa')->nullable();
    $table->foreign('id_tarifa')->references('id_tarifa')->on('tarifas')->onDelete('no action');

    $table->timestamp('fecha_registro')->useCurrent();
    $table->decimal('monto_pagado', 10, 2)->default(0.00);
    $table->enum('estado_pago', ['Pendiente', 'Pagado', 'Cancelado'])->default('Pendiente');
});
```

- [ ] **Step 2: Crear el modelo**

`app/Models/Inscripcion.php`:
```php
<?php

namespace App\Models;

use Database\Factories\InscripcionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id_robot', 'id_tarifa', 'monto_pagado', 'estado_pago'])]
class Inscripcion extends Model
{
    /** @use HasFactory<InscripcionFactory> */
    use HasFactory;

    protected $table = 'inscripciones';

    protected $primaryKey = 'id_inscripcion';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_registro' => 'datetime',
            'monto_pagado' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Robot, $this> */
    public function robot(): BelongsTo
    {
        return $this->belongsTo(Robot::class, 'id_robot', 'id_robot');
    }

    /** @return BelongsTo<Tarifa, $this> */
    public function tarifa(): BelongsTo
    {
        return $this->belongsTo(Tarifa::class, 'id_tarifa', 'id_tarifa');
    }

    /** @return HasMany<InspeccionChecklist, $this> */
    public function inspecciones(): HasMany
    {
        return $this->hasMany(InspeccionChecklist::class, 'id_inscripcion', 'id_inscripcion');
    }

    /** @return HasMany<IntentoTiempo, $this> */
    public function intentos(): HasMany
    {
        return $this->hasMany(IntentoTiempo::class, 'id_inscripcion', 'id_inscripcion');
    }
}
```

- [ ] **Step 3: Crear la factory**

`database/factories/InscripcionFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Inscripcion;
use App\Models\Robot;
use App\Models\Tarifa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inscripcion>
 */
class InscripcionFactory extends Factory
{
    protected $model = Inscripcion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_robot' => Robot::factory(),
            'id_tarifa' => Tarifa::factory(),
            'monto_pagado' => 0.00,
            'estado_pago' => 'Pendiente',
        ];
    }

    public function pagada(): static
    {
        return $this->state(fn (array $a) => ['estado_pago' => 'Pagado', 'monto_pagado' => 250.00]);
    }
}
```

- [ ] **Step 4: Escribir test que falla**

Añadir a `EsquemaTest.php` (con `use App\Models\Inscripcion;`):
```php
public function test_se_puede_crear_una_inscripcion(): void
{
    $inscripcion = Inscripcion::factory()->pagada()->create();

    $this->assertDatabaseHas('inscripciones', ['estado_pago' => 'Pagado']);
    $this->assertInstanceOf(\App\Models\Robot::class, $inscripcion->robot);
}
```

- [ ] **Step 5: Ejecutar el test**

Run: `php artisan test --compact --filter=test_se_puede_crear_una_inscripcion`
Expected: PASS.

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Inscripcion.php database/migrations database/factories/InscripcionFactory.php tests/Feature/Database/EsquemaTest.php
git commit -m "feat(db): tabla, modelo y factory de inscripciones"
```

---

## Task 7: `inspecciones_checklist` + modelo + factory (sin trigger todavía)

**Files:**
- Create: `database/migrations/<ts>_create_inspecciones_checklist_table.php`
- Create: `app/Models/InspeccionChecklist.php`
- Create: `database/factories/InspeccionChecklistFactory.php`
- Modify: `tests/Feature/Database/EsquemaTest.php`

- [ ] **Step 1: Crear la migración**

Run: `php artisan make:migration create_inspecciones_checklist_table --no-interaction`
```php
Schema::create('inspecciones_checklist', function (Blueprint $table) {
    $table->bigIncrements('id_inspeccion');

    $table->unsignedBigInteger('id_inscripcion');
    $table->foreign('id_inscripcion')->references('id_inscripcion')->on('inscripciones')->cascadeOnDelete();

    $table->unsignedBigInteger('id_juez');
    $table->foreign('id_juez')->references('id')->on('users')->onDelete('no action');

    $table->integer('peso_medido_g');
    $table->string('dimensiones_medidas');
    $table->enum('estado_aprobacion', ['Pendiente', 'Aprobado', 'Rechazado', 'Descalificado']);
    $table->text('observaciones')->nullable();
    $table->timestamp('fecha_inspeccion')->useCurrent();
});
```

- [ ] **Step 2: Crear el modelo**

`app/Models/InspeccionChecklist.php`:
```php
<?php

namespace App\Models;

use Database\Factories\InspeccionChecklistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_inscripcion', 'id_juez', 'peso_medido_g', 'dimensiones_medidas', 'estado_aprobacion', 'observaciones'])]
class InspeccionChecklist extends Model
{
    /** @use HasFactory<InspeccionChecklistFactory> */
    use HasFactory;

    protected $table = 'inspecciones_checklist';

    protected $primaryKey = 'id_inspeccion';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'peso_medido_g' => 'integer',
            'fecha_inspeccion' => 'datetime',
        ];
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

- [ ] **Step 3: Crear la factory**

`database/factories/InspeccionChecklistFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspeccionChecklist>
 */
class InspeccionChecklistFactory extends Factory
{
    protected $model = InspeccionChecklist::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_inscripcion' => Inscripcion::factory()->pagada(),
            'id_juez' => User::factory()->juez(),
            'peso_medido_g' => fake()->numberBetween(100, 900),
            'dimensiones_medidas' => '19x19 cm',
            'estado_aprobacion' => 'Pendiente',
            'observaciones' => null,
        ];
    }

    public function aprobado(): static
    {
        return $this->state(fn (array $a) => ['estado_aprobacion' => 'Aprobado']);
    }
}
```

- [ ] **Step 4: Escribir test que falla**

Añadir a `EsquemaTest.php` (con `use App\Models\InspeccionChecklist;`):
```php
public function test_se_puede_crear_una_inspeccion_sobre_inscripcion_pagada(): void
{
    InspeccionChecklist::factory()->aprobado()->create();

    $this->assertDatabaseHas('inspecciones_checklist', ['estado_aprobacion' => 'Aprobado']);
}
```

- [ ] **Step 5: Ejecutar el test**

Run: `php artisan test --compact --filter=test_se_puede_crear_una_inspeccion_sobre_inscripcion_pagada`
Expected: PASS (la factory crea inscripción `pagada`, así que aún sin trigger funciona).

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/InspeccionChecklist.php database/migrations database/factories/InspeccionChecklistFactory.php tests/Feature/Database/EsquemaTest.php
git commit -m "feat(db): tabla, modelo y factory de inspecciones_checklist"
```

---

## Task 8: `encuentros` (auto-referencial) + modelo + factory

**Files:**
- Create: `database/migrations/<ts>_create_encuentros_table.php`
- Create: `app/Models/Encuentro.php`
- Create: `database/factories/EncuentroFactory.php`
- Modify: `tests/Feature/Database/EsquemaTest.php`

- [ ] **Step 1: Crear la migración**

Run: `php artisan make:migration create_encuentros_table --no-interaction`
```php
Schema::create('encuentros', function (Blueprint $table) {
    $table->bigIncrements('id_encuentro');

    $table->unsignedBigInteger('id_categoria');
    $table->foreign('id_categoria')->references('id_categoria')->on('categorias')->cascadeOnDelete();

    $table->string('ronda');

    $table->unsignedBigInteger('id_encuentro_siguiente')->nullable();
    $table->foreign('id_encuentro_siguiente')->references('id_encuentro')->on('encuentros')->nullOnDelete();
});
```

- [ ] **Step 2: Crear el modelo**

`app/Models/Encuentro.php`:
```php
<?php

namespace App\Models;

use Database\Factories\EncuentroFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id_categoria', 'ronda', 'id_encuentro_siguiente'])]
class Encuentro extends Model
{
    /** @use HasFactory<EncuentroFactory> */
    use HasFactory;

    protected $table = 'encuentros';

    protected $primaryKey = 'id_encuentro';

    public $timestamps = false;

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    /** @return BelongsTo<Encuentro, $this> */
    public function siguiente(): BelongsTo
    {
        return $this->belongsTo(Encuentro::class, 'id_encuentro_siguiente', 'id_encuentro');
    }

    /** @return HasMany<Encuentro, $this> */
    public function anteriores(): HasMany
    {
        return $this->hasMany(Encuentro::class, 'id_encuentro_siguiente', 'id_encuentro');
    }

    /** @return HasMany<ParticipanteEncuentro, $this> */
    public function participantes(): HasMany
    {
        return $this->hasMany(ParticipanteEncuentro::class, 'id_encuentro', 'id_encuentro');
    }
}
```

- [ ] **Step 3: Crear la factory**

`database/factories/EncuentroFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Encuentro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Encuentro>
 */
class EncuentroFactory extends Factory
{
    protected $model = Encuentro::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_categoria' => Categoria::factory()->combate(),
            'ronda' => fake()->randomElement(['Cuartos', 'Semifinal', 'Final']),
            'id_encuentro_siguiente' => null,
        ];
    }
}
```

- [ ] **Step 4: Escribir test que falla**

Añadir a `EsquemaTest.php` (con `use App\Models\Encuentro;`):
```php
public function test_encuentro_es_auto_referencial(): void
{
    $final = Encuentro::factory()->create(['ronda' => 'Final']);
    $semi = Encuentro::factory()->create(['ronda' => 'Semifinal', 'id_encuentro_siguiente' => $final->id_encuentro]);

    $this->assertSame($final->id_encuentro, $semi->siguiente->id_encuentro);
    $this->assertTrue($final->anteriores->contains('id_encuentro', $semi->id_encuentro));
}
```

- [ ] **Step 5: Ejecutar el test**

Run: `php artisan test --compact --filter=test_encuentro_es_auto_referencial`
Expected: PASS.

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Encuentro.php database/migrations database/factories/EncuentroFactory.php tests/Feature/Database/EsquemaTest.php
git commit -m "feat(db): tabla, modelo y factory de encuentros auto-referencial"
```

---

## Task 9: `participantes_encuentro` (PK compuesta) + modelo + factory

**Files:**
- Create: `database/migrations/<ts>_create_participantes_encuentro_table.php`
- Create: `app/Models/ParticipanteEncuentro.php`
- Create: `database/factories/ParticipanteEncuentroFactory.php`
- Modify: `tests/Feature/Database/EsquemaTest.php`

- [ ] **Step 1: Crear la migración**

Run: `php artisan make:migration create_participantes_encuentro_table --no-interaction`
```php
Schema::create('participantes_encuentro', function (Blueprint $table) {
    $table->unsignedBigInteger('id_encuentro');
    $table->unsignedBigInteger('id_inscripcion');
    $table->integer('puntos_obtenidos')->default(0);
    $table->boolean('es_ganador')->default(false);

    $table->primary(['id_encuentro', 'id_inscripcion']);
    $table->foreign('id_encuentro')->references('id_encuentro')->on('encuentros')->cascadeOnDelete();
    $table->foreign('id_inscripcion')->references('id_inscripcion')->on('inscripciones')->cascadeOnDelete();
});
```

- [ ] **Step 2: Crear el modelo (PK compuesta, sin incrementos)**

`app/Models/ParticipanteEncuentro.php`:
```php
<?php

namespace App\Models;

use Database\Factories\ParticipanteEncuentroFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_encuentro', 'id_inscripcion', 'puntos_obtenidos', 'es_ganador'])]
class ParticipanteEncuentro extends Model
{
    /** @use HasFactory<ParticipanteEncuentroFactory> */
    use HasFactory;

    protected $table = 'participantes_encuentro';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'puntos_obtenidos' => 'integer',
            'es_ganador' => 'boolean',
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
}
```

- [ ] **Step 3: Crear la factory**

`database/factories/ParticipanteEncuentroFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\ParticipanteEncuentro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParticipanteEncuentro>
 */
class ParticipanteEncuentroFactory extends Factory
{
    protected $model = ParticipanteEncuentro::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_encuentro' => Encuentro::factory(),
            'id_inscripcion' => Inscripcion::factory()->pagada(),
            'puntos_obtenidos' => 0,
            'es_ganador' => false,
        ];
    }
}
```

- [ ] **Step 4: Escribir test que falla**

Añadir a `EsquemaTest.php` (con `use App\Models\ParticipanteEncuentro;`). Para insertar legalmente necesita inspección aprobada (T2 llega en Task 10, pero el test se diseña ya cumpliendo la regla):
```php
public function test_se_puede_registrar_participante_con_inspeccion_aprobada(): void
{
    $inspeccion = \App\Models\InspeccionChecklist::factory()->aprobado()->create();

    $participante = ParticipanteEncuentro::factory()->create([
        'id_inscripcion' => $inspeccion->id_inscripcion,
    ]);

    $this->assertDatabaseHas('participantes_encuentro', [
        'id_inscripcion' => $inspeccion->id_inscripcion,
    ]);
}
```

- [ ] **Step 5: Ejecutar el test**

Run: `php artisan test --compact --filter=test_se_puede_registrar_participante_con_inspeccion_aprobada`
Expected: PASS.

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/ParticipanteEncuentro.php database/migrations database/factories/ParticipanteEncuentroFactory.php tests/Feature/Database/EsquemaTest.php
git commit -m "feat(db): tabla, modelo y factory de participantes_encuentro (PK compuesta)"
```

---

## Task 10: `intentos_tiempos` (CHECK max 3 + unique) + modelo + factory

**Files:**
- Create: `database/migrations/<ts>_create_intentos_tiempos_table.php`
- Create: `app/Models/IntentoTiempo.php`
- Create: `database/factories/IntentoTiempoFactory.php`
- Modify: `tests/Feature/Database/EsquemaTest.php`

- [ ] **Step 1: Crear la migración**

Run: `php artisan make:migration create_intentos_tiempos_table --no-interaction`
```php
public function up(): void
{
    Schema::create('intentos_tiempos', function (Blueprint $table) {
        $table->bigIncrements('id_intento');

        $table->unsignedBigInteger('id_inscripcion');
        $table->foreign('id_inscripcion')->references('id_inscripcion')->on('inscripciones')->cascadeOnDelete();

        $table->integer('numero_vuelta');
        $table->decimal('tiempo_logrado', 8, 3);
        $table->decimal('penalizacion_segundos', 8, 3)->default(0.000);

        $table->unique(['id_inscripcion', 'numero_vuelta']);
    });

    DB::statement('ALTER TABLE intentos_tiempos ADD CONSTRAINT chk_numero_vuelta CHECK (numero_vuelta BETWEEN 1 AND 3)');
}

public function down(): void
{
    Schema::dropIfExists('intentos_tiempos');
}
```
(Añadir `use Illuminate\Support\Facades\DB;` al inicio de la migración.)

- [ ] **Step 2: Crear el modelo**

`app/Models/IntentoTiempo.php`:
```php
<?php

namespace App\Models;

use Database\Factories\IntentoTiempoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_inscripcion', 'numero_vuelta', 'tiempo_logrado', 'penalizacion_segundos'])]
class IntentoTiempo extends Model
{
    /** @use HasFactory<IntentoTiempoFactory> */
    use HasFactory;

    protected $table = 'intentos_tiempos';

    protected $primaryKey = 'id_intento';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero_vuelta' => 'integer',
            'tiempo_logrado' => 'decimal:3',
            'penalizacion_segundos' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<Inscripcion, $this> */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'id_inscripcion', 'id_inscripcion');
    }
}
```

- [ ] **Step 3: Crear la factory**

`database/factories/IntentoTiempoFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Inscripcion;
use App\Models\IntentoTiempo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntentoTiempo>
 */
class IntentoTiempoFactory extends Factory
{
    protected $model = IntentoTiempo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_inscripcion' => Inscripcion::factory()->pagada(),
            'numero_vuelta' => 1,
            'tiempo_logrado' => fake()->randomFloat(3, 5, 60),
            'penalizacion_segundos' => 0.000,
        ];
    }
}
```

- [ ] **Step 4: Escribir test que falla**

Añadir a `EsquemaTest.php` (con `use App\Models\IntentoTiempo;`):
```php
public function test_no_se_permite_numero_vuelta_mayor_a_tres(): void
{
    $inspeccion = \App\Models\InspeccionChecklist::factory()->aprobado()->create();

    $this->expectException(\Illuminate\Database\QueryException::class);

    IntentoTiempo::factory()->create([
        'id_inscripcion' => $inspeccion->id_inscripcion,
        'numero_vuelta' => 4,
    ]);
}
```

- [ ] **Step 5: Ejecutar el test**

Run: `php artisan test --compact --filter=test_no_se_permite_numero_vuelta_mayor_a_tres`
Expected: PASS (la CHECK constraint lanza `QueryException`).

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/IntentoTiempo.php database/migrations database/factories/IntentoTiempoFactory.php tests/Feature/Database/EsquemaTest.php
git commit -m "feat(db): tabla, modelo y factory de intentos_tiempos con CHECK max 3 vueltas"
```

---

## Task 11: Triggers nativos PostgreSQL (T1, T2, T3)

**Files:**
- Create: `database/migrations/<ts>_create_roboleague_triggers.php`
- Create: `tests/Feature/Database/TriggersTest.php`

- [ ] **Step 1: Crear la migración de triggers**

Run: `php artisan make:migration create_roboleague_triggers --no-interaction`

`up()`:
```php
public function up(): void
{
    // T1: bloquear inspección si la inscripción no está Pagada
    DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_validar_pago_inspeccion()
        RETURNS TRIGGER AS $$
        BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM inscripciones
                WHERE id_inscripcion = NEW.id_inscripcion
                  AND estado_pago = 'Pagado'
            ) THEN
                RAISE EXCEPTION 'La inscripcion % no esta Pagada; no puede inspeccionarse', NEW.id_inscripcion;
            END IF;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        CREATE TRIGGER trg_validar_pago_inspeccion
        BEFORE INSERT ON inspecciones_checklist
        FOR EACH ROW EXECUTE FUNCTION fn_validar_pago_inspeccion();
    SQL);

    // T2: bloquear participante de encuentro si no hay inspeccion Aprobado
    DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_validar_aprobacion_encuentro()
        RETURNS TRIGGER AS $$
        BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM inspecciones_checklist
                WHERE id_inscripcion = NEW.id_inscripcion
                  AND estado_aprobacion = 'Aprobado'
            ) THEN
                RAISE EXCEPTION 'La inscripcion % no tiene inspeccion Aprobado; no puede competir', NEW.id_inscripcion;
            END IF;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        CREATE TRIGGER trg_validar_aprobacion_encuentro
        BEFORE INSERT ON participantes_encuentro
        FOR EACH ROW EXECUTE FUNCTION fn_validar_aprobacion_encuentro();
    SQL);

    // T3: bloquear registro de tiempos si no hay inspeccion Aprobado
    DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_validar_aprobacion_tiempo()
        RETURNS TRIGGER AS $$
        BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM inspecciones_checklist
                WHERE id_inscripcion = NEW.id_inscripcion
                  AND estado_aprobacion = 'Aprobado'
            ) THEN
                RAISE EXCEPTION 'La inscripcion % no tiene inspeccion Aprobado; no puede registrar tiempos', NEW.id_inscripcion;
            END IF;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        CREATE TRIGGER trg_validar_aprobacion_tiempo
        BEFORE INSERT ON intentos_tiempos
        FOR EACH ROW EXECUTE FUNCTION fn_validar_aprobacion_tiempo();
    SQL);
}
```

`down()`:
```php
public function down(): void
{
    DB::unprepared('DROP TRIGGER IF EXISTS trg_validar_pago_inspeccion ON inspecciones_checklist');
    DB::unprepared('DROP FUNCTION IF EXISTS fn_validar_pago_inspeccion');
    DB::unprepared('DROP TRIGGER IF EXISTS trg_validar_aprobacion_encuentro ON participantes_encuentro');
    DB::unprepared('DROP FUNCTION IF EXISTS fn_validar_aprobacion_encuentro');
    DB::unprepared('DROP TRIGGER IF EXISTS trg_validar_aprobacion_tiempo ON intentos_tiempos');
    DB::unprepared('DROP FUNCTION IF EXISTS fn_validar_aprobacion_tiempo');
}
```
(Añadir `use Illuminate\Support\Facades\DB;`.)

- [ ] **Step 2: Escribir los tests que fallan**

`tests/Feature/Database/TriggersTest.php`:
```php
<?php

namespace Tests\Feature\Database;

use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\IntentoTiempo;
use App\Models\ParticipanteEncuentro;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TriggersTest extends TestCase
{
    use RefreshDatabase;

    public function test_t1_inspeccion_sobre_inscripcion_no_pagada_es_bloqueada(): void
    {
        $inscripcion = Inscripcion::factory()->create(['estado_pago' => 'Pendiente']);
        $juez = User::factory()->juez()->create();

        $this->expectException(QueryException::class);

        InspeccionChecklist::create([
            'id_inscripcion' => $inscripcion->id_inscripcion,
            'id_juez' => $juez->id,
            'peso_medido_g' => 500,
            'dimensiones_medidas' => '19x19 cm',
            'estado_aprobacion' => 'Aprobado',
        ]);
    }

    public function test_t2_participante_sin_inspeccion_aprobada_es_bloqueado(): void
    {
        $inscripcion = Inscripcion::factory()->pagada()->create();
        $encuentro = \App\Models\Encuentro::factory()->create();

        $this->expectException(QueryException::class);

        ParticipanteEncuentro::create([
            'id_encuentro' => $encuentro->id_encuentro,
            'id_inscripcion' => $inscripcion->id_inscripcion,
        ]);
    }

    public function test_t3_tiempo_sin_inspeccion_aprobada_es_bloqueado(): void
    {
        $inscripcion = Inscripcion::factory()->pagada()->create();

        $this->expectException(QueryException::class);

        IntentoTiempo::create([
            'id_inscripcion' => $inscripcion->id_inscripcion,
            'numero_vuelta' => 1,
            'tiempo_logrado' => 12.500,
        ]);
    }

    public function test_t3_tiempo_con_inspeccion_aprobada_es_permitido(): void
    {
        $inspeccion = InspeccionChecklist::factory()->aprobado()->create();

        IntentoTiempo::create([
            'id_inscripcion' => $inspeccion->id_inscripcion,
            'numero_vuelta' => 1,
            'tiempo_logrado' => 12.500,
        ]);

        $this->assertDatabaseHas('intentos_tiempos', ['numero_vuelta' => 1]);
    }
}
```

- [ ] **Step 3: Ejecutar los tests**

Run: `php artisan test --compact --filter=TriggersTest`
Expected: PASS (4 tests). Cada `expectException` es la última operación del test (evita el estado "transacción abortada" de Postgres bajo RefreshDatabase).

- [ ] **Step 4: Commit**

```bash
git add database/migrations tests/Feature/Database/TriggersTest.php
git commit -m "feat(db): triggers nativos T1/T2/T3 de validacion pago e inspeccion"
```

---

## Task 12: Vistas nativas (`vista_posiciones`, `vista_emparejamientos`)

**Files:**
- Create: `database/migrations/<ts>_create_roboleague_views.php`
- Create: `tests/Feature/Database/VistasTest.php`

- [ ] **Step 1: Crear la migración de vistas**

Run: `php artisan make:migration create_roboleague_views --no-interaction`

`up()`:
```php
public function up(): void
{
    DB::statement(<<<'SQL'
        CREATE VIEW vista_posiciones AS
        SELECT
            i.id_inscripcion,
            r.id_robot,
            r.nombre AS robot,
            c.id_categoria,
            c.nombre AS categoria,
            MIN(t.tiempo_logrado + t.penalizacion_segundos) AS mejor_tiempo,
            COUNT(t.id_intento) AS intentos
        FROM inscripciones i
        JOIN robots r ON r.id_robot = i.id_robot
        JOIN categorias c ON c.id_categoria = r.id_categoria
        JOIN intentos_tiempos t ON t.id_inscripcion = i.id_inscripcion
        WHERE c.tipo_evaluacion = 'Tiempo'
        GROUP BY i.id_inscripcion, r.id_robot, r.nombre, c.id_categoria, c.nombre;
    SQL);

    DB::statement(<<<'SQL'
        CREATE VIEW vista_emparejamientos AS
        SELECT
            e.id_encuentro,
            e.ronda,
            c.nombre AS categoria,
            pe.id_inscripcion,
            r.nombre AS robot,
            pe.puntos_obtenidos,
            pe.es_ganador
        FROM encuentros e
        JOIN categorias c ON c.id_categoria = e.id_categoria
        LEFT JOIN participantes_encuentro pe ON pe.id_encuentro = e.id_encuentro
        LEFT JOIN inscripciones i ON i.id_inscripcion = pe.id_inscripcion
        LEFT JOIN robots r ON r.id_robot = i.id_robot;
    SQL);
}

public function down(): void
{
    DB::statement('DROP VIEW IF EXISTS vista_posiciones');
    DB::statement('DROP VIEW IF EXISTS vista_emparejamientos');
}
```
(Añadir `use Illuminate\Support\Facades\DB;`.)

- [ ] **Step 2: Escribir el test que falla**

`tests/Feature/Database/VistasTest.php`:
```php
<?php

namespace Tests\Feature\Database;

use App\Models\Categoria;
use App\Models\InspeccionChecklist;
use App\Models\IntentoTiempo;
use App\Models\Robot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VistasTest extends TestCase
{
    use RefreshDatabase;

    public function test_vista_posiciones_devuelve_el_mejor_tiempo_con_penalizacion(): void
    {
        $categoria = Categoria::factory()->tiempo()->create();
        $robot = Robot::factory()->create(['id_categoria' => $categoria->id_categoria]);
        $inspeccion = InspeccionChecklist::factory()->aprobado()->create([
            'id_inscripcion' => \App\Models\Inscripcion::factory()->pagada()->create([
                'id_robot' => $robot->id_robot,
            ])->id_inscripcion,
        ]);
        $idInscripcion = $inspeccion->id_inscripcion;

        IntentoTiempo::create(['id_inscripcion' => $idInscripcion, 'numero_vuelta' => 1, 'tiempo_logrado' => 20.000]);
        IntentoTiempo::create(['id_inscripcion' => $idInscripcion, 'numero_vuelta' => 2, 'tiempo_logrado' => 10.000, 'penalizacion_segundos' => 5.000]);
        IntentoTiempo::create(['id_inscripcion' => $idInscripcion, 'numero_vuelta' => 3, 'tiempo_logrado' => 18.000]);

        $fila = DB::table('vista_posiciones')->where('id_inscripcion', $idInscripcion)->first();

        // mejor = min(20.000, 10+5=15.000, 18.000) = 15.000
        $this->assertEquals(15.000, (float) $fila->mejor_tiempo);
        $this->assertEquals(3, (int) $fila->intentos);
    }
}
```

- [ ] **Step 3: Ejecutar el test**

Run: `php artisan test --compact --filter=VistasTest`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add database/migrations tests/Feature/Database/VistasTest.php
git commit -m "feat(db): vistas nativas de posiciones y emparejamientos"
```

---

## Task 13: Seeders de catálogos

**Files:**
- Create: `database/seeders/InstitucionSeeder.php`
- Create: `database/seeders/CategoriaSeeder.php`
- Create: `database/seeders/TarifaSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Create: `tests/Feature/Database/SeedersTest.php`

- [ ] **Step 1: Crear `CategoriaSeeder`**

Run: `php artisan make:seeder CategoriaSeeder --no-interaction`
```php
public function run(): void
{
    $categorias = [
        ['nombre' => 'Mini Sumo', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 500, 'dimensiones_maximas' => '10x10 cm'],
        ['nombre' => 'Guerra', 'tipo_evaluacion' => 'Combate', 'peso_maximo_g' => 30000, 'dimensiones_maximas' => '60x60 cm'],
        ['nombre' => 'Seguidor de Línea', 'tipo_evaluacion' => 'Tiempo', 'peso_maximo_g' => 1000, 'dimensiones_maximas' => '20x20 cm'],
        ['nombre' => 'Laberinto', 'tipo_evaluacion' => 'Tiempo', 'peso_maximo_g' => 1000, 'dimensiones_maximas' => '20x20 cm'],
    ];

    foreach ($categorias as $categoria) {
        \App\Models\Categoria::create($categoria);
    }
}
```

- [ ] **Step 2: Crear `TarifaSeeder`**

Run: `php artisan make:seeder TarifaSeeder --no-interaction`
```php
public function run(): void
{
    $tarifas = [
        ['descripcion' => 'Preventa', 'fecha_inicio_cobro' => '2026-01-01', 'fecha_fin_cobro' => '2026-03-31', 'monto' => 150.00],
        ['descripcion' => 'Fase Regular', 'fecha_inicio_cobro' => '2026-04-01', 'fecha_fin_cobro' => '2026-05-31', 'monto' => 250.00],
        ['descripcion' => 'Tardía', 'fecha_inicio_cobro' => '2026-06-01', 'fecha_fin_cobro' => '2026-06-30', 'monto' => 400.00],
        ['descripcion' => 'Demostración', 'fecha_inicio_cobro' => '2026-01-01', 'fecha_fin_cobro' => '2026-12-31', 'monto' => 0.00],
    ];

    foreach ($tarifas as $tarifa) {
        \App\Models\Tarifa::create($tarifa);
    }
}
```

- [ ] **Step 3: Crear `InstitucionSeeder`**

Run: `php artisan make:seeder InstitucionSeeder --no-interaction`
```php
public function run(): void
{
    $instituciones = [
        ['nombre' => 'TESCHA', 'tipo' => 'Pública', 'estado' => 'México'],
        ['nombre' => 'Tec de Monterrey', 'tipo' => 'Privada', 'estado' => 'Nuevo León'],
        ['nombre' => 'Club Robótica Independiente', 'tipo' => 'Independiente', 'estado' => 'CDMX'],
    ];

    foreach ($instituciones as $institucion) {
        \App\Models\Institucion::create($institucion);
    }
}
```

- [ ] **Step 4: Orquestar en `DatabaseSeeder`**

En `database/seeders/DatabaseSeeder.php`, dentro de `run()` reemplazar el contenido por:
```php
$this->call([
    InstitucionSeeder::class,
    CategoriaSeeder::class,
    TarifaSeeder::class,
]);

User::factory()->create([
    'name' => 'Admin',
    'apellidos' => 'RoboLeague',
    'email' => 'admin@roboleague.test',
    'rol' => 'Administrador',
]);
```

- [ ] **Step 5: Escribir el test que falla**

`tests/Feature/Database/SeedersTest.php`:
```php
<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_catalogos_se_siembran(): void
    {
        $this->seed();

        $this->assertDatabaseCount('categorias', 4);
        $this->assertDatabaseCount('tarifas', 4);
        $this->assertDatabaseCount('instituciones', 3);
        $this->assertDatabaseHas('users', ['email' => 'admin@roboleague.test', 'rol' => 'Administrador']);
    }
}
```

- [ ] **Step 6: Ejecutar el test**

Run: `php artisan test --compact --filter=SeedersTest`
Expected: PASS.

- [ ] **Step 7: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders tests/Feature/Database/SeedersTest.php
git commit -m "feat(db): seeders de catalogos (categorias, tarifas, instituciones)"
```

---

## Task 14: Pruebas de cascadas ON DELETE (QA-04)

**Files:**
- Create: `tests/Feature/Database/CascadasTest.php`

- [ ] **Step 1: Escribir los tests que fallan**

`tests/Feature/Database/CascadasTest.php`:
```php
<?php

namespace Tests\Feature\Database;

use App\Models\Inscripcion;
use App\Models\Institucion;
use App\Models\Robot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CascadasTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrar_institucion_pone_null_en_robot(): void
    {
        $institucion = Institucion::factory()->create();
        $robot = Robot::factory()->create(['id_institucion' => $institucion->id_institucion]);

        $institucion->delete();

        $this->assertDatabaseHas('robots', ['id_robot' => $robot->id_robot, 'id_institucion' => null]);
    }

    public function test_borrar_robot_cascada_inscripciones(): void
    {
        $robot = Robot::factory()->create();
        $inscripcion = Inscripcion::factory()->create(['id_robot' => $robot->id_robot]);

        $robot->delete();

        $this->assertDatabaseMissing('inscripciones', ['id_inscripcion' => $inscripcion->id_inscripcion]);
    }
}
```

- [ ] **Step 2: Ejecutar los tests**

Run: `php artisan test --compact --filter=CascadasTest`
Expected: PASS (2 tests).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Database/CascadasTest.php
git commit -m "test(db): cascadas ON DELETE (set null institucion, cascade robot)"
```

---

## Task 15: Verificación integral de la Fase 1

**Files:** ninguno (verificación).

- [ ] **Step 1: Migración limpia con seed contra la BD real**

Run: `php artisan migrate:fresh --seed`
Expected: corre sin errores; siembra catálogos y usuario admin.

- [ ] **Step 2: Suite completa de pruebas**

Run: `php artisan test --compact`
Expected: todas las pruebas PASS (Database + las pruebas de auth preexistentes del starter kit).

- [ ] **Step 3: Estilo**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes (o aplica y revisa).

- [ ] **Step 4: Commit final si hubo ajustes**

```bash
git add -A
git commit -m "chore(db): verificacion integral Fase 1 RoboLeague" || echo "nada que commitear"
```

---

## Self-Review (cobertura de la spec)

- Esquema 9 tablas + `users` extendida → Tasks 1–10 ✓
- 3FN, FKs y ON DELETE del diccionario → Tasks 4,6,7,8,9,10 (Cascade/Set Null/No Action) ✓
- CHECK enums (rol, tipo_evaluacion, estado_pago, estado_aprobacion) → Tasks 2,3,6,7 ✓
- Trigger T1 (pago→inspección) → Task 11 ✓
- Trigger T2 (aprobado→encuentro) → Task 11 ✓
- Trigger T3 (aprobado→tiempos) + máx 3 vueltas → Tasks 10 (CHECK/unique) + 11 (trigger) ✓
- Vistas posiciones/emparejamientos → Task 12 ✓
- Modelos Eloquent + relaciones (incl. auto-referencial y PK compuesta) → Tasks 1–10 ✓
- Factories y seeders de catálogos → Tasks (factories en cada una) + 13 ✓
- Pruebas de triggers, cascadas (QA-04), checks, vista → Tasks 11,12,14 + EsquemaTest ✓
- PostgreSQL nativo + BD de testing → Task 0 ✓
```
