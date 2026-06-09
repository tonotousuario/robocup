<?php

namespace App\Models;

use Database\Factories\InscripcionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id_robot', 'id_tarifa', 'monto_pagado', 'estado_pago', 'reparacion_segundos_consumidos', 'reparacion_iniciada_en'])]
class Inscripcion extends Model
{
    public const REPARACION_SEGUNDOS = 300;

    /** @use HasFactory<InscripcionFactory> */
    use HasFactory;

    protected $table = 'inscripciones';

    protected $primaryKey = 'id_inscripcion';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_registro' => 'datetime',
            'monto_pagado' => 'decimal:2',
            'reparacion_segundos_consumidos' => 'integer',
            'reparacion_iniciada_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Robot, $this> */
    public function robot(): BelongsTo
    {
        return $this->belongsTo(Robot::class, 'id_robot', 'id_robot');
    }

    /** @return BelongsTo<Tarifa, $this> */
    public function tarifa(): BelongsTo
    {
        return $this->belongsTo(Tarifa::class, 'id_tarifa', 'id_tarifa');
    }

    /** @return HasMany<InspeccionChecklist, $this> */
    public function inspecciones(): HasMany
    {
        return $this->hasMany(InspeccionChecklist::class, 'id_inscripcion', 'id_inscripcion');
    }

    /** @return HasMany<IntentoTiempo, $this> */
    public function intentos(): HasMany
    {
        return $this->hasMany(IntentoTiempo::class, 'id_inscripcion', 'id_inscripcion');
    }

    public function reparacionRestante(): int
    {
        $transcurrido = $this->reparacion_iniciada_en !== null
            ? (int) now()->diffInSeconds($this->reparacion_iniciada_en, true)
            : 0;

        return max(0, self::REPARACION_SEGUNDOS - $this->reparacion_segundos_consumidos - $transcurrido);
    }
}
