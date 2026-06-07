# RoboLeague — Fase 1: Capa de Datos (Diseño)

**Fecha:** 2026-06-07
**Origen:** `/home/d4n/Documentos/Skul/base de datos/db_robotica/documentacion/trabajo.tex`
**Alcance de esta spec:** Únicamente la Fase 1 (capa de persistencia). Las Fases 2 (backend) y 3 (frontend React) se especificarán por separado una vez validada esta.

## Contexto

RoboLeague es un gestor integral de competencias de robótica (monolito Laravel + Inertia/React + PostgreSQL). El repositorio es un starter kit limpio de Laravel con Fortify (auth) + Inertia React; solo existe el modelo `User` y la migración base.

Esta fase implementa el **núcleo del proyecto de Administración de Base de Datos**: el esquema relacional en 3FN sobre PostgreSQL con reglas de negocio forzadas nativamente (triggers + constraints), modelos Eloquent, factories, seeders y pruebas.

## Decisiones de diseño

### Motor de base de datos
PostgreSQL (confirmado disponible: PostgreSQL 18.3 corriendo en `localhost:5432`, `pdo_pgsql` habilitado). Se cambia `DB_CONNECTION` de `sqlite` a `pgsql` en `.env` y `.env.example`. La lógica nativa (triggers, vistas, funciones) se versiona dentro de migraciones Laravel mediante `DB::unprepared()`.

### Tabla `users` (no `usuarios`)
El diccionario define `Usuarios(id_usuario, nombre, apellidos, correo, telefono, rol)`. El repo ya tiene la tabla `users` de Fortify usada para autenticación. **Se extiende la tabla `users` existente** en vez de crear una tabla `usuarios` paralela:

| Diccionario | Columna en `users` |
|---|---|
| `id_usuario` | `id` (existente) |
| `nombre` | `name` (existente) |
| `correo` | `email` (existente, unique) |
| `apellidos` | `apellidos` (nueva, not null) |
| `telefono` | `telefono` (nueva, nullable) |
| `rol` | `rol` (nueva, not null, CHECK enum) |

Rationale: el login de Fortify y el actor del torneo son la misma persona; duplicar la entidad rompería la integridad y la coherencia del auth.

### Cadena de reglas de negocio forzadas en BD
Estados (CHECK constraints):
- `users.rol ∈ {Administrador, Juez, Coach, Piloto}`
- `categorias.tipo_evaluacion ∈ {Combate, Tiempo}`
- `inscripciones.estado_pago ∈ {Pendiente, Pagado, Cancelado}`
- `inspecciones_checklist.estado_aprobacion ∈ {Pendiente, Aprobado, Rechazado, Descalificado}`

Triggers (`BEFORE INSERT`, lanzan excepción que aborta la transacción):
- **T1 — `inspecciones_checklist`:** rechaza la inserción si la inscripción referida no tiene `estado_pago = 'Pagado'`.
- **T2 — `participantes_encuentro`:** rechaza la inserción si la inscripción no tiene una inspección con `estado_aprobacion = 'Aprobado'`.
- **T3 — `intentos_tiempos`:** rechaza la inserción si la inscripción no tiene inspección `'Aprobado'`; además fuerza `numero_vuelta` ∈ {1,2,3} y unicidad `(id_inscripcion, numero_vuelta)` para garantizar máx. 3 intentos.

## Esquema (9 tablas de dominio + `users` extendida)

Todas en 3FN. Convención de nombres del diccionario (snake_case en español). FK y `ON DELETE` exactamente como el diccionario.

