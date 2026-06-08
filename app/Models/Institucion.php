<?php

namespace App\Models;

use Database\Factories\InstitucionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'tipo', 'estado'])]
class Institucion extends Model
{
    /** @use HasFactory<InstitucionFactory> */
    use HasFactory;

    protected $table = 'instituciones';

    protected $primaryKey = 'id_institucion';

    public $timestamps = false;

    public function getRouteKeyName(): string
    {
        return 'id_institucion';
    }

    /** @return HasMany<Robot, $this> */
    public function robots(): HasMany
    {
        return $this->hasMany(Robot::class, 'id_institucion', 'id_institucion');
    }
}
