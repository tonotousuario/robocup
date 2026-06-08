<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Encuentro;
use App\Models\Inscripcion;
use App\Models\ParticipanteEncuentro;
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
