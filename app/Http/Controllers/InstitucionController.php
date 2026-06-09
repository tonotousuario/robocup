<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstitucionRequest;
use App\Models\Institucion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InstitucionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Institucion::orderBy('nombre');

        if ($request->filled('q')) {
            $query->where('nombre', 'ilike', '%'.$request->string('q')->toString().'%');
        }

        $ordenables = ['nombre'];
        $sort = in_array($request->query('sort'), $ordenables, true) ? $request->query('sort') : 'nombre';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        $query->reorder($sort, $dir);

        $instituciones = $query->paginate(15)->withQueryString()->through(fn (Institucion $institucion) => [
            'id_institucion' => $institucion->id_institucion,
            'nombre' => $institucion->nombre,
            'tipo' => $institucion->tipo,
            'estado' => $institucion->estado,
        ]);

        return Inertia::render('instituciones/index', [
            'instituciones' => $instituciones,
            'filtros' => [
                'q' => $request->query('q', ''),
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    public function store(InstitucionRequest $request): RedirectResponse
    {
        Institucion::create($request->validated());

        return back()->with('success', 'Institución creada.');
    }

    public function update(InstitucionRequest $request, Institucion $institucion): RedirectResponse
    {
        $institucion->update($request->validated());

        return back()->with('success', 'Institución actualizada.');
    }

    public function destroy(Institucion $institucion): RedirectResponse
    {
        $institucion->delete();

        return back()->with('success', 'Institución eliminada.');
    }
}
