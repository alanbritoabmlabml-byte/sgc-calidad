<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SectorController extends Controller
{
    public function index(): View
    {
        return view('admin.sectores.index', [
            'sectores' => Sector::with('processes')->withCount('lots')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.sectores.form', ['sector' => new Sector(['active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sector = Sector::create($this->validar($request));

        return redirect()
            ->route('admin.sectores.index')
            ->with('ok', "Sector {$sector->name} creado. Ahora cargale sus procesos y plantillas de ensayo.");
    }

    public function edit(Sector $sector): View
    {
        return view('admin.sectores.form', ['sector' => $sector->load('processes')]);
    }

    public function update(Request $request, Sector $sector): RedirectResponse
    {
        $sector->update($this->validar($request, $sector));

        return redirect()->route('admin.sectores.index')->with('ok', "Sector {$sector->name} actualizado.");
    }

    /**
     * Alta rapida desde el formulario de lote, sin cambiar de pantalla.
     * Devuelve el sector en JSON para que la pantalla lo agregue al desplegable.
     */
    public function rapido(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('sectors', 'code')],
            'prefijo_lote' => ['nullable', 'string', 'max:8', 'regex:/^[A-Za-z0-9]+$/'],
        ], [
            'prefijo_lote.regex' => 'El prefijo solo admite letras y numeros.',
        ], [
            'name' => 'nombre',
            'prefijo_lote' => 'prefijo de lote',
        ]);

        // Si no se indican, el codigo y el prefijo se derivan del nombre.
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $datos['name']));

        $sector = Sector::create([
            'name' => $datos['name'],
            'code' => $datos['code'] ?: $this->codigoLibre($base),
            'prefijo_lote' => strtoupper($datos['prefijo_lote'] ?? '') ?: mb_substr($base, 0, 3),
            'active' => true,
        ]);

        return response()->json([
            'ok' => true,
            'sector' => ['id' => $sector->id, 'name' => $sector->name],
            'mensaje' => "Sector {$sector->name} creado. Cargale sus procesos y plantillas de ensayo "
                .'desde Configuracion antes de poder inspeccionar lotes.',
        ], 201);
    }

    /** Busca un codigo libre a partir de una base, agregando un sufijo si hace falta. */
    private function codigoLibre(string $base): string
    {
        $base = mb_substr($base, 0, 16) ?: 'SECTOR';
        $codigo = $base;
        $n = 2;

        while (Sector::where('code', $codigo)->exists()) {
            $codigo = $base.$n++;
        }

        return $codigo;
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Sector $sector = null): array
    {
        $datos = $request->validate([
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('sectors', 'code')->ignore($sector?->id),
            ],
            'name' => ['required', 'string', 'max:80'],
            'prefijo_lote' => ['required', 'string', 'max:8', 'regex:/^[A-Za-z0-9]+$/'],
            'active' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'El codigo solo admite letras, numeros, guion y guion bajo.',
            'code.unique' => 'Ya existe un sector con ese codigo.',
            'prefijo_lote.regex' => 'El prefijo solo admite letras y numeros.',
        ], [
            'code' => 'codigo',
            'name' => 'nombre',
            'prefijo_lote' => 'prefijo de lote',
        ]);

        $datos['code'] = strtoupper($datos['code']);
        $datos['prefijo_lote'] = strtoupper($datos['prefijo_lote']);
        $datos['active'] = $request->boolean('active');

        return $datos;
    }
}
