<?php

namespace App\Models;

use Database\Factories\ParticipanteEncuentroFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_encuentro', 'id_inscripcion', 'puntos_obtenidos', 'es_ganador'])]
class ParticipanteEncuentro extends Model
{
    /** @use HasFactory<ParticipanteEncuentroFactory> */
    use HasFactory;

    protected $table = 'participantes_encuentro';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'puntos_obtenidos' => 'integer',
            'es_ganador' => 'boolean',
        ];
    }

    /** @return BelongsTo<Encuentro, $this> */
    public function encuentro(): BelongsTo
    {
        return $this->belongsTo(Encuentro::class, 'id_encuentro', 'id_encuentro');
    }

    /** @return BelongsTo<Inscripcion, $this> */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'id_inscripcion', 'id_inscripcion');
    }
}
