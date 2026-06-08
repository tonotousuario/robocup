<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RobotRequest extends FormRequest
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
        $rules = [
            'nombre' => ['required', 'string', 'max:255'],
            'id_categoria' => ['required', 'integer', 'exists:categorias,id_categoria'],
            'id_institucion' => ['nullable', 'integer', 'exists:instituciones,id_institucion'],
        ];

        if ($this->user()->isAdministrador()) {
            $rules['id_piloto'] = ['required', 'integer', Rule::exists('users', 'id')->where('rol', 'Piloto')];
        } else {
            $rules['id_piloto'] = ['nullable'];
        }

        return $rules;
    }
}
