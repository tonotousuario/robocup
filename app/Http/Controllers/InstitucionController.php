<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstitucionRequest;
use App\Models\Institucion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InstitucionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('instituciones/index', [
            'instituciones' => Institucion::orderBy('nombre')->get(),
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
