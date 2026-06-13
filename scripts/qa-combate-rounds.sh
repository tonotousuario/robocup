#!/usr/bin/env bash
# =============================================================
# QA — Combate por rounds + amonestaciones (RoboLeague)
# Rama: feature/roboleague-combate-rounds
#
# Uso:
#   bash scripts/qa-combate-rounds.sh          # prepara datos y muestra guía
#   bash scripts/qa-combate-rounds.sh --tests  # además corre las pruebas
#
# Requiere: PostgreSQL corriendo (roboleague), PHP, composer/npm instalados.
# Deja la BD recreada y sembrada, lista para QA manual en el navegador.
# =============================================================
set -euo pipefail
cd "$(dirname "$0")/.."

echo "==> Rama actual: $(git branch --show-current)"

# --- (opcional) Pruebas automatizadas ---
if [[ "${1:-}" == "--tests" ]]; then
  echo "==> Corriendo pruebas de la feature..."
  php artisan test --filter='CombateRounds|Bracket|Encuentro|Seeders'
fi

# --- Recrear BD + catálogos base (8 categorías, tarifas, instituciones, admin) ---
echo "==> migrate:fresh --seed"
php artisan migrate:fresh --seed

# --- Sembrar escenario de combate: juez + 4 pilotos/robots aprobados + bracket ---
echo "==> Sembrando escenario de combate (Mini Sumo Autónomo Profesional)"
php artisan tinker --execute '
$cat = App\Models\Categoria::where("nombre", "Mini Sumo Autónomo Profesional")->first();

App\Models\User::updateOrCreate(
    ["email" => "juez@roboleague.test"],
    ["name" => "Juez", "apellidos" => "Dohyo", "rol" => "Juez", "password" => bcrypt("password"), "email_verified_at" => now()]
);

$t = App\Models\Tarifa::first();

foreach (["Tanque", "Martillo", "Sierra", "Pinza"] as $n) {
    $p = App\Models\User::create([
        "name" => $n, "apellidos" => "Piloto", "email" => strtolower($n)."@rl.test",
        "rol" => "Piloto", "password" => bcrypt("password"), "email_verified_at" => now(),
    ]);
    $r = App\Models\Robot::create(["nombre" => $n, "id_piloto" => $p->id, "id_categoria" => $cat->id_categoria]);
    $i = App\Models\Inscripcion::create([
        "id_robot" => $r->id_robot, "id_tarifa" => $t->id_tarifa,
        "monto_pagado" => $t->monto, "estado_pago" => "Pagado",
    ]);
    App\Models\InspeccionChecklist::create([
        "id_inscripcion" => $i->id_inscripcion, "id_juez" => 1, "peso_medido_g" => 480,
        "dimensiones_medidas" => "10x10", "estado_aprobacion" => "Aprobado",
    ]);
}

app(App\Services\BracketService::class)->generar($cat);
echo "Escenario listo: bracket generado con 4 robots aprobados.\n";
'

cat <<'GUIA'

==============================================================
 LISTO. Ahora levanta la app:

   composer run dev      # o: php artisan serve  +  npm run dev

 Abre http://localhost:8000 y entra con:
   - Juez : juez@roboleague.test  / password
   - Admin: admin@roboleague.test / password
   - Pilotos: tanque@rl.test, martillo@rl.test, ... / password

 QA (menú "Combate" → categoría "Mini Sumo Autónomo Profesional"):
   1. Mejor de 3: "Gana round {robot}" 2 veces → decide y avanza a la Final.
   2. Repetir round: marca 1-1, pulsa "Repetir round" → no cambia el marcador.
   3. Amonestar: elige robot + motivo → aparece en la lista ⚠ (no cambia resultado).
   4. Default / Descalificar: deciden al instante y avanzan.
   5. Reparación {robot}: queda deshabilitado tras usarse (una sola vez).
   6. Roles: Coach/Piloto ven el bracket SIN panel de acciones (solo lectura).
==============================================================
GUIA
