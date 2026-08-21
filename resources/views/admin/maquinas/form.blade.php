@extends('layouts.app')

@section('titulo', $maquina->exists ? 'Editar '.$maquina->code : 'Nueva maquina')

@section('encabezado')
    <nav class="mb-2 text-xs text-slate-500">
        <a href="{{ route('admin.maquinas.index') }}" class="hover:underline">Maquinas</a> /
        {{ $maquina->exists ? $maquina->code : 'Nueva' }}
    </nav>
    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
        {{ $maquina->exists ? 'Editar maquina' : 'Nueva maquina' }}
    </h1>
@endsection

@section('contenido')
    <form method="POST"
          action="{{ $maquina->exists ? route('admin.maquinas.update', $maquina) : route('admin.maquinas.store') }}"
          class="max-w-xl">
        @csrf
        @if ($maquina->exists)
            @method('PUT')
        @endif

        <div class="tarjeta p-4 sm:p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="sector_id" class="etiqueta">Sector *</label>
                    <select id="sector_id" name="sector_id" class="campo" required>
                        @foreach ($sectores as $s)
                            <option value="{{ $s->id }}" @selected(old('sector_id', $maquina->sector_id) == $s->id)>
                                {{ $s->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="tipo" class="etiqueta">Tipo *</label>
                    <select id="tipo" name="tipo" class="campo" required>
                        @foreach (\App\Models\Machine::TIPOS as $t)
                            <option value="{{ $t }}" @selected(old('tipo', $maquina->tipo) === $t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="code" class="etiqueta">Codigo *</label>
                    <input id="code" name="code" class="campo" required value="{{ old('code', $maquina->code) }}"
                           placeholder="T-2">
                </div>

                <div>
                    <label for="name" class="etiqueta">Nombre</label>
                    <input id="name" name="name" class="campo" value="{{ old('name', $maquina->name) }}"
                           placeholder="Telar 2">
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="active" value="1" @checked(old('active', $maquina->active ?? true))
                               class="h-4 w-4 rounded border-slate-300 text-pc-600 focus:ring-pc-500">
                        Activa
                    </label>
                </div>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="btn-primario">
                {{ $maquina->exists ? 'Guardar cambios' : 'Crear maquina' }}
            </button>
            <a href="{{ route('admin.maquinas.index') }}" class="btn-secundario">Cancelar</a>
        </div>
    </form>
@endsection
