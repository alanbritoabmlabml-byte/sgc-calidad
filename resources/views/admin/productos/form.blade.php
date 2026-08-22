@extends('layouts.app')

@section('titulo', $producto->exists ? 'Ficha de '.$producto->code : 'Nuevo producto')

@section('encabezado')
    <nav class="mb-2 text-xs text-slate-500">
        <a href="{{ route('admin.productos.index') }}" class="hover:underline">Productos</a> /
        {{ $producto->exists ? $producto->code : 'Nuevo' }}
    </nav>
    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
        {{ $producto->exists ? 'Ficha técnica del producto' : 'Nuevo producto' }}
    </h1>
    @if ($producto->exists && $producto->ficha_actualizada_at)
        <p class="mt-1 text-sm text-slate-500">
            Última actualización {{ $producto->ficha_actualizada_at->format('d/m/Y H:i') }}
            @if ($producto->actualizadoPor) por {{ $producto->actualizadoPor->name }} @endif
        </p>
    @endif
@endsection

@section('contenido')

@php $completitud = $producto->completitudNominales(); @endphp

@if ($producto->exists && $completitud['faltan'] !== [])
    <div class="mb-5 rounded-lg border-l-4 border-amber-500 bg-amber-50 p-4">
        <p class="text-sm font-semibold text-amber-900">Ficha técnica incompleta</p>
        <p class="mt-1 text-sm text-amber-800">
            Faltan estos valores nominales: <strong>{{ implode(', ', $completitud['faltan']) }}</strong>.
            Sin ellos el ensayo no tiene especificación contra la que evaluar, y el veredicto de esas
            características queda como "sin evaluar".
        </p>
    </div>
@endif

