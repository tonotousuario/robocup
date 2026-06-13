#!/usr/bin/env bash
# =============================================================
# RoboLeague — despliegue en el VPS con Docker Compose + Cloudflare Tunnel
#
#   ./scripts/deploy.sh            # build + migrate + cache (deploy normal)
#   ./scripts/deploy.sh --seed     # además corre los seeders (1er deploy: catálogos)
#
# Requisitos en el VPS: docker + docker compose, y un .env.production lleno
# (cópialo de .env.production.example).
# =============================================================
set -euo pipefail

cd "$(dirname "$0")/.."                       # raíz del repo
ENV_FILE=".env.production"
COMPOSE="docker compose --env-file ${ENV_FILE} --profile tunnel"
SEED=false
[[ "${1:-}" == "--seed" ]] && SEED=true

# ---- 0. Verificaciones ----
if ! docker compose version &>/dev/null; then
  echo "✗ Falta Docker Compose v2 (docker compose). Instálalo en el VPS." >&2; exit 1
fi
if [[ ! -f "$ENV_FILE" ]]; then
  echo "✗ No existe $ENV_FILE." >&2
  echo "  Crea uno:  cp .env.production.example $ENV_FILE  y rellena DB_PASSWORD y CLOUDFLARE_TUNNEL_TOKEN." >&2
  exit 1
fi

echo "▶ 1/5  Construyendo imágenes…"
$COMPOSE build

# ---- 2. APP_KEY (genera si está vacío) ----
if grep -qE '^APP_KEY=$' "$ENV_FILE" || ! grep -q '^APP_KEY=base64:' "$ENV_FILE"; then
  echo "▶ 2/5  Generando APP_KEY…"
  KEY=$($COMPOSE run --rm -T app php artisan key:generate --show | tr -d '\r' | grep '^base64:')
  # delimitador '#' para no chocar con '/' del base64
  sed -i "s#^APP_KEY=.*#APP_KEY=${KEY}#" "$ENV_FILE"
  echo "   APP_KEY escrita en $ENV_FILE"
else
  echo "▶ 2/5  APP_KEY ya existe (ok)"
fi

echo "▶ 3/5  Levantando servicios…"
$COMPOSE up -d --remove-orphans

# esperar a que 'app' esté arriba
echo "   esperando a app/db…"
for i in $(seq 1 30); do
  if $COMPOSE exec -T app php -v &>/dev/null; then break; fi
  sleep 2
done

echo "▶ 4/5  Migraciones (y triggers)…"
$COMPOSE exec -T app php artisan migrate --force
if $SEED; then
  echo "        Seeders (catálogos: Tarifas, Categorías, Instituciones)…"
  $COMPOSE exec -T app php artisan db:seed --force
fi

echo "▶ 5/5  Optimización (config/route/view/event cache)…"
$COMPOSE exec -T app php artisan storage:link || true
$COMPOSE exec -T app php artisan optimize

echo ""
echo "✓ Despliegue listo."
$COMPOSE ps
echo ""
echo "  Prueba local en el VPS:   curl -I http://localhost:8080"
echo "  Público (vía túnel):      https://roboleague.tlapala.com"
echo "  Logs del túnel:           $COMPOSE logs -f cloudflared"
