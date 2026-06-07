<?php

namespace App\Models;

use Database\Factories\InspeccionChecklistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_inscripcion', 'id_juez', 'peso_medido_g', 'dimensiones_medidas', 'estado_aprobacion', 'observaciones'])]
class InspeccionChecklist extends Model
{
    /** @use HasFactory<InspeccionChecklistFactory> */
    use HasFactory;

    protected $table = 'inspecciones_checklist';

    protected $primaryKey = 'id_inspeccion';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'peso_medido_g' => 'integer',
            'fecha_inspeccion' => 'datetime',
        ];
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
