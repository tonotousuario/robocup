# RoboLeague — Podio de la competencia (con match por el 3er lugar) (Diseño)

**Fecha:** 2026-06-09
**Origen:** Petición del usuario — mostrar un podio de los 3 primeros lugares cuando termina la competencia.
**Alcance:** Añadir un encuentro por el 3er lugar al bracket de combate y mostrar un podio 🥇🥈🥉 en la proyección cuando la final está decidida. Excluye animaciones elaboradas, podio en la vista interna de Combate y cambios de siembra.

## Contexto

En `main`: bracket de eliminación simple (`BracketService::generar` construye el árbol desde la Final; `registrarGanador`/`decidirEncuentro` marca `es_ganador` y avanza el ganador a `id_encuentro_siguiente`). La proyección (`ProyeccionController::show` → `proyeccion/combate.tsx`) muestra Bracket/Marcador/Rotar con polling 5 s; `enVivo` elige el encuentro vigente. Rondas conocidas: Final, Semifinal, Cuartos, Octavos, Dieciseisavos (mapa de orden en `enVivo` y en `ProjectionBracket`/combate).

Modelos: `Encuentro` (`ronda`, `id_encuentro_siguiente`, rel `participantes`), `ParticipanteEncuentro` (`es_ganador`, `inscripcion.robot`).

## Decisiones de diseño (confirmadas)

- **Match por el 3er lugar**: solo cuando hay semifinales (≥4 robots aprobados → existe la ronda Semifinal). Con 2 robots (solo Final), no hay match de 3er lugar y el podio muestra solo 1º/2º.
- **Podio automático**: cuando la Final tiene ganador, la proyección muestra el podio a pantalla completa (precede a las vistas normales). Mientras la final no esté decidida, la proyección funciona como hoy. Se refresca con el polling existente.
- No cambia el modelo de datos: reusa `encuentros` (nueva `ronda = 'Tercer lugar'`), `participantes_encuentro`, `es_ganador`.

## Backend

### `BracketService::generar` — crear el encuentro de 3er lugar
- Tras construir el árbol, si el bracket tiene semifinales (size ≥ 4, es decir existen encuentros con `ronda = 'Semifinal'`), crear un `Encuentro` adicional: `id_categoria`, `ronda = 'Tercer lugar'`, `id_encuentro_siguiente = null` (hermano de la Final, sin hijos, sin participantes iniciales).

### Enrutar perdedores de semifinal al 3er lugar
- En la lógica de decisión (`registrarGanador`, usada por `decidirEncuentro` y por el auto-avance de byes): tras marcar el ganador y avanzar al `id_encuentro_siguiente`, si el encuentro decidido es una **Semifinal** (`ronda === 'Semifinal'`) y existe un encuentro `ronda = 'Tercer lugar'` en la categoría, añadir el **perdedor** (el participante con `es_ganador` false / el id ≠ ganador) al encuentro de 3er lugar vía `ParticipanteEncuentro::firstOrCreate`.
- Implementación: en `registrarGanador($encuentro, $idGanador)`, después del bloque que avanza al siguiente, detectar semifinal y enrutar el perdedor. El perdedor se obtiene de los participantes del encuentro (`id_inscripcion != $idGanador`). El encuentro de 3er lugar: `Encuentro::where('id_categoria', $encuentro->id_categoria)->where('ronda', 'Tercer lugar')->first()`.
- Solo enruta si hay un perdedor real (encuentros de semifinal con 2 participantes; los resueltos por bye con 1 participante no envían perdedor).

### `ProyeccionController::show` — prop `podio`
- Calcular `podio` (o null). `podio = null` si la Final no tiene ganador.
- Cuando la Final (encuentro `ronda = 'Final'` de la categoría) tiene un participante con `es_ganador = true`:
  - `campeon` = robot del ganador de la Final.
  - `subcampeon` = robot del otro participante de la Final.
  - `tercero` = robot del ganador del encuentro `ronda = 'Tercer lugar'` (si existe y tiene ganador; si no, null).
- Forma: `{ campeon: string|null, subcampeon: string|null, tercero: string|null }` o `null`.
- Pasar `podio` como prop a `proyeccion/combate`.

## Frontend (`proyeccion/combate.tsx`)

- Añadir `podio: Podio | null` a `PageProps` (tipo nuevo `Podio = { campeon: string|null; subcampeon: string|null; tercero: string|null }` en `@/types`).
- Si `podio` no es null, renderizar un **componente de podio a pantalla completa** (nuevo `components/proyeccion/projection-podium.tsx`) ANTES del bloque de vistas (precede a Bracket/Marcador/Rotar): tres escalones en orden visual 2º–1º–3º, nombres en `font-display` grande, campeón destacado con `text-primary`/realce y 🥇; 🥈 y 🥉 en los otros. Si `tercero` es null, el escalón 3 no se muestra.
- Incluir `'podio'` en el `only` del polling (`router.reload`) para que el podio aparezca al decidirse la final sin recargar.

## Estrategia de pruebas (feature, PostgreSQL)

- `generar` con 4 aprobados → existe un `Encuentro` con `ronda = 'Tercer lugar'`; con 2 aprobados → NO existe.
- Decidir ambas semifinales → el encuentro de 'Tercer lugar' tiene como participantes a los dos perdedores de semifinal.
- `show`: Final sin ganador → `podio` null. Final decidida → `podio.campeon`/`subcampeon` correctos. Tercer lugar decidido → `podio.tercero` correcto.
- Compat: con 2 robots, decidir la final → `podio` con campeón/subcampeón y `tercero` null; bracket/`enVivo` siguen verdes.

## Fuera de alcance
Animaciones, podio en Combate interno, match por 3er lugar en categorías de Tiempo (solo Combate), cambios de siembra.

## Criterios de aceptación (DoD)
1. `generar` crea el match de 'Tercer lugar' solo con semifinales; los perdedores de semifinal se enrutan a él (tests).
2. `show` expone `podio` (1º/2º siempre con final decidida; 3º si el match de tercer lugar está decidido); null si la final sigue abierta (tests).
3. La proyección muestra el podio a pantalla completa cuando `podio` no es null; aparece vía polling.
4. Compat: bracket, `enVivo`, marcador y franja de reparación intactos (tests verdes).
5. `php artisan test` 100%; `npm run build` sin errores; Pint limpio.
