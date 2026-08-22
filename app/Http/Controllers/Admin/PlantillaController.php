<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Process;
use App\Models\TestTemplate;
use App\Support\Permisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlantillaController extends Controller
{
    public function index(): View
    {
        return view('admin.plantillas.index', [
            'procesos' => Process::with(['sector', 'templates.parameters', 'templates.creador'])
                ->orderBy('sector_id')
                ->orderBy('orden')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.plantillas.create', [
            'procesos' => Process::with('sector')->orderBy('sector_id')->orderBy('orden')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $plantilla = TestTemplate::create([
            ...$datos,
            'active' => false,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.plantillas.show', $plantilla)
            ->with('ok', 'Plantilla creada. Agregale los parametros de ensayo y despues activala.');
    }

    public function show(Request $request, TestTemplate $plantilla): View
    {
        $plantilla->load(['process.sector', 'parameters.template', 'creador', 'activadaPor']);

        return view('admin.plantillas.show', [
            'plantilla' => $plantilla,
            'usos' => $plantilla->inspections()->count(),
            // Calidad puede crear pero no modificar: la vista se adapta al permiso.
            'puedeEditar' => $request->user()->puede(Permisos::PLANTILLAS_EDITAR),
            'puedeEliminar' => $request->user()->puede(Permisos::PLANTILLAS_ELIMINAR),
            'puedeCrear' => $request->user()->puede(Permisos::PLANTILLAS_CREAR),
            'puedeActivar' => $request->user()->puede(Permisos::PLANTILLAS_ACTIVAR),
            'atributosProducto' => [
                '' => 'No (valor fijo de la plantilla)',
                'ancho_nominal' => 'Ancho nominal del producto',
                'largo_nominal' => 'Largo nominal del producto',
                'gramaje_nominal' => 'Gramaje nominal del producto',
                'denier_nominal' => 'Denier nominal del producto',
                'peso_nominal' => 'Peso nominal del producto',
            ],
        ]);
    }

    public function update(Request $request, TestTemplate $plantilla): RedirectResponse
    {
        $plantilla->update($this->validar($request, $plantilla));

        return back()->with('ok', 'Plantilla actualizada.');
    }

    public function activar(Request $request, TestTemplate $plantilla): RedirectResponse
    {
        if ($plantilla->parameters()->count() === 0) {
            return back()->with('error', 'No se puede activar una plantilla sin parametros de ensayo.');
        }

        $plantilla->activar($request->user());

        return back()->with('ok',
            "Plantilla {$plantilla->nombre_completo} activada para {$plantilla->process->name}. ".
            'Las boletas ya emitidas conservan la revision con la que se llenaron.'
        );
    }

    /**
     * Crea una revision nueva copiando los parametros. Es el camino correcto
     * para cambiar una especificacion: las boletas ya emitidas siguen apuntando
     * a la revision con la que se llenaron.
     */
    public function duplicar(Request $request, TestTemplate $plantilla): RedirectResponse
    {
        $copia = DB::transaction(function () use ($plantilla, $request) {
            $siguiente = TestTemplate::where('process_id', $plantilla->process_id)->count() + 1;

            $copia = TestTemplate::create([
                'process_id' => $plantilla->process_id,
                'name' => $plantilla->name,
                'revision' => (string) $siguiente,
                'muestras_default' => $plantilla->muestras_default,
                'muestras_max' => $plantilla->muestras_max,
                'active' => false,
                'created_by' => $request->user()->id,
            ]);

            foreach ($plantilla->parameters as $parametro) {
                $nuevo = $parametro->replicate(['test_template_id', 'created_by']);
                $nuevo->test_template_id = $copia->id;
                $nuevo->created_by = $request->user()->id;
                $nuevo->save();
            }

            return $copia;
        });

        return redirect()
            ->route('admin.plantillas.show', $copia)
            ->with('ok', "Se creo la revision {$copia->revision}. Ajustala y activala cuando este lista.");
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?TestTemplate $plantilla = null): array
    {
        return $request->validate([
            'process_id' => ['required', 'exists:processes,id'],
            'name' => ['required', 'string', 'max:180'],
            'revision' => ['required', 'string', 'max:16'],
            'muestras_max' => ['required', 'integer', 'min:1', 'max:13'],
            // Lo que se abre por defecto no puede exceder el tope de la plantilla.
            'muestras_default' => ['required', 'integer', 'min:1', 'lte:muestras_max'],
        ], [
            'muestras_default.lte' => 'Las muestras por defecto no pueden superar las muestras maximas.',
        ], [
            'process_id' => 'proceso',
            'name' => 'nombre',
            'muestras_default' => 'muestras por defecto',
            'muestras_max' => 'muestras maximas',
        ]);
    }
}
