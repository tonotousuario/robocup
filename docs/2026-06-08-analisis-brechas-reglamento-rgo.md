# Análisis de brechas — Sistema RoboLeague vs Reglamento RGO 2025

**Fecha:** 2026-06-08
**Fuente del reglamento:** Robot Gods Olympics 2025 (`Reglamentos-RGO-2025.pdf`, 16 categorías).
**Propósito:** Comparar lo implementado (Fases 1–2.5) contra el reglamento real, para priorizar mejoras. Documento de referencia; no es spec.

## Contexto

El diseño académico original (`trabajo.tex`) acotó el sistema a **2 tipos de evaluación**: `Combate` (bracket, un solo ganador por encuentro) y `Tiempo` (3 intentos, mejor tiempo). El reglamento RGO real tiene 16 categorías con ~5 modelos distintos de competencia. Parte de la brecha es simplificación deliberada; parte es genuinamente faltante.

## Ya cubierto

- Homologación / checklist: peso medido, dimensiones, estado `Aprobado/Rechazado/Descalificado` + observaciones.
- Bracket de eliminación simple (`encuentros` auto-referencial) → eliminación de sumo.
- Mejor de 3 intentos cronometrados (`intentos_tiempos`) → clasificación de Seguidor de Línea / Reto Loop.
- `penalizacion_segundos` en intentos → reutilizable para el modelo del Drone.
- Inscripciones / tarifas / roles / inspección.

## Brechas

### 1. Amonestaciones / penalizaciones — no existen en el esquema
No hay tabla ni concepto de falta/advertencia/sanción. Uso por categoría:
- **Sumo (Mini/Micro/Nano)**: infracción → pierde el round (tocar robot en el dohyo, colocar tarde en el conteo 1-3, no presentarse en 60 s).
- **Drone**: cada infracción +10 s al puntaje (aterrizar fuera, golpear obstáculo, batería agotada, ruta errónea).
- **Soccer RC**: 2 penalizaciones = pierde el partido (contacto agresivo >5 s, ofensas, tocar balón+robot a la vez).
- **Combate 1/3 lb**: advertencias del juez; sujeción >15 s = reinicio; sanciones / expulsión.

### 2. Combate por rounds — se modela 1 solo ganador
Sumo es **mejor de 3 rounds (2 min c/u)** con reglas de ganador de round y repetición de round. Hoy el juez da 1 clic = ganador del encuentro. `participantes_encuentro.puntos_obtenidos` existe pero está sin usar (podría guardar rounds ganados).

### 3. Acciones del juez faltantes
Hoy: aprobar inspección, marcar ganador (1 clic), capturar tiempos. Faltan: ganador de round, repetir round, tiempo de reparación (5 min, 1 vez por evento), registrar penalizaciones/advertencias, descalificar a mitad de combate, victoria por default (no-show), puntos de agresión/daño/innovación (1/3 lb).

### 4. `tipo_evaluacion` binario
Enum `{Combate, Tiempo}` insuficiente. Modelos requeridos: sumo best-of-3; combate por puntos + fase de grupos con tabla 3/1/0 (1/3 lb); tiempo con final de persecución (seguidor); drone (puntos = tiempo + penalizaciones, 2 intentos); soccer (goles + penalizaciones, eliminación). Soccer y Drone no encajan en los 2 tipos actuales.

### 5. Datos faltantes
Rounds (número + ganador), tabla de penalizaciones, tiempo de reparación usado, resultado por default, goles (soccer), puntos/zonas (drone), fase de grupos con standings.

## Resumen por categoría (del reglamento)

| Categoría | Modelo | Gana | Penalización/amonestación |
|---|---|---|---|
| Mini Sumo Auton. Amateur/Pro | Combate, 3 rounds × 2 min | Mejor de 3 rounds | Infracción → pierde round; reparación 5 min (1 vez) |
| Mini Sumo RC Amateur/Pro | Igual a sumo (RC, sin sensores) | Mejor de 3 rounds | Igual; + moverse antes del conteo → pierde round |
| Micro / Nano Sumo | Igual a sumo (dohyos/pesos menores) | Mejor de 3 rounds | Igual |
| Seguidor de Línea Amateur/Pro (sin/con turbina) | Tiempo: 2 rondas clasif. + final de persecución | Menor tiempo; final = alcanzar al rival | Sin penalización de tiempo; solo descalificación |
| Reto Seguidor Loop | Tiempo: 3 rondas + final top-5 (2 intentos) | Menor tiempo (máx 3 min) | Solo descalificación |
| Reto Drone | Puntos = tiempo + penalizaciones, 2 intentos | Menor puntaje | +10 s por infracción |
| Soccer RC | Eliminación, 4 min (2×2) | Más goles / 2 penal. rival / default 3-0 | 2 penalizaciones = pierde |
| Combate 1 lb / 3 lb | Grupos (tabla 3/1/0) + muerte súbita | KO / puntos agresión(3)+daño(4)+innovación | Advertencias; sujeción >15 s = reinicio; sanción |
| Carrera Otto | Tiempo (reglamento no extraído completo) | Menor tiempo | — |
| Carrera Insecto | Tiempo (~2.1 m) | Menor tiempo | — |

## Prioridad sugerida
1. **Combate por rounds + amonestaciones (sumo)** — aplica a las 6 categorías de sumo; es lo pedido.
2. Tiempo de reparación + acciones de juez transversales.
3. Modelos nuevos (Soccer, Drone, Combate por puntos 1/3 lb) — cada uno casi un módulo.

## Nota de scope (decisión del usuario, 2026-06-08)
- Solo interesan las subcategorías **Amateur/Pro** de lo que ya existe (sumo, seguidor) → son filas de catálogo, sin cambio estructural.
- **Soccer** se evaluará como módulo futuro propio.
- Se arranca por la prioridad #1 (combate por rounds + amonestaciones).
