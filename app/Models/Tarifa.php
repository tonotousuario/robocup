<?php

namespace App\Models;

use Database\Factories\TarifaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['descripcion', 'fecha_inicio_cobro', 'fecha_fin_cobro', 'monto'])]
class Tarifa extends Model
{
    /** @use HasFactory<TarifaFactory> */
    use HasFactory;

    protected $table = 'tarifas';

    protected $primaryKey = 'id_tarifa';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio_cobro' => 'date',
            'fecha_fin_cobro' => 'date',
            'monto' => 'decimal:2',
        ];
    }
}
