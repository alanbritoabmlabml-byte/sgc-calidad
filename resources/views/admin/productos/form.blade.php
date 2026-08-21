@extends('layouts.app')

@section('titulo', $producto->exists ? 'Editar '.$producto->code : 'Nuevo producto')

@section('encabezado')
    <nav class="mb-2 text-xs text-slate-500">
        <a href="{{ route('admin.productos.index') }}" class="hover:underline">Productos</a> /
        {{ $producto->exists ? $producto->code : 'Nuevo' }}
    </nav>
    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
        {{ $producto->exists ? 'Editar producto' : 'Nuevo producto' }}
    </h1>
@endsection

@section('contenido')
    <form method="POST"
          action="{{ $producto->exists ? route('admin.productos.update', $producto) : route('admin.productos.store') }}"
          class="max-w-3xl">
        @csrf
        @if ($producto->exists)
            @method('PUT')
        @endif

        <div class="tarjeta p-4 sm:p-5">
            <div class="grid gap-4 sm:grid-cols-2">
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
                    <label for="code" class="etiqueta">Codigo *</label>
                    <input id="code" name="code" class="campo" required value="{{ old('code', $producto->code) }}"
                           placeholder="Bl 65x104/66">
                </div>

                <div>
                    <label for="name" class="etiqueta">Nombre</label>
                    <input id="name" name="name" class="campo" value="{{ old('name', $producto->name) }}">
                </div>

                <div>
                    <label for="color" class="etiqueta">Color</label>
                    <input id="color" name="color" class="campo" value="{{ old('color', $producto->color) }}">
                </div>
            </div>
        </div>

        <div class="tarjeta mt-5 p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-slate-900">Valores nominales</h2>
            <p class="mt-1 text-xs text-slate-500">
                De aca sale el objetivo de los parametros configurados como
                "tomar el objetivo del producto". En el codigo "Bl 65x104/66":
                65 es el ancho, 104 el largo y 66 el gramaje.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="ancho_nominal" class="etiqueta">Ancho (cm)</label>
                    <input id="ancho_nominal" name="ancho_nominal" type="number" step="0.01" min="0" class="campo"
                           value="{{ old('ancho_nominal', $producto->ancho_nominal) }}">
                </div>
                <div>
                    <label for="largo_nominal" class="etiqueta">Largo util (cm)</label>
                    <input id="largo_nominal" name="largo_nominal" type="number" step="0.01" min="0" class="campo"
                           value="{{ old('largo_nominal', $producto->largo_nominal) }}">
                </div>
                <div>
                    <label for="gramaje_nominal" class="etiqueta">Gramaje (g/m2)</label>
                    <input id="gramaje_nominal" name="gramaje_nominal" type="number" step="0.01" min="0" class="campo"
                           value="{{ old('gramaje_nominal', $producto->gramaje_nominal) }}">
                </div>
                <div>
                    <label for="peso_nominal" class="etiqueta">Peso de la bolsa (g)</label>
                    <input id="peso_nominal" name="peso_nominal" type="number" step="0.01" min="0" class="campo"
                           value="{{ old('peso_nominal', $producto->peso_nominal) }}">
                    <p class="mt-1 text-xs text-amber-700">
                        Confirmalo con Calidad: es el valor contra el que se evalua el peso en Corte y Costura.
                    </p>
                </div>
                <div>
                    <label for="denier_nominal" class="etiqueta">Denier de la cinta</label>
                    <input id="denier_nominal" name="denier_nominal" type="number" step="0.01" min="0" class="campo"
                           value="{{ old('denier_nominal', $producto->denier_nominal) }}">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="active" value="1" @checked(old('active', $producto->active ?? true))
                               class="h-4 w-4 rounded border-slate-300 text-pc-600 focus:ring-pc-500">
                        Activo
                    </label>
                </div>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="btn-primario">
                {{ $producto->exists ? 'Guardar cambios' : 'Crear producto' }}
            </button>
            <a href="{{ route('admin.productos.index') }}" class="btn-secundario">Cancelar</a>
        </div>
    </form>
@endsection
