<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductoController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.productos.index', [
            'productos' => Product::with('sector', 'actualizadoPor')
                ->when($request->filled('q'), fn ($q) => $q->where('code', 'like', '%'.$request->input('q').'%'))
                ->when($request->boolean('incompletos'), fn ($q) => $q->where(fn ($s) => $s
                    ->whereNull('ancho_nominal')->orWhereNull('largo_nominal')
                    ->orWhereNull('gramaje_nominal')->orWhereNull('peso_nominal')))
                ->orderBy('sector_id')
                ->orderBy('code')
                ->paginate(30)
                ->withQueryString(),
            'sectores' => Sector::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.productos.form', [
            'producto' => new Product(['active' => true]),
            'sectores' => Sector::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $producto = Product::create($this->validar($request) + $this->sello($request));

        return redirect()->route('admin.productos.edit', $producto)
            ->with('ok', "Producto {$producto->code} creado. Revisa que la ficha tecnica este completa.");
    }

    public function edit(Product $producto): View
    {
        return view('admin.productos.form', [
            'producto' => $producto->load('actualizadoPor'),
            'sectores' => Sector::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $producto): RedirectResponse
    {
        $producto->update($this->validar($request, $producto) + $this->sello($request));

        return redirect()->route('admin.productos.index')
            ->with('ok', "Ficha tecnica de {$producto->code} actualizada.");
    }

    /**
     * Alta rapida desde el formulario de lote, sin cambiar de pantalla.
     *
     * Solo pide lo minimo y trata de deducir los nominales del codigo: en
     * "Bl 65x104/66" el 65 es el ancho, el 104 el largo y el 66 el gramaje.
     * Devuelve el producto en JSON para agregarlo al desplegable.
     */
    public function rapido(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'sector_id' => ['required', 'exists:sectors,id'],
            'code' => [
                'required', 'string', 'max:60',
                Rule::unique('products', 'code')->where('sector_id', $request->integer('sector_id')),
            ],
            'name' => ['nullable', 'string', 'max:120'],
            'ancho_nominal' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'largo_nominal' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'gramaje_nominal' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'peso_nominal' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ], [
            'code.unique' => 'Ya existe un producto con ese codigo en el sector.',
        ], [
            'sector_id' => 'sector',
            'code' => 'codigo',
        ]);

        $deducidos = self::deducirDelCodigo($datos['code']);

        $producto = Product::create([
            'sector_id' => $datos['sector_id'],
            'code' => $datos['code'],
            'name' => $datos['name'] ?: null,
            // Lo que el usuario escribio manda; lo deducido solo rellena huecos.
            'ancho_nominal' => $datos['ancho_nominal'] ?? $deducidos['ancho_nominal'],
            'largo_nominal' => $datos['largo_nominal'] ?? $deducidos['largo_nominal'],
            'gramaje_nominal' => $datos['gramaje_nominal'] ?? $deducidos['gramaje_nominal'],
            'peso_nominal' => $datos['peso_nominal'] ?? null,
            'active' => true,
            ...$this->sello($request),
        ]);

        $completitud = $producto->completitudNominales();

        return response()->json([
            'ok' => true,
            'producto' => ['id' => $producto->id, 'code' => $producto->code],
            'deducido' => $deducidos,
            'mensaje' => $completitud['faltan'] === []
                ? "Producto {$producto->code} creado con su ficha completa."
                : "Producto {$producto->code} creado. Falta cargar: "
                    .implode(', ', $completitud['faltan'])
                    .'. Sin esos valores el ensayo no tiene contra que evaluar.',
        ], 201);
    }

    /**
     * Deduce los nominales del codigo de producto.
     * "Bl 65x104/66" -> ancho 65 cm, largo 104 cm, gramaje 66 g/m2.
     *
     * @return array{ancho_nominal: ?float, largo_nominal: ?float, gramaje_nominal: ?float}
     */
    public static function deducirDelCodigo(string $code): array
    {
        $vacio = ['ancho_nominal' => null, 'largo_nominal' => null, 'gramaje_nominal' => null];

        // ancho "x" largo, opcionalmente "/" gramaje. Admite coma o punto decimal.
        if (! preg_match('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)(?:\s*\/\s*(\d+(?:[.,]\d+)?))?/', $code, $m)) {
            return $vacio;
        }

        $num = fn (?string $v): ?float => $v === null || $v === ''
            ? null
            : (float) str_replace(',', '.', $v);

        return [
            'ancho_nominal' => $num($m[1] ?? null),
            'largo_nominal' => $num($m[2] ?? null),
            'gramaje_nominal' => $num($m[3] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    private function sello(Request $request): array
    {
        return [
            'actualizado_por' => $request->user()->id,
            'ficha_actualizada_at' => now(),
        ];
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Product $producto = null): array
    {
        $datos = $request->validate([
            'sector_id' => ['required', 'exists:sectors,id'],
            'code' => [
                'required', 'string', 'max:60',
                Rule::unique('products', 'code')
                    ->where('sector_id', $request->integer('sector_id'))
                    ->ignore($producto?->id),
            ],
            'name' => ['nullable', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:40'],

            // Ficha tecnica
            'norma' => ['nullable', 'string', 'max:60'],
            'tipo_tejido' => ['nullable', Rule::in(Product::TIPOS_TEJIDO)],
            'tratamiento_uv' => ['nullable', 'string', 'max:60'],
            'cliente' => ['nullable', 'string', 'max:120'],

            // Nominales que alimentan las tolerancias del ensayo
            'ancho_nominal' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'largo_nominal' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'gramaje_nominal' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'denier_nominal' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'peso_nominal' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'capacidad_kg' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'densidad_urdimbre_nominal' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'densidad_trama_nominal' => ['nullable', 'numeric', 'min:0', 'max:999'],

            'observaciones_tecnicas' => ['nullable', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
        ], [
            'code.unique' => 'Ya existe un producto con ese codigo en el sector.',
        ], [
            'sector_id' => 'sector',
            'code' => 'codigo',
            'name' => 'nombre',
            'tipo_tejido' => 'tipo de tejido',
            'tratamiento_uv' => 'tratamiento UV',
            'ancho_nominal' => 'ancho nominal',
            'largo_nominal' => 'largo nominal',
            'gramaje_nominal' => 'gramaje nominal',
            'denier_nominal' => 'denier nominal',
            'peso_nominal' => 'peso nominal',
            'capacidad_kg' => 'capacidad',
            'densidad_urdimbre_nominal' => 'densidad de urdimbre',
            'densidad_trama_nominal' => 'densidad de trama',
            'observaciones_tecnicas' => 'observaciones tecnicas',
        ]);

        $datos['active'] = $request->boolean('active');

        return $datos;
    }
}
