<?php

namespace App\Http\Controllers;

use App\Models\Lot;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LotController extends Controller
{
    public function index(Request $request): View
    {
        $lotes = Lot::with(['sector', 'product', 'machine', 'inspections.process'])
            ->when($request->filled('sector'), fn ($q) => $q->where('sector_id', $request->integer('sector')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->date('hasta')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = trim((string) $request->input('q'));

                $q->where(function ($sub) use ($texto) {
                    $sub->where('code', 'like', "%{$texto}%")
                        ->orWhere('nro_tarjeta', 'like', "%{$texto}%")
                        ->orWhere('nro_lote_produccion', 'like', "%{$texto}%")
                        ->orWhereRelation('product', 'code', 'like', "%{$texto}%");
                });
            })
            ->latest('fecha')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('lotes.index', [
            'lotes' => $lotes,
            'sectores' => Sector::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $sector = $request->filled('sector')
            ? Sector::findOrFail($request->integer('sector'))
            : Sector::where('active', true)->orderBy('id')->firstOrFail();

        return view('lotes.create', $this->datosFormulario($sector));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $sector = Sector::findOrFail($datos['sector_id']);

        $datos['code'] = Lot::generarCodigo($sector);
        $datos['created_by'] = $request->user()->id;

        $lote = Lot::create($datos);

        return redirect()
            ->route('lotes.show', $lote)
            ->with('ok', "Lote {$lote->code} creado. Ya puedes registrar sus inspecciones.");
    }

    public function show(Lot $lote): View
    {
        $lote->load([
            'sector.processes',
            'product',
            'machine',
            'creator',
            'sourceLot.product',
            'derivedLots.product',
            'inspections.process',
            'inspections.machine',
        ]);

        // Estado del lote proceso por proceso: es la vista de trazabilidad.
        $cadena = $lote->sector->processes->map(fn ($proceso) => [
            'proceso' => $proceso,
            'inspeccion' => $lote->inspeccionDe($proceso),
            'puerta' => $lote->puedeInspeccionar($proceso),
        ]);

        return view('lotes.show', [
            'lote' => $lote,
            'cadena' => $cadena,
        ]);
    }

    public function edit(Lot $lote): View
    {
        return view('lotes.edit', [
            'lote' => $lote,
            ...$this->datosFormulario($lote->sector),
        ]);
    }

    public function update(Request $request, Lot $lote): RedirectResponse
    {
        $lote->update($this->validar($request, $lote));

        return redirect()
            ->route('lotes.show', $lote)
            ->with('ok', "Lote {$lote->code} actualizado.");
    }

    /** @return array<string, mixed> */
    private function datosFormulario(Sector $sector): array
    {
        return [
            'sector' => $sector,
            'sectores' => Sector::where('active', true)->orderBy('name')->get(),
            'productos' => Product::where('sector_id', $sector->id)
                ->where('active', true)->orderBy('code')->get(),
            'maquinas' => Machine::where('sector_id', $sector->id)
                ->where('active', true)->orderBy('tipo')->orderBy('code')->get(),
            // Lotes candidatos a ser el origen: en Rafia, los rollos de Tejido.
            'lotesOrigen' => Lot::with('product')
                ->where('sector_id', $sector->id)
                ->whereIn('estado', [Lot::ABIERTO, Lot::LIBERADO])
                ->latest('fecha')
                ->limit(200)
                ->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Lot $lote = null): array
    {
        return $request->validate([
            'sector_id' => ['required', 'exists:sectors,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'machine_id' => ['nullable', 'exists:machines,id'],
            'nro_tarjeta' => ['nullable', 'string', 'max:50'],
            'nro_lote_produccion' => ['nullable', 'string', 'max:50'],
            'fecha' => ['required', 'date'],
            'hora' => ['nullable', 'date_format:H:i'],
            'turno' => ['nullable', Rule::in(['Dia', 'Noche'])],
            'peso_neto' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            // Un lote no puede tener como origen a si mismo.
            'source_lot_id' => [
                'nullable',
                'exists:lots,id',
                Rule::notIn($lote ? [$lote->id] : []),
            ],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ], [
            'source_lot_id.not_in' => 'Un lote no puede ser su propio lote de origen.',
        ], [
            'sector_id' => 'sector',
            'product_id' => 'producto',
            'machine_id' => 'maquina',
            'nro_tarjeta' => 'N. de tarjeta',
            'nro_lote_produccion' => 'N. de lote de produccion',
            'peso_neto' => 'peso neto',
            'source_lot_id' => 'lote de origen',
        ]);
    }
}
