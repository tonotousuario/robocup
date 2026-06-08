<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AmonestarRequest extends FormRequest
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
            'id_inscripcion' => ['required', 'integer'],
            'motivo' => ['required', 'string', 'max:1000'],
            'numero_round' => ['nullable', 'integer'],
        ];
    }
}
