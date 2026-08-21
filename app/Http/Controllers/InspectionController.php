<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\Lot;
use App\Models\Machine;
use App\Models\Measurement;
use App\Models\Process;
use App\Models\Sector;
use App\Models\TestParameter;
use App\Models\TestTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InspectionController extends Controller
{
    public function index(Request $request): View
    {
        $inspecciones = Inspection::with(['lot.product', 'process', 'machine'])
            ->when($request->filled('proceso'), fn ($q) => $q->where('process_id', $request->integer('proceso')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->date('hasta')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = trim((string) $request->input('q'));

                $q->where(function ($sub) use ($texto) {
                    $sub->where('code', 'like', "%{$texto}%")
                        ->orWhereRelation('lot', 'code', 'like', "%{$texto}%")
                        ->orWhereRelation('lot', 'nro_tarjeta', 'like', "%{$texto}%")
                        ->orWhereRelation('lot.product', 'code', 'like', "%{$texto}%");
                });
            })
            ->latest('fecha')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('inspecciones.index', [
            'inspecciones' => $inspecciones,
            'procesos' => Process::with('sector')->where('active', true)->orderBy('sector_id')->orderBy('orden')->get(),
        ]);
    }

    public function create(Request $request, Lot $lote, Process $proceso): View
    {
        $this->verificarPuerta($lote, $proceso);

        $plantilla = $this->plantillaVigente($proceso);

        return view('inspecciones.create', [
            'lote' => $lote->load('product', 'sector'),
            'proceso' => $proceso,
            'plantilla' => $plantilla->load('parameters'),
            'maquinas' => $this->maquinas($lote->sector, $proceso),
            'configGrilla' => $this->configGrilla($plantilla, $lote),
        ]);
    }

    public function store(Request $request, Lot $lote, Process $proceso): RedirectResponse
    {
        $this->verificarPuerta($lote, $proceso);

        $plantilla = $this->plantillaVigente($proceso);
        $datos = $this->validar($request, $plantilla);

        $inspeccion = DB::transaction(function () use ($datos, $lote, $proceso, $plantilla, $request) {
            $inspeccion = Inspection::create([
                ...collect($datos)->except('m')->all(),
                'code' => Inspection::generarCodigo($proceso),
                'lot_id' => $lote->id,
                'process_id' => $proceso->id,
                'test_template_id' => $plantilla->id,
                'public_token' => Inspection::generarToken(),
                'user_id' => $request->user()->id,
                'estado' => Inspection::PENDIENTE,
            ]);

            $this->guardarMediciones($inspeccion, $plantilla, $lote, $datos['m'] ?? []);
            $this->resolverEstado($inspeccion, $datos['estado'] ?? null);

            return $inspeccion;
        });

        $lote->refresh()->recalcularEstado();

        return redirect()
            ->route('inspecciones.show', $inspeccion)
            ->with('ok', "Inspeccion {$inspeccion->code} registrada con estado {$inspeccion->fresh()->estado}.");
    }

    public function show(Inspection $inspeccion): View
    {
        $inspeccion->load([
            'lot.product', 'lot.sector', 'lot.sourceLot',
            'process', 'template.parameters', 'machine', 'user', 'measurements',
        ]);

        return view('inspecciones.show', [
            'inspeccion' => $inspeccion,
            'resumen' => $inspeccion->resumen(),
        ]);
    }

    public function edit(Inspection $inspeccion): View
    {
        $inspeccion->load(['lot.product', 'process', 'template.parameters', 'measurements']);

        // Se reusa la plantilla con la que se creo, no la vigente: una revision
        // posterior no debe alterar una boleta ya llenada.
        $plantilla = $inspeccion->template;

        return view('inspecciones.edit', [
            'inspeccion' => $inspeccion,
            'lote' => $inspeccion->lot,
            'proceso' => $inspeccion->process,
            'plantilla' => $plantilla,
            'maquinas' => $this->maquinas($inspeccion->lot->sector, $inspeccion->process),
            'configGrilla' => $this->configGrilla($plantilla, $inspeccion->lot, $inspeccion),
        ]);
    }

    public function update(Request $request, Inspection $inspeccion): RedirectResponse
    {
        abort_if(
            $inspeccion->published_at !== null,
            403,
            'Esta boleta ya fue emitida. Para corregirla hay que anular la emision primero.'
        );

        $plantilla = $inspeccion->template;
        $datos = $this->validar($request, $plantilla);

        DB::transaction(function () use ($inspeccion, $plantilla, $datos) {
            $inspeccion->update(collect($datos)->except(['m', 'estado'])->all());

            // Se reemplazan las mediciones: es mas simple y seguro que hacer
            // un diff campo por campo, y la boleta aun no fue emitida.
            $inspeccion->measurements()->delete();
            $this->guardarMediciones($inspeccion, $plantilla, $inspeccion->lot, $datos['m'] ?? []);
            $this->resolverEstado($inspeccion, $datos['estado'] ?? null);
        });

        $inspeccion->lot->refresh()->recalcularEstado();

        return redirect()
            ->route('inspecciones.show', $inspeccion)
            ->with('ok', "Inspeccion {$inspeccion->code} actualizada.");
    }

    /**
     * Emite la boleta: a partir de aca el QR resuelve el certificado publico
     * y la inspeccion queda congelada.
     */
    public function publicar(Inspection $inspeccion): RedirectResponse
    {
        if ($inspeccion->estado === Inspection::PENDIENTE) {
            return back()->with('error', 'Define primero el estado de inspeccion. Una boleta pendiente no se puede emitir.');
        }

        $inspeccion->update(['published_at' => now()]);

        return back()->with('ok', "Boleta {$inspeccion->code} emitida. El QR ya resuelve el certificado.");
    }

    public function despublicar(Inspection $inspeccion): RedirectResponse
    {
        $inspeccion->update(['published_at' => null]);

        return back()->with('ok', "Emision de {$inspeccion->code} anulada. El certificado publico dejo de estar disponible.");
    }

    /** Aborta si el lote no puede inspeccionarse todavia en este proceso. */
    private function verificarPuerta(Lot $lote, Process $proceso): void
    {
        abort_unless(
            $lote->sector_id === $proceso->sector_id,
            404,
            'El proceso no pertenece al sector del lote.'
        );

        $puerta = $lote->puedeInspeccionar($proceso);

        abort_if(! $puerta['permitido'], 422, $puerta['motivo']);
    }

    private function plantillaVigente(Process $proceso): TestTemplate
    {
        $plantilla = $proceso->activeTemplate;

        abort_unless(
            $plantilla !== null,
            422,
            "El proceso {$proceso->name} no tiene una plantilla de ensayo activa. Cargala en Configuracion."
        );

        return $plantilla->load('parameters');
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Machine> */
    private function maquinas(Sector $sector, Process $proceso)
    {
        // Se ofrecen primero las maquinas del tipo que corresponde al proceso.
        $tipo = match ($proceso->code) {
            'IT' => 'telar',
            'EXT' => 'extrusora',
            'ICC' => 'corte',
            'IMP' => 'impresora',
            default => null,
        };

        return Machine::where('sector_id', $sector->id)
            ->where('active', true)
            ->when($tipo, fn ($q) => $q->orderByRaw('CASE WHEN tipo = ? THEN 0 ELSE 1 END', [$tipo]))
            ->orderBy('code')
            ->get();
    }

    /**
     * Datos que necesita la grilla de Alpine para evaluar en vivo: limites ya
     * resueltos contra el producto del lote, y valores previos si se esta editando.
     *
     * @return array<string, mixed>
     */
    private function configGrilla(TestTemplate $plantilla, Lot $lote, ?Inspection $inspeccion = null): array
    {
        $producto = $lote->product;

        $parametros = $plantilla->parameters->map(function (TestParameter $p) use ($producto) {
            [$min, $max] = $p->limites($producto);

            return [
                'id' => $p->id,
                'tipo' => $p->tipo,
                'muestras' => $p->muestras,
                'min' => $min,
                'max' => $max,
                'requerido' => $p->requerido,
                'conformes' => $p->opciones['conformes'] ?? [],
            ];
        })->values();

        $valores = [];

        if ($inspeccion) {
            foreach ($inspeccion->measurements as $m) {
                $valores[$m->test_parameter_id][$m->muestra] = $m->valor_texto ?? $m->valor_num;
            }
        }

        return [
            'parametros' => $parametros,
            'valores' => $valores,
            'muestras' => $inspeccion
                ? max(1, (int) $inspeccion->measurements->max('muestra'))
                : $plantilla->muestras_default,
            'muestrasMax' => $plantilla->muestras_max,
        ];
    }

    /**
     * Persiste las mediciones evaluando cada valor contra la especificacion.
     *
     * @param  array<int, array<int, mixed>>  $entrada
     */
    private function guardarMediciones(Inspection $inspeccion, TestTemplate $plantilla, Lot $lote, array $entrada): void
    {
        $producto = $lote->product;
        $filas = [];
        $ahora = now();

        foreach ($plantilla->parameters as $parametro) {
            $valoresParametro = $entrada[$parametro->id] ?? [];

            foreach ($valoresParametro as $muestra => $valor) {
                if ($valor === null || $valor === '') {
                    continue;
                }

                if ($muestra < 1 || $muestra > max($parametro->muestras, $plantilla->muestras_max)) {
                    continue;
                }

                $esNumero = $parametro->tipo === 'numeric' && is_numeric($valor);

                $filas[] = [
                    'inspection_id' => $inspeccion->id,
                    'test_parameter_id' => $parametro->id,
                    'muestra' => (int) $muestra,
                    'valor_num' => $esNumero ? (float) $valor : null,
                    'valor_texto' => $esNumero ? null : (string) $valor,
                    'en_especificacion' => $parametro->evaluar($valor, $producto),
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        foreach (array_chunk($filas, 200) as $bloque) {
            Measurement::insert($bloque);
        }
    }

    /**
     * Fija el estado de la inspeccion. Si el inspector no eligio uno,
     * se aplica el que sugiere el sistema segun las mediciones.
     */
    private function resolverEstado(Inspection $inspeccion, ?string $elegido): void
    {
        $inspeccion->load('measurements', 'template.parameters', 'lot.product');

        $estado = $elegido ?: $inspeccion->estadoSugerido();

        $inspeccion->update(['estado' => $estado]);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, TestTemplate $plantilla): array
    {
        return $request->validate([
            'fecha' => ['required', 'date'],
            'hora' => ['nullable', 'date_format:H:i'],
            'machine_id' => ['nullable', 'exists:machines,id'],
            'operador' => ['nullable', 'string', 'max:120'],
            'responsable' => ['nullable', 'string', 'max:120'],
            'turno' => ['nullable', Rule::in(['Dia', 'Noche'])],
            'total_unidades' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'total_falladas' => ['nullable', 'integer', 'min:0', 'max:1000000', 'lte:total_unidades'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'observacion_interna' => ['nullable', 'string', 'max:2000'],
            // Vacio = usar la sugerencia del sistema.
            'estado' => ['nullable', Rule::in(array_keys(Inspection::ESTADOS))],
            'm' => ['nullable', 'array'],
            'm.*' => ['array'],
            'm.*.*' => ['nullable', 'string', 'max:40'],
        ], [
            'total_falladas.lte' => 'Las unidades falladas no pueden superar el total de unidades.',
        ], [
            'machine_id' => 'maquina',
            'total_unidades' => 'total de unidades',
            'total_falladas' => 'unidades falladas',
            'observacion_interna' => 'observacion interna',
        ]);
    }
}
