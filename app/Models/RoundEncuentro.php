<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_encuentro', 'numero_round', 'id_inscripcion_ganador', 'repetido'])]
class RoundEncuentro extends Model
{
    protected $table = 'rounds_encuentro';

    protected $primaryKey = 'id_round';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero_round' => 'integer',
            'repetido' => 'boolean',
            'fecha' => 'datetime',
        ];
    }

    /** @return BelongsTo<Encuentro, $this> */
    public function encuentro(): BelongsTo
    {
        return $this->belongsTo(Encuentro::class, 'id_encuentro', 'id_encuentro');
    }

    /** @return BelongsTo<Inscripcion, $this> */
    public function ganador(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'id_inscripcion_ganador', 'id_inscripcion');
    }
}
