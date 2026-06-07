<?php

namespace App\Models;

use Database\Factories\EncuentroFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id_categoria', 'ronda', 'id_encuentro_siguiente'])]
class Encuentro extends Model
{
    /** @use HasFactory<EncuentroFactory> */
    use HasFactory;

    protected $table = 'encuentros';

    protected $primaryKey = 'id_encuentro';

    public $timestamps = false;

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    /** @return BelongsTo<Encuentro, $this> */
    public function siguiente(): BelongsTo
    {
        return $this->belongsTo(Encuentro::class, 'id_encuentro_siguiente', 'id_encuentro');
    }

    /** @return HasMany<Encuentro, $this> */
    public function anteriores(): HasMany
    {
        return $this->hasMany(Encuentro::class, 'id_encuentro_siguiente', 'id_encuentro');
    }

    /** @return HasMany<ParticipanteEncuentro, $this> */
    public function participantes(): HasMany
    {
        return $this->hasMany(ParticipanteEncuentro::class, 'id_encuentro', 'id_encuentro');
    }
}
