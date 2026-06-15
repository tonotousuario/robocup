<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\InspeccionChecklist;
use App\Models\Institucion;
use App\Models\IntentoTiempo;
use App\Models\Robot;
use App\Models\Tarifa;
use App\Models\User;
use App\Services\BracketService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de demostración para la defensa: deja un torneo "a medio jugar".
 *
 * NO se ejecuta en producción: está deliberadamente FUERA de DatabaseSeeder,
 * por lo que `php artisan db:seed` (y `deploy.sh --seed`) jamás lo invocan.
 * Córrelo a mano solo en local:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * Requiere los catálogos base (Categorías, Tarifas, Instituciones). Si partiste
 * de `migrate:fresh --seed` ya están; si no, corre primero `db:seed`.
 */
class DemoSeeder extends Seeder
{
    public function __construct(private BracketService $bracket) {}

    public function run(): void
    {
        $tarifa = Tarifa::query()->firstOr(fn () => Tarifa::factory()->create());
        $instituciones = Institucion::query()->take(3)->get();
        if ($instituciones->isEmpty()) {
            $instituciones = Institucion::factory()->count(3)->create();
        }

        $juez = User::firstOrCreate(
            ['email' => 'juez@roboleague.test'],
            [
                'name' => 'Jueza',
                'apellidos' => 'Pista',
                'rol' => 'Juez',
                'telefono' => fake()->phoneNumber(),
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        $combate = Categoria::where('tipo_evaluacion', 'Combate')->firstOrFail();
        $tiempo = Categoria::where('tipo_evaluacion', 'Tiempo')->firstOrFail();

        $this->sembrarCombate($combate, $tarifa, $instituciones, $juez);
        $this->sembrarTiempo($tiempo, $tarifa, $instituciones, $juez);

        $this->command?->info('✓ DemoSeeder: torneo de demostración cargado.');
        $this->command?->line("  Combate: «{$combate->nombre}» con bracket en progreso.");
        $this->command?->line("  Tiempo:  «{$tiempo->nombre}» con 3 intentos por robot.");
        $this->command?->line('  1 robot Pagado SIN aprobar (para demostrar el rechazo del trigger).');
        $this->command?->line('  1 inscripción Pendiente de pago (para demostrar el flujo de caja).');
    }

    /**
     * 8 robots aprobados → bracket generado y cuartos resueltos (semifinales en juego).
     * + 1 robot pagado pero NO aprobado (lo bloquea el trigger al intentar competir).
     * + 1 inscripción pendiente de pago.
     */
    private function sembrarCombate(Categoria $categoria, Tarifa $tarifa, $instituciones, User $juez): void
    {
        $aprobados = collect(range(1, 8))->map(
            fn (int $i) => $this->crearCompetidor($categoria, $tarifa, $instituciones, $juez, aprobado: true)
        );

        // Robot pagado pero con inspección Pendiente: candidato del "momento wow".
        $this->crearCompetidor($categoria, $tarifa, $instituciones, $juez, aprobado: false, inspeccionar: true);

        // Inscripción aún sin pagar (sin inspección): muestra el flujo de caja.
        $this->crearCompetidor($categoria, $tarifa, $instituciones, $juez, aprobado: false, pagada: false);

        // Genera el árbol desde la final usando la lógica real de la app.
        $this->bracket->generar($categoria);

        // Resuelve la primera ronda real (mejor de tres) para dejar las semifinales en juego.
        $primeraRonda = Encuentro::where('id_categoria', $categoria->id_categoria)
            ->whereIn('ronda', ['Cuartos', 'Octavos'])
            ->get();

        $amonestada = false;
        foreach ($primeraRonda as $encuentro) {
            $participantes = $encuentro->participantes()->get();
            if ($participantes->count() < 2) {
                continue; // bye ya auto-avanzado
            }

            $ganador = (int) $participantes->first()->id_inscripcion;

            // Un duelo con round repetido por empate (no contabiliza) para enriquecer la bitácora.
            if (! $amonestada) {
                $this->bracket->registrarRound($encuentro, null, repetido: true);
                $this->bracket->amonestar(
                    $encuentro,
                    (int) $participantes->last()->id_inscripcion,
                    'Salir del dohyo',
                    $juez->id,
                    numeroRound: 1,
                );
                $amonestada = true;
            }

            // Mejor de tres: dos victorias deciden el encuentro y avanzan al ganador.
            $this->bracket->registrarRound($encuentro, $ganador);
            $this->bracket->registrarRound($encuentro, $ganador);
        }
    }

    /**
     * 4 robots aprobados con 3 intentos cronometrados cada uno (uno es el mejor).
     */
    private function sembrarTiempo(Categoria $categoria, Tarifa $tarifa, $instituciones, User $juez): void
    {
        collect(range(1, 4))->each(function () use ($categoria, $tarifa, $instituciones, $juez) {
            $inscripcion = $this->crearCompetidor($categoria, $tarifa, $instituciones, $juez, aprobado: true);

            $tiempos = collect([
                fake()->randomFloat(3, 11, 13),
                fake()->randomFloat(3, 11, 13),
                fake()->randomFloat(3, 11, 13),
            ]);

            $tiempos->each(fn (float $t, int $i) => IntentoTiempo::factory()->create([
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'numero_vuelta' => $i + 1,
                'tiempo_logrado' => $t,
                'penalizacion_segundos' => 0,
            ]));
        });
    }

    /**
     * Crea piloto + robot + inscripción y, opcionalmente, su inspección.
     */
    private function crearCompetidor(
        Categoria $categoria,
        Tarifa $tarifa,
        $instituciones,
        User $juez,
        bool $aprobado,
        bool $pagada = true,
        bool $inspeccionar = false,
    ): Inscripcion {
        $piloto = User::factory()->create();

        $robot = Robot::factory()->create([
            'id_piloto' => $piloto->id,
            'id_categoria' => $categoria->id_categoria,
            'id_institucion' => $instituciones->random()->id_institucion,
        ]);

        $factory = Inscripcion::factory()->state([
            'id_robot' => $robot->id_robot,
            'id_tarifa' => $tarifa->id_tarifa,
        ]);

        $inscripcion = ($pagada ? $factory->pagada() : $factory)->create();

        // La inspección requiere pago (trigger T1); solo se crea si está pagada.
        if ($pagada && ($aprobado || $inspeccionar)) {
            $estado = InspeccionChecklist::factory()->state([
                'id_inscripcion' => $inscripcion->id_inscripcion,
                'id_juez' => $juez->id,
            ]);

            ($aprobado ? $estado->aprobado() : $estado)->create();
        }

        return $inscripcion;
    }
}
