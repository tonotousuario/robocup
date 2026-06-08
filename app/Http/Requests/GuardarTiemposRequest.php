<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarTiemposRequest extends FormRequest
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
            'intentos' => ['required', 'array', 'max:3'],
            'intentos.*.numero_vuelta' => ['required', 'integer', 'in:1,2,3'],
            'intentos.*.tiempo_logrado' => ['nullable', 'numeric', 'gt:0'],
            'intentos.*.penalizacion_segundos' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
