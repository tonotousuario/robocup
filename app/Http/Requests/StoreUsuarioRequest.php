<?php

namespace App\Http\Requests;

use App\Enums\RolUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'rol' => ['required', Rule::enum(RolUsuario::class)],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
