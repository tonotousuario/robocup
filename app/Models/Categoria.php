<?php

namespace App\Models;

use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'tipo_evaluacion', 'peso_maximo_g', 'dimensiones_maximas'])]
class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use HasFactory;

    protected $table = 'categorias';

    protected $primaryKey = 'id_categoria';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['peso_maximo_g' => 'integer'];
    }

    /** @return HasMany<Robot, $this> */
    public function robots(): HasMany
    {
        return $this->hasMany(Robot::class, 'id_categoria', 'id_categoria');
    }

    /** @return HasMany<Encuentro, $this> */
    public function encuentros(): HasMany
    {
        return $this->hasMany(Encuentro::class, 'id_categoria', 'id_categoria');
    }
}
