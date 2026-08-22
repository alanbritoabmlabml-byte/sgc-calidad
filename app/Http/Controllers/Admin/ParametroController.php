<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TestParameter;
use App\Models\TestTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParametroController extends Controller
{
    public function store(Request $request, TestTemplate $plantilla): RedirectResponse
    {
        $datos = $this->validar($request, $plantilla);

        $datos['orden'] = (int) $plantilla->parameters()->max('orden') + 1;
        $datos['created_by'] = $request->user()->id;

        $plantilla->parameters()->create($datos);

        return back()->with('ok', "Parametro \"{$datos['label']}\" agregado.");
    }

    public function update(Request $request, TestParameter $parametro): RedirectResponse
    {
        $parametro->update($this->validar($request, $parametro->template, $parametro));

        return back()->with('ok', "Parametro \"{$parametro->label}\" actualizado.");
    }

    public function destroy(TestParameter $parametro): RedirectResponse
    {
        // Si ya hay mediciones cargadas, borrar el parametro se llevaria datos
        // de boletas emitidas. En ese caso se debe crear una revision nueva.
        $mediciones = $parametro->measurements()->count();

        if ($mediciones > 0) {
            return back()->with(
                'error',
                "No se puede eliminar \"{$parametro->label}\": ya tiene {$mediciones} mediciones registradas. ".
                'Duplica la plantilla en una revision nueva y quitalo alli.'
            );
        }

        $etiqueta = $parametro->label;
        $parametro->delete();

        return back()->with('ok', "Parametro \"{$etiqueta}\" eliminado.");
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, TestTemplate $plantilla, ?TestParameter $parametro = null): array
    {
        $datos = $request->validate([
            'code' => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('test_parameters', 'code')
                    ->where('test_template_id', $plantilla->id)
                    ->where('grupo', $request->input('grupo') ?: null)
                    ->ignore($parametro?->id),
            ],
            'label' => ['required', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:24'],
            'tipo' => ['required', Rule::in(TestParameter::TIPOS)],
            'grupo' => ['nullable', 'string', 'max:16'],

            'spec_modo' => ['required', Rule::in(array_keys(TestParameter::MODOS))],
            'spec_min' => ['nullable', 'numeric'],
            'spec_max' => ['nullable', 'numeric', 'gte:spec_min'],
            'spec_objetivo' => ['nullable', 'numeric'],
            'spec_tolerancia' => ['nullable', 'numeric', 'min:0'],
            'spec_tolerancia_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'spec_label' => ['nullable', 'string', 'max:60'],
            'spec_desde_producto' => ['nullable', Rule::in([
                'ancho_nominal', 'largo_nominal', 'gramaje_nominal', 'denier_nominal', 'peso_nominal',
            ])],

            'muestras' => ['required', 'integer', 'min:1', 'max:'.$plantilla->muestras_max],
            'promediar' => ['nullable', 'boolean'],
            'requerido' => ['nullable', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:1'],

            // Se reciben como texto separado por coma y se guardan como json.
            'valores' => ['nullable', 'string', 'max:200'],
            'conformes' => ['nullable', 'string', 'max:200'],
        ], [
            'code.regex' => 'El codigo solo admite minusculas, numeros y guion bajo.',
            'code.unique' => 'Ya existe un parametro con ese codigo y grupo en esta plantilla.',
            'spec_max.gte' => 'El maximo no puede ser menor que el minimo.',
            'muestras.max' => "Esta plantilla admite hasta {$plantilla->muestras_max} muestras.",
        ], [
            'code' => 'codigo',
            'label' => 'nombre',
            'unit' => 'unidad',
            'spec_modo' => 'modo de especificacion',
            'spec_desde_producto' => 'origen del objetivo',
        ]);

        $datos['grupo'] = $datos['grupo'] ?: null;
        $datos['spec_desde_producto'] = $datos['spec_desde_producto'] ?: null;
        $datos['promediar'] = $request->boolean('promediar');
        $datos['requerido'] = $request->boolean('requerido');

        $datos['opciones'] = $this->opciones($datos);
        unset($datos['valores'], $datos['conformes']);

        return $datos;
    }

    /**
     * Arma el json de opciones para los parametros de tipo select.
     *
     * @param  array<string, mixed>  $datos
     * @return array{valores: array<int, string>, conformes: array<int, string>}|null
     */
    private function opciones(array $datos): ?array
    {
        $partir = fn (?string $texto): array => collect(explode(',', (string) $texto))
            ->map(fn (string $v) => trim($v))
            ->filter()
            ->values()
            ->all();

        $valores = $partir($datos['valores'] ?? null);
        $conformes = $partir($datos['conformes'] ?? null);

        if ($valores === [] && $conformes === []) {
            return null;
        }

        return ['valores' => $valores, 'conformes' => $conformes];
    }
}
