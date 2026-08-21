<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductoController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.productos.index', [
            'productos' => Product::with('sector')
                ->when($request->filled('q'), fn ($q) => $q->where('code', 'like', '%'.$request->input('q').'%'))
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
        Product::create($this->validar($request));

        return redirect()->route('admin.productos.index')->with('ok', 'Producto creado.');
    }

    public function edit(Product $producto): View
    {
        return view('admin.productos.form', [
            'producto' => $producto,
            'sectores' => Sector::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $producto): RedirectResponse
    {
        $producto->update($this->validar($request, $producto));

        return redirect()->route('admin.productos.index')->with('ok', "Producto {$producto->code} actualizado.");
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
            'ancho_nominal' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'largo_nominal' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'gramaje_nominal' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'denier_nominal' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'peso_nominal' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'active' => ['nullable', 'boolean'],
        ], [
            'code.unique' => 'Ya existe un producto con ese codigo en el sector.',
        ], [
            'sector_id' => 'sector',
            'code' => 'codigo',
            'name' => 'nombre',
            'ancho_nominal' => 'ancho nominal',
            'largo_nominal' => 'largo nominal',
            'gramaje_nominal' => 'gramaje nominal',
            'denier_nominal' => 'denier nominal',
            'peso_nominal' => 'peso nominal',
        ]);

        $datos['active'] = $request->boolean('active');

        return $datos;
    }
}
