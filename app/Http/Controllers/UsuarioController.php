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
    public function index(Request $request): Response
    {
        $sortables = ['name', 'email', 'rol'];
        $sort = in_array($request->query('sort'), $sortables, true) ? $request->query('sort') : 'name';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $usuarios = User::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->string('q')->toString();
                $query->where(fn ($w) => $w
                    ->where('name', 'ilike', "%{$q}%")
                    ->orWhere('apellidos', 'ilike', "%{$q}%")
                    ->orWhere('email', 'ilike', "%{$q}%"));
            })
            ->when($request->filled('rol'), fn ($query) => $query->where('rol', $request->string('rol')->toString()))
            ->orderBy($sort, $dir)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'apellidos' => $u->apellidos,
                'email' => $u->email,
                'telefono' => $u->telefono,
                'rol' => $u->rol,
            ]);

        return Inertia::render('usuarios/index', [
            'usuarios' => $usuarios,
            'filtros' => [
                'q' => $request->query('q', ''),
                'rol' => $request->query('rol', ''),
                'sort' => $sort,
                'dir' => $dir,
            ],
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
