<?php

namespace App\Services;

use App\Models\Tarifa;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class TarifaService
{
    public function vigenteParaHoy(): ?Tarifa
    {
        return $this->vigentePara(Carbon::now());
    }

    public function vigentePara(CarbonInterface $fecha): ?Tarifa
    {
        return Tarifa::whereDate('fecha_inicio_cobro', '<=', $fecha)
            ->whereDate('fecha_fin_cobro', '>=', $fecha)
            ->orderBy('fecha_inicio_cobro')
            ->first();
    }
}