<form method="POST"
      action="{{ $producto->exists ? route('admin.productos.update', $producto) : route('admin.productos.store') }}"
      class="max-w-4xl">
    @csrf
    @if ($producto->exists)
        @method('PUT')
    @endif

    {{-- ===== Identificacion ===== --}}
    <div class="tarjeta p-4 sm:p-5">
        <h2 class="text-sm font-semibold text-slate-900">Identificación</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="sector_id" class="etiqueta">Sector *</label>
                <select id="sector_id" name="sector_id" class="campo" required>
                    @foreach ($sectores as $s)
                        <option value="{{ $s->id }}" @selected(old('sector_id', $producto->sector_id) == $s->id)>
                            {{ $s->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="code" class="etiqueta">Código *</label>
                <input id="code" name="code" class="campo" required
                       value="{{ old('code', $producto->code) }}" placeholder="Bl 65x104/66">
            </div>

            <div>
                <label for="name" class="etiqueta">Nombre comercial</label>
                <input id="name" name="name" class="campo" value="{{ old('name', $producto->name) }}"
                       placeholder="Saco Blanco 65x104">
                <p class="mt-1 text-xs text-slate-400">Es lo que sale impreso en la etiqueta QR.</p>
            </div>

            <div>
                <label for="color" class="etiqueta">Color</label>
                <input id="color" name="color" class="campo" value="{{ old('color', $producto->color) }}">
            </div>

            <div>
                <label for="cliente" class="etiqueta">Cliente</label>
                <input id="cliente" name="cliente" class="campo" value="{{ old('cliente', $producto->cliente) }}"
                       placeholder="Si el producto es exclusivo de un cliente">
            </div>

            <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="active" value="1"
                           @checked(old('active', $producto->active ?? true))
                           class="h-4 w-4 rounded border-slate-300 text-pc-700 focus:ring-pc-500">
                    Producto activo
                </label>
            </div>
        </div>
    </div>

    {{-- ===== Nominales que alimentan el ensayo ===== --}}
    <div class="tarjeta mt-5 p-4 sm:p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900">Valores nominales</h2>
            <span @class([
                'badge',
                'bg-emerald-100 text-emerald-800 ring-emerald-300' => $completitud['faltan'] === [],
                'bg-amber-100 text-amber-800 ring-amber-300' => $completitud['faltan'] !== [],
            ])>{{ $completitud['cargados'] }} de {{ $completitud['total'] }} cargados</span>
        </div>
        <p class="mt-1 text-xs leading-relaxed text-slate-500">
            De acá sale el objetivo de los parámetros de ensayo configurados como
            "tomar el objetivo del producto". En el código <span class="font-mono">Bl 65x104/66</span>:
            <strong>65</strong> es el ancho, <strong>104</strong> el largo y <strong>66</strong> el gramaje.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="ancho_nominal" class="etiqueta">Ancho (cm)</label>
                <input id="ancho_nominal" name="ancho_nominal" type="number" step="0.01" min="0" class="campo"
                       value="{{ old('ancho_nominal', $producto->ancho_nominal) }}">
                <p class="mt-1 text-xs text-slate-400">Tolerancia del ensayo: ± 1 cm</p>
            </div>

            <div>
                <label for="largo_nominal" class="etiqueta">Largo útil (cm)</label>
                <input id="largo_nominal" name="largo_nominal" type="number" step="0.01" min="0" class="campo"
                       value="{{ old('largo_nominal', $producto->largo_nominal) }}">
                <p class="mt-1 text-xs text-slate-400">Tolerancia del ensayo: ± 1 cm</p>
            </div>

            <div>
                <label for="gramaje_nominal" class="etiqueta">Gramaje (g/m²)</label>
                <input id="gramaje_nominal" name="gramaje_nominal" type="number" step="0.01" min="0" class="campo"
                       value="{{ old('gramaje_nominal', $producto->gramaje_nominal) }}">
                <p class="mt-1 text-xs text-slate-400">Tolerancia del ensayo: ± 2 g/m²</p>
            </div>

            <div>
                <label for="peso_nominal" class="etiqueta">Peso de la bolsa (g)</label>
                <input id="peso_nominal" name="peso_nominal" type="number" step="0.01" min="0" class="campo"
                       value="{{ old('peso_nominal', $producto->peso_nominal) }}">
                <p class="mt-1 text-xs font-medium text-amber-700">
                    Confirmalo con Calidad: de este valor depende el veredicto del peso en Corte y Costura.
                </p>
            </div>

            <div>
                <label for="denier_nominal" class="etiqueta">Denier de la cinta</label>
                <input id="denier_nominal" name="denier_nominal" type="number" step="0.01" min="0" class="campo"
                       value="{{ old('denier_nominal', $producto->denier_nominal) }}">
                <p class="mt-1 text-xs text-slate-400">Tolerancia del ensayo: ± 3,5 %</p>
            </div>

            <div>
                <label for="capacidad_kg" class="etiqueta">Capacidad (kg)</label>
                <input id="capacidad_kg" name="capacidad_kg" type="number" step="0.01" min="0" class="campo"
                       value="{{ old('capacidad_kg', $producto->capacidad_kg) }}" placeholder="50">
            </div>

            <div>
                <label for="densidad_urdimbre_nominal" class="etiqueta">Densidad urdimbre (cintas/10 cm)</label>
                <input id="densidad_urdimbre_nominal" name="densidad_urdimbre_nominal" type="number" step="0.01"
                       min="0" class="campo"
                       value="{{ old('densidad_urdimbre_nominal', $producto->densidad_urdimbre_nominal) }}">
            </div>

            <div>
                <label for="densidad_trama_nominal" class="etiqueta">Densidad trama (cintas/10 cm)</label>
                <input id="densidad_trama_nominal" name="densidad_trama_nominal" type="number" step="0.01"
                       min="0" class="campo"
                       value="{{ old('densidad_trama_nominal', $producto->densidad_trama_nominal) }}">
            </div>
        </div>
    </div>

    {{-- ===== Especificacion declarada ===== --}}
    <div class="tarjeta mt-5 p-4 sm:p-5">
        <h2 class="text-sm font-semibold text-slate-900">Especificación de la ficha técnica</h2>
        <p class="mt-1 text-xs text-slate-500">
            Datos declarados del producto, que acompañan al certificado.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <label for="norma" class="etiqueta">Norma de referencia</label>
                <input id="norma" name="norma" class="campo" value="{{ old('norma', $producto->norma) }}"
                       placeholder="Ej. NB 60001">
            </div>

            <div>
                <label for="tipo_tejido" class="etiqueta">Tipo de tejido</label>
                <select id="tipo_tejido" name="tipo_tejido" class="campo">
                    <option value="">Sin definir</option>
                    @foreach (\App\Models\Product::TIPOS_TEJIDO as $tipo)
                        <option value="{{ $tipo }}" @selected(old('tipo_tejido', $producto->tipo_tejido) === $tipo)>
                            {{ $tipo }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="tratamiento_uv" class="etiqueta">Tratamiento UV</label>
                <input id="tratamiento_uv" name="tratamiento_uv" class="campo"
                       value="{{ old('tratamiento_uv', $producto->tratamiento_uv) }}"
                       placeholder="Ej. 1200 h">
            </div>

            <div class="sm:col-span-3">
                <label for="observaciones_tecnicas" class="etiqueta">Observaciones técnicas</label>
                <textarea id="observaciones_tecnicas" name="observaciones_tecnicas" rows="3" class="campo"
                          placeholder="Requisitos particulares del producto o del cliente">{{ old('observaciones_tecnicas', $producto->observaciones_tecnicas) }}</textarea>
            </div>
        </div>
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        <button type="submit" class="btn-primario">
            {{ $producto->exists ? 'Guardar ficha técnica' : 'Crear producto' }}
        </button>
        <a href="{{ route('admin.productos.index') }}" class="btn-secundario">Cancelar</a>
    </div>
</form>
@endsection
