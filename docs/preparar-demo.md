# Preparar la demo — RoboLeague

Guía para dejar el sistema listo y ejecutar el recorrido del flujo de trabajo durante la
defensa. Pensada para correr **en local**; al final está el **Plan B** con el despliegue
en producción.

---

## 1. Requisitos previos

- PHP 8.4, Composer
- PostgreSQL en marcha (conexión por socket Unix + rol *peer*; ver `.env`)
- Node.js + npm

---

## 2. Dejar la base de datos en cero y poblada

```bash
# desde la raíz del repo
php artisan migrate:fresh --seed
```

Esto crea el esquema (23 migraciones, triggers y vistas incluidos) y carga los catálogos
base mediante los seeders:

- **Instituciones** de ejemplo
- **8 categorías** reales — 6 de *Combate* (Mini/Micro/Nano Sumo) y 2 de *Tiempo*
  (Seguidor de Línea Amateur/Profesional)
- **Tarifas** paramétricas por fecha
- **Usuario administrador** (ver credenciales abajo)

> ⚠️ `migrate:fresh` **borra todos los datos**. Úsalo solo en local antes de la demo, nunca
> contra producción.

### Credenciales del administrador

| Campo | Valor |
|-------|-------|
| Correo | `admin@roboleague.test` |
| Contraseña | `password` |

> Si cargas el `DemoSeeder` (Estrategia B), también se crea una jueza:
> `juez@roboleague.test` / `password`.

---

## 3. Datos para la demo: dos estrategias

Los seeders **no** cargan robots, inscripciones ni encuentros (eso es justo lo que se
captura en vivo). Elige una:

### Estrategia A — Captura en vivo (la más vendedora)
Llega con la BD recién sembrada y construye el torneo frente al jurado siguiendo el guion
de la sección 5. Demuestra el producto funcionando de verdad.

### Estrategia B — Torneo pre-poblado (red de seguridad)
Si prefieres no depender de teclear en vivo, carga el `DemoSeeder`:

```bash
php artisan db:seed --class=DemoSeeder
```

Deja un escenario "a medio jugar" usando la lógica real de la app (`BracketService`):

- **14 robots** en total; **12 inspecciones aprobadas**
- Categoría de **Combate** («Mini Sumo Autónomo Amateur») con bracket de 8: **cuartos
  resueltos** (incluye un round repetido por empate y una amonestación) y **semifinales +
  final en juego**
- Categoría de **Tiempo** («Seguidor de Línea Amateur») con **3 intentos por robot**
- **1 robot pagado pero SIN aprobar** → para demostrar en vivo que el *trigger* lo rechaza
  al intentar meterlo a combate (paso 5 del guion)
- **1 inscripción pendiente de pago** → para mostrar el flujo de caja

> El `DemoSeeder` está **fuera** de `DatabaseSeeder` a propósito: ni `php artisan db:seed`
> ni `scripts/deploy.sh --seed` lo ejecutan. Es **solo para local**. Córrelo después de
> `migrate:fresh --seed` (necesita los catálogos base).

> **Recomendación:** prepara la Estrategia B como respaldo y ejecuta la A para el momento
> "wow". Si la captura en vivo se complica, ya tienes datos sobre los que seguir.

---

## 4. Levantar la aplicación

```bash
composer run dev
```

Arranca en paralelo (concurrently): **servidor** (`php artisan serve` →
http://localhost:8000), **cola**, **logs** (Pail) y **Vite/HMR**. Deja esta terminal
abierta durante toda la demo.

> Si la UI no refleja un cambio, recuerda que Vite/HMR debe estar corriendo (lo lanza
> `composer run dev`).

---

## 5. Guion del recorrido (showcase)

Sigue el mismo arco de la presentación. Ten dos pestañas listas: una con sesión de admin y
otra (o ventana de incógnito) con el **Modo Proyección** público.

| # | Pantalla | Qué hacer / mostrar | Punto que vende |
|---|----------|---------------------|-----------------|
| 1 | **Landing + Login** | Entrar con el admin | Tema "Eléctrico", auth localizada |
| 2 | **Dashboard** | Mostrar indicadores y accesos rápidos | Inertia renderiza según el rol |
| 3 | **Robots → Inscripción** | Registrar un robot, inscribirlo y **pagar** | Tarifa que cambia por fecha (caja dinámica) |
| 4 | **Inspección (checklist)** | Aprobar un robot; dejar otro **sin aprobar** | Prepara el momento clave del paso 5 |
| 5 | **Combate / Bracket** | Generar bracket e intentar meter al robot **no aprobado** | 🔑 **Momento wow:** la BD rechaza el INSERT vía *trigger* — la regla vive en los datos |
| 6 | **Combate · rounds** | Registrar el mejor de tres, un empate que no cuenta, una amonestación y la reparación pausable (300 s) | Reglas de negocio reales |
| 7 | **Tiempos** | En una categoría de *Tiempo*, registrar 3 intentos | Se toma el menor válido (`MIN`) |
| 8 | **Modo Proyección** | Abrir la pestaña pública y mostrar bracket/podio | Vista sin auth, refresco por polling |
| 9 | **Reportes / Caja** | Mostrar total recaudado | Cierra el círculo financiero |

### El gancho técnico (no te lo saltes)
El paso 5 es el corazón de la tesis: un robot **no aprobado** en la inspección **no puede**
entrar al bracket porque un *trigger* `BEFORE INSERT` lo bloquea en la base de datos, no
solo en la app. Es lo que diferencia "una app con validaciones" de "una base de datos que
es el primer filtro de seguridad". Combínalo con la slide nueva de **RBAC** (rol + trigger)
para contar la historia de *defensa en profundidad*.

---

## 6. Checklist pre-defensa

- [ ] `php artisan migrate:fresh --seed` ejecutado sin errores
- [ ] `composer run dev` corriendo (servidor, cola, logs, Vite)
- [ ] Login con `admin@roboleague.test` funciona
- [ ] Pestaña de **Proyección** abierta y lista
- [ ] (Opcional) `php artisan db:seed --class=DemoSeeder` cargado como respaldo
- [ ] Suite en verde: `php artisan test --compact` → **184 pruebas / 849 aserciones**
- [ ] Plan B a mano (sección 7) por si falla el internet del recinto

---

## 7. Plan B — Producción ya desplegada

El sistema está desplegado con **Docker Compose + Cloudflare Tunnel** mediante
`scripts/deploy.sh` (en el VPS):

- **URL pública:** https://roboleague.tlapala.com (también es el QR de cierre de la
  presentación)
- Redespliegue normal: `./scripts/deploy.sh` (build + migrate + cache)
- Solo el **primer** deploy usa `./scripts/deploy.sh --seed`, que corre `db:seed`
  (catálogos + admin). **Nunca** carga el `DemoSeeder`.

Si el entorno local falla, puedes hacer toda la demo contra producción. Ten presente que
los datos ahí son persistentes: **no** corras `migrate:fresh` ni el `DemoSeeder` contra ese
entorno.

---

## Notas

- La captura automática de tiempos por hardware (**ESP32**, Fase 3) está fuera del alcance
  de esta demo; hoy los tiempos los capturan los jueces por interfaz. La presentación ya lo
  refleja como pendiente.
- La pirámide de QA (slide 15) menciona E2E con Dusk/Playwright como capa **aspiracional**;
  las 184 pruebas reales son *feature* + integración + unitarias (PHPUnit). Tenlo claro por
  si preguntan.