1. **instituciones** — `id_institucion` PK serial, `nombre` nn, `tipo` nn, `estado` nn.
2. **categorias** — `id_categoria` PK, `nombre` nn, `tipo_evaluacion` nn (CHECK), `peso_maximo_g` int nn, `dimensiones_maximas` null.
3. **users** (extendida) — ver tabla de mapeo arriba.
4. **robots** — `id_robot` PK, `nombre` nn, `id_piloto` FK→users (ON DELETE No Action) nn, `id_institucion` FK→instituciones (ON DELETE Set Null) null, `id_categoria` FK→categorias (ON DELETE No Action) nn.
5. **tarifas** — `id_tarifa` PK, `descripcion` nn, `fecha_inicio_cobro` date nn, `fecha_fin_cobro` date nn, `monto` decimal nn default 0.00.
6. **inscripciones** — `id_inscripcion` PK, `id_robot` FK→robots (Cascade) nn, `id_tarifa` FK→tarifas (No Action) null, `fecha_registro` timestamp nn default now(), `monto_pagado` decimal nn default 0.00, `estado_pago` nn (CHECK).
7. **inspecciones_checklist** — `id_inspeccion` PK, `id_inscripcion` FK→inscripciones (Cascade) nn, `id_juez` FK→users (No Action) nn, `peso_medido_g` int nn, `dimensiones_medidas` nn, `estado_aprobacion` nn (CHECK), `observaciones` text null, `fecha_inspeccion` timestamp nn default now(). [Trigger T1]
8. **encuentros** — `id_encuentro` PK, `id_categoria` FK→categorias (Cascade) nn, `ronda` nn, `id_encuentro_siguiente` FK→encuentros (Set Null, auto-referencial) null.
9. **participantes_encuentro** — PK compuesta (`id_encuentro` FK Cascade, `id_inscripcion` FK Cascade), `puntos_obtenidos` int default 0, `es_ganador` bool default false. [Trigger T2]
10. **intentos_tiempos** — `id_intento` PK, `id_inscripcion` FK→inscripciones (Cascade) nn, `numero_vuelta` int nn, `tiempo_logrado` decimal nn, `penalizacion_segundos` decimal default 0.000. [Trigger T3]

### Vistas
- **`vista_posiciones`**: para categorías de Tiempo, mejor registro válido por inscripción: `MIN(tiempo_logrado + penalizacion_segundos)`, ordenable, con datos de robot/piloto/categoría (RF5.2).
- **`vista_emparejamientos`**: brackets vigentes — encuentros con sus participantes y categoría (RF5.1).

## Modelos Eloquent
Un modelo por tabla con `$table`, `$primaryKey`, `$fillable`/`$guarded`, casts (timestamps, decimals, bool) y relaciones (`belongsTo`/`hasMany`/`belongsToMany`). `User` se amplía con `apellidos`, `telefono`, `rol` en `$fillable` y relaciones (`robotsComoPiloto`, `inspecciones`). `Encuentro` con relación auto-referencial (`siguiente`/`anteriores`). `ParticipanteEncuentro` con PK compuesta.

## Factories y Seeders
- Factories para las 9 tablas + estados/relaciones coherentes con los triggers (p.ej. factory de inscripción con estado `Pagado` para poder encadenar inspección).
- Seeders de catálogos: `CategoriaSeeder` (Mini Sumo, Guerra, Seguidor de Línea, Laberinto), `TarifaSeeder` (Preventa, Fase Regular, Tardía, Demostración a 0.00), `InstitucionSeeder` (ejemplos públicas/privadas/independientes). `DatabaseSeeder` los orquesta.

## Estrategia de pruebas (PHPUnit + PostgreSQL)
Feature tests que prueban la lógica **nativa** (no replicada en PHP):
- T1: insertar inspección con inscripción no `Pagado` ⇒ se lanza `QueryException`.
- T2 (QA-03): insertar participante de combate sin inspección `Aprobado` ⇒ `QueryException`.
- T3: insertar 4º intento o `numero_vuelta` duplicado ⇒ `QueryException`; robot no aprobado ⇒ `QueryException`.
- ON DELETE (QA-04): borrar institución ⇒ `robots.id_institucion` queda NULL; borrar robot ⇒ inscripciones/intentos en cascada.
- CHECK enums: valor inválido en `rol`/`estado_pago`/`estado_aprobacion`/`tipo_evaluacion` ⇒ excepción.
- `vista_posiciones`: con 3 intentos devuelve el menor `tiempo+penalización`.

> Nota: las pruebas requieren una base de datos de testing en PostgreSQL (no `:memory:` sqlite). Se configura `phpunit.xml` para usar una conexión pgsql de testing.

## Fuera de alcance (Fase 1)
Controladores, rutas, Form Requests, API ESP32, pantallas React, autorización por rol — todo eso es Fase 2/3.

## Criterios de aceptación (Definition of Done — Fase 1)
1. `php artisan migrate:fresh --seed` corre limpio contra PostgreSQL.
2. Los 3 triggers abortan las inserciones ilegales (verificado por tests).
3. Los `ON DELETE` se comportan según el diccionario (verificado por tests).
4. Todas las feature tests pasan (`php artisan test`).
5. `vendor/bin/pint --dirty` sin cambios pendientes.
