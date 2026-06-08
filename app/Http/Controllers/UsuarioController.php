<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('usuarios/index', [
            'usuarios' => User::orderBy('name')->get(['id', 'name', 'apellidos', 'email', 'telefono', 'rol']),
        ]);
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = User::create([...$data, 'password' => Hash::make($data['password'])]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return back()->with('success', 'Usuario creado.');
    }

    public function update(UpdateUsuarioRequest $request, User $usuario): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $usuario->update($data);

        return back()->with('success', 'Usuario actualizado.');
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->id === $request->user()->id) {
            return back()->withErrors(['usuario' => 'No puedes eliminar tu propia cuenta.']);
        }

        if ($usuario->robotsComoPiloto()->exists() || $usuario->inspecciones()->exists()) {
            return back()->withErrors(['usuario' => 'No se puede eliminar: el usuario tiene robots o inspecciones asociadas.']);
        }

        $usuario->delete();

        return back()->with('success', 'Usuario eliminado.');
    }
}
