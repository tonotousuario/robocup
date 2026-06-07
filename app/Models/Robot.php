<?php

namespace App\Models;

use Database\Factories\RobotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'id_piloto', 'id_institucion', 'id_categoria'])]
class Robot extends Model
{
    /** @use HasFactory<RobotFactory> */
    use HasFactory;

    protected $table = 'robots';

    protected $primaryKey = 'id_robot';

    public $timestamps = false;

    /** @return BelongsTo<User, $this> */
    public function piloto(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_piloto', 'id');
    }

    /** @return BelongsTo<Institucion, $this> */
    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'id_institucion', 'id_institucion');
    }

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    /** @return HasMany<Inscripcion, $this> */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'id_robot', 'id_robot');
    }
}
