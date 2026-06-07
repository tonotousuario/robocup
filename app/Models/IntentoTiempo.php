<?php

namespace App\Models;

use Database\Factories\IntentoTiempoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_inscripcion', 'numero_vuelta', 'tiempo_logrado', 'penalizacion_segundos'])]
class IntentoTiempo extends Model
{
    /** @use HasFactory<IntentoTiempoFactory> */
    use HasFactory;

    protected $table = 'intentos_tiempos';

    protected $primaryKey = 'id_intento';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero_vuelta' => 'integer',
            'tiempo_logrado' => 'decimal:3',
            'penalizacion_segundos' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<Inscripcion, $this> */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'id_inscripcion', 'id_inscripcion');
    }
}
