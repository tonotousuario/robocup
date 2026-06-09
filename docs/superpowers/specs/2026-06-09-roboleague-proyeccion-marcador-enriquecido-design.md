# RoboLeague — Vista "Marcador" de proyección enriquecida (Diseño)

**Fecha:** 2026-06-09
**Origen:** Petición del usuario — mostrar en la proyección el marcador de rounds y las amonestaciones del encuentro en vivo.
**Dependencia:** Construye sobre la rama del **cronómetro de reparación** (que ya añadió la franja de reparación a la proyección). Esa rama debe estar en `main` antes de implementar esto.
**Alcance:** Enriquecer SOLO la vista "Marcador" de la proyección con el marcador de rounds y la lista de amonestaciones del encuentro en vivo. No toca el Bracket ni el cronómetro (ya hecho).

## Contexto

En la proyección (`ProyeccionController::show` → `proyeccion/combate.tsx`), la vista "Marcador" muestra un panel grande con `enVivo` = `{ id_encuentro, ronda, robots:[a,b] }` ("Martillo vs Sierra"). El combate (en `main`) ya tiene rounds (`rounds_encuentro`, ganador por round) y amonestaciones (`amonestaciones`), expuestos en el panel del juez pero NO en la proyección. El método `enVivo(Collection $encuentros)` elige el encuentro vigente (2 participantes, sin ganador, ronda más avanzada).

Modelos: `Encuentro` (rel `rounds()`, `amonestaciones()`, `participantes.inscripcion.robot`), `RoundEncuentro` (`id_inscripcion_ganador` nullable), `Amonestacion` (`id_inscripcion`, `motivo`, rel `inscripcion.robot`).

## Decisiones de diseño

- Solo la vista "Marcador" (en vivo). El Bracket queda limpio.
- **Marcador de rounds**: rounds ganados por cada robot del encuentro en vivo (rounds con `id_inscripcion_ganador` no null, agrupados; repetidos no cuentan).
- **Amonestaciones**: lista del encuentro en vivo con `{ robot, motivo }`.
- Se refresca con el polling de 5 s existente; sin WebSockets.

## Backend (`ProyeccionController`)

Ampliar `enVivo()` para devolver, además de `{ id_encuentro, ronda, robots }`:
- `marcador`: arreglo ordenado de los participantes del encuentro en vivo con su nombre y rounds ganados, p. ej. `[{ robot, rounds }, { robot, rounds }]` (rounds = conteo de `rounds_encuentro` con `id_inscripcion_ganador` = esa inscripción).
- `amonestaciones`: `[{ robot, motivo }]` del encuentro en vivo.

Para esto, `enVivo` necesita el `id_encuentro` del vigente y cargar sus `rounds` + `amonestaciones.inscripcion.robot`. Implementación sugerida: tras identificar el encuentro vigente (ya se hace por la colección mapeada), recargar ese `Encuentro` con `with(['participantes.inscripcion.robot','rounds','amonestaciones.inscripcion.robot'])` y computar marcador/amonestaciones; o cargar esas relaciones desde el inicio en `show` y pasarlas a `enVivo`. (El detalle exacto al planear; mantener `enVivo` null cuando no hay vigente.)

Forma final de `enVivo` (o null):
```
{
  id_encuentro, ronda,
  robots: [a, b],
  marcador: [{ robot, rounds }, { robot, rounds }],
  amonestaciones: [{ robot, motivo }, ...]
}
```

## Frontend (`proyeccion/combate.tsx`, vista Marcador)

Dentro del bloque que ya se renderiza con `vista === 'marcador' && enVivo`, debajo del "Robot A vs Robot B":
- **Marcador de rounds** grande: `{marcador[0].robot} {marcador[0].rounds} – {marcador[1].rounds} {marcador[1].robot}`, con el número del acento.
- **Amonestaciones**: si `enVivo.amonestaciones.length > 0`, una lista "⚠ {robot}: {motivo}"; si no, nada.

### Tipos (`resources/js/types/models.ts`)
- Extender `ProyeccionEnVivo` con:
  - `marcador: { robot: string | null; rounds: number }[]`.
  - `amonestaciones: { robot: string | null; motivo: string }[]`.

## Estrategia de pruebas (feature, PostgreSQL)

- `show` con un encuentro en vivo (2 participantes, sin ganador) que tiene 1 round ganado por el robot A y 1 amonestación al robot B: `enVivo.marcador` refleja A=1, B=0; `enVivo.amonestaciones` trae `{ robot: B, motivo }`.
- Round repetido no incrementa el marcador.
- Sin encuentro en vivo → `enVivo` null (no rompe).
- (Render es de cliente; verificación visual.)

## Fuera de alcance
Bracket (no cambia), cronómetro (ya hecho), historial de rounds detallado, animaciones.

## Criterios de aceptación (DoD)
1. `enVivo` expone `marcador` (rounds por robot, repetidos no cuentan) y `amonestaciones` (robot+motivo) del encuentro vigente (tests).
2. La vista Marcador muestra el marcador de rounds y la lista de amonestaciones bajo el "vs"; se refresca con el polling.
3. Sin encuentro en vivo, la proyección no rompe (test).
4. `php artisan test` 100%; `npm run build` sin errores; Pint limpio.
