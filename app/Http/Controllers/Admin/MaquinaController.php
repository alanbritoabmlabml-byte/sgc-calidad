<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaquinaController extends Controller
{
    public function index(): View
    {
        return view('admin.maquinas.index', [
            'maquinas' => Machine::with('sector')
                ->orderBy('sector_id')
                ->orderBy('tipo')
                ->orderBy('code')
                ->get()
                ->groupBy('tipo'),
        ]);
    }

    public function create(): View
    {
        return view('admin.maquinas.form', [
            'maquina' => new Machine(['active' => true, 'tipo' => 'telar']),
            'sectores' => Sector::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Machine::create($this->validar($request));

        return redirect()->route('admin.maquinas.index')->with('ok', 'Maquina creada.');
    }

    public function edit(Machine $maquina): View
    {
        return view('admin.maquinas.form', [
            'maquina' => $maquina,
            'sectores' => Sector::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Machine $maquina): RedirectResponse
    {
        $maquina->update($this->validar($request, $maquina));

        return redirect()->route('admin.maquinas.index')->with('ok', "Maquina {$maquina->code} actualizada.");
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Machine $maquina = null): array
    {
        $datos = $request->validate([
            'sector_id' => ['required', 'exists:sectors,id'],
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('machines', 'code')
                    ->where('sector_id', $request->integer('sector_id'))
                    ->ignore($maquina?->id),
            ],
            'name' => ['nullable', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(Machine::TIPOS)],
            'active' => ['nullable', 'boolean'],
        ], [
            'code.unique' => 'Ya existe una maquina con ese codigo en el sector.',
        ], [
            'sector_id' => 'sector',
            'code' => 'codigo',
            'name' => 'nombre',
        ]);

        $datos['active'] = $request->boolean('active');

        return $datos;
    }
}
