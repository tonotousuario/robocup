<?php

namespace App\Services;

use App\Models\Amonestacion;
use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\ParticipanteEncuentro;
use App\Models\RoundEncuentro;
use Illuminate\Support\Facades\DB;

class BracketService
{
    public function generar(Categoria $categoria): void
    {
        if ($categoria->tipo_evaluacion !== 'Combate') {
            throw new \InvalidArgumentException('La categoría no es de combate.');
        }

        DB::transaction(function () use ($categoria) {
            Encuentro::where('id_categoria', $categoria->id_categoria)->delete();

            $inscripciones = Inscripcion::whereHas('robot', fn ($q) => $q->where('id_categoria', $categoria->id_categoria))
                ->whereHas('inspecciones', fn ($q) => $q->where('estado_aprobacion', 'Aprobado'))
                ->pluck('id_inscripcion')
                ->shuffle()
                ->values();

            $n = $inscripciones->count();
            if ($n < 2) {
                throw new \DomainException('Se requieren al menos 2 robots aprobados.');
            }

            $size = 2 ** (int) ceil(log($n, 2));

            // Árbol desde la final hacia abajo.
            $final = Encuentro::create([
                'id_categoria' => $categoria->id_categoria,
                'ronda' => $this->nombreRonda(1),
                'id_encuentro_siguiente' => null,
            ]);

            $nivelActual = collect([$final]);
            $matchesNivel = 1;
            while ($matchesNivel < $size / 2) {
                $matchesNivel *= 2;
                $ronda = $this->nombreRonda($matchesNivel);
                $siguiente = collect();
                foreach ($nivelActual as $padre) {
                    for ($k = 0; $k < 2; $k++) {
                        $siguiente->push(Encuentro::create([
                            'id_categoria' => $categoria->id_categoria,
                            'ronda' => $ronda,
                            'id_encuentro_siguiente' => $padre->id_encuentro,
                        ]));
                    }
                }
                $nivelActual = $siguiente;
            }

            $ronda1 = $nivelActual->values(); // size/2 matches

            // Colocar competidores: primeros $byes matches reciben 1 (bye), el resto 2.
            $byes = $size - $n;
            $cursor = 0;
            foreach ($ronda1 as $index => $match) {
                $cuantos = $index < $byes ? 1 : 2;
                for ($s = 0; $s < $cuantos; $s++) {
                    ParticipanteEncuentro::create([
                        'id_encuentro' => $match->id_encuentro,
                        'id_inscripcion' => $inscripciones[$cursor],
                    ]);
                    $cursor++;
                }
            }

            // Encuentro por el 3er lugar (solo si hay semifinales).
            if ($size >= 4) {
                Encuentro::create([
                    'id_categoria' => $categoria->id_categoria,
                    'ronda' => 'Tercer lugar',
                    'id_encuentro_siguiente' => null,
                ]);
            }

            // Auto-avance de byes.
            foreach ($ronda1 as $index => $match) {
                if ($index < $byes) {
                    $idInscripcion = $match->participantes()->first()->id_inscripcion;
                    $this->registrarGanador($match, $idInscripcion);
                }
            }
        });
    }

    public function registrarGanador(Encuentro $encuentro, int $idInscripcion): void
    {
        ParticipanteEncuentro::where('id_encuentro', $encuentro->id_encuentro)
            ->where('id_inscripcion', $idInscripcion)
            ->update(['es_ganador' => true]);

        if ($encuentro->id_encuentro_siguiente !== null) {
            ParticipanteEncuentro::firstOrCreate([
                'id_encuentro' => $encuentro->id_encuentro_siguiente,
                'id_inscripcion' => $idInscripcion,
            ]);
        }

        if ($encuentro->ronda === 'Semifinal') {
            $tercerLugar = Encuentro::where('id_categoria', $encuentro->id_categoria)
                ->where('ronda', 'Tercer lugar')
                ->first();

            if ($tercerLugar !== null) {
                $idPerdedor = ParticipanteEncuentro::where('id_encuentro', $encuentro->id_encuentro)
                    ->where('id_inscripcion', '!=', $idInscripcion)
                    ->value('id_inscripcion');

                if ($idPerdedor !== null) {
                    ParticipanteEncuentro::firstOrCreate([
                        'id_encuentro' => $tercerLugar->id_encuentro,
                        'id_inscripcion' => $idPerdedor,
                    ]);
                }
            }
        }
    }

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

    private function nombreRonda(int $matches): string
    {
        return match ($matches) {
            1 => 'Final',
            2 => 'Semifinal',
            4 => 'Cuartos',
            8 => 'Octavos',
            16 => 'Dieciseisavos',
            default => 'Ronda de '.($matches * 2),
        };
    }
}
