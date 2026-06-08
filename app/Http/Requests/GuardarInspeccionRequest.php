<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarInspeccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_inscripcion' => ['required', 'integer', 'exists:inscripciones,id_inscripcion'],
            'peso_medido_g' => ['required', 'integer', 'min:0'],
            'dimensiones_medidas' => ['required', 'string', 'max:255'],
            'estado_aprobacion' => ['required', 'in:Aprobado,Rechazado,Descalificado'],
            'observaciones' => ['nullable', 'string'],
        ];
    }
}
