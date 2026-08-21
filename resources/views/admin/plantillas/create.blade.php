@extends('layouts.app')

@section('titulo', 'Nueva plantilla')

@section('encabezado')
    <nav class="mb-2 text-xs text-slate-500">
        <a href="{{ route('admin.plantillas.index') }}" class="hover:underline">Plantillas</a> / Nueva
    </nav>
    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Nueva plantilla de ensayo</h1>
@endsection

@section('contenido')
    <form method="POST" action="{{ route('admin.plantillas.store') }}" class="max-w-2xl">
        @csrf

        <div class="tarjeta p-4 sm:p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="process_id" class="etiqueta">Proceso *</label>
                    <select id="process_id" name="process_id" class="campo" required>
                        @foreach ($procesos as $p)
                            <option value="{{ $p->id }}" @selected(old('process_id') == $p->id)>
                                {{ $p->sector->name }} &middot; {{ $p->orden }}. {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="name" class="etiqueta">Nombre del formulario *</label>
                    <input id="name" name="name" class="campo" required value="{{ old('name') }}"
                           placeholder="Ej. Ensayos de Calidad en Bolsas de Polipropileno">
                </div>

                <div>
                    <label for="revision" class="etiqueta">Revision *</label>
                    <input id="revision" name="revision" class="campo" required value="{{ old('revision', '1') }}">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="muestras_max" class="etiqueta">Muestras max. *</label>
                        <input id="muestras_max" name="muestras_max" type="number" min="1" max="13" class="campo"
                               required value="{{ old('muestras_max', 13) }}">
                    </div>
                    <div>
                        <label for="muestras_default" class="etiqueta">Por defecto *</label>
                        <input id="muestras_default" name="muestras_default" type="number" min="1" max="13" class="campo"
                               required value="{{ old('muestras_default', 8) }}">
                    </div>
                </div>
            </div>

            <p class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                La plantilla se crea inactiva. Cargale los parametros de ensayo y despues activala:
                al activarse reemplaza a la plantilla vigente del proceso, pero las boletas ya
                emitidas conservan la revision con la que se llenaron.
            </p>
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="btn-primario">Crear plantilla</button>
            <a href="{{ route('admin.plantillas.index') }}" class="btn-secundario">Cancelar</a>
        </div>
    </form>
@endsection
