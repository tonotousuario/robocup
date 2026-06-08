<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_encuentro', 'id_inscripcion', 'id_juez', 'numero_round', 'motivo'])]
class Amonestacion extends Model
{
    protected $table = 'amonestaciones';

    protected $primaryKey = 'id_amonestacion';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero_round' => 'integer',
            'fecha' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function juez(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_juez', 'id');
    }
}
