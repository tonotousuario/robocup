<?php

namespace App\Http\Controllers;

use App\Http\Requests\RobotRequest;
use App\Models\Categoria;
use App\Models\Institucion;
use App\Models\Robot;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RobotController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Robot::class);

        $user = $request->user();

        $query = Robot::with(['piloto', 'institucion', 'categoria'])->orderBy('nombre');

        if (! $user->isAdministrador()) {
            $query->where('id_piloto', $user->id);
        }

        $robots = $query->get()->map(fn (Robot $robot) => [
            'id_robot' => $robot->id_robot,
            'nombre' => $robot->nombre,
            'categoria' => $robot->categoria?->nombre,
            'institucion' => $robot->institucion?->nombre,
            'piloto' => $robot->piloto ? $robot->piloto->name.' '.$robot->piloto->apellidos : null,
            'id_piloto' => $robot->id_piloto,
        ])->values();

        return Inertia::render('robots/index', [
            'robots' => $robots,
            'categorias' => Categoria::orderBy('nombre')->get(['id_categoria', 'nombre']),
            'instituciones' => Institucion::orderBy('nombre')->get(['id_institucion', 'nombre']),
            'pilotos' => $user->isAdministrador()
                ? User::where('rol', 'Piloto')->orderBy('name')->get(['id', 'name', 'apellidos'])
                    ->map(fn (User $p) => ['id' => $p->id, 'nombre' => $p->name.' '.$p->apellidos])->values()
                : [],
        ]);
    }

    public function store(RobotRequest $request): RedirectResponse
    {
        $this->authorize('create', Robot::class);

        $data = $request->validated();

        if (! $request->user()->isAdministrador()) {
            $data['id_piloto'] = $request->user()->id;
        }

        Robot::create($data);

        return back()->with('success', 'Robot creado.');
    }

    public function update(RobotRequest $request, Robot $robot): RedirectResponse
    {
        $this->authorize('update', $robot);

        $data = $request->validated();

        if (! $request->user()->isAdministrador()) {
            $data['id_piloto'] = $request->user()->id;
        }

        $robot->update($data);

        return back()->with('success', 'Robot actualizado.');
    }

    public function destroy(Robot $robot): RedirectResponse
    {
        $this->authorize('delete', $robot);

        $robot->delete();

        return back()->with('success', 'Robot eliminado.');
    }
}
