@extends('layouts.app')

@section('titulo', $sector->exists ? 'Editar '.$sector->name : 'Nuevo sector')

@section('encabezado')
    <nav class="mb-2 text-xs text-slate-500">
        <a href="{{ route('admin.sectores.index') }}" class="hover:underline">Sectores</a> /
        {{ $sector->exists ? $sector->name : 'Nuevo' }}
    </nav>
    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
        {{ $sector->exists ? 'Editar sector' : 'Nuevo sector' }}
    </h1>
@endsection

@section('contenido')
    <form method="POST"
          action="{{ $sector->exists ? route('admin.sectores.update', $sector) : route('admin.sectores.store') }}"
          class="max-w-xl">
        @csrf
        @if ($sector->exists)
            @method('PUT')
        @endif

        <div class="tarjeta p-4 sm:p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="etiqueta">Nombre *</label>
                    <input id="name" name="name" class="campo" required
                           value="{{ old('name', $sector->name) }}" placeholder="Ej. Rafia">
                </div>

                <div>
                    <label for="code" class="etiqueta">Código *</label>
                    <input id="code" name="code" class="campo font-mono uppercase" required
                           value="{{ old('code', $sector->code) }}" placeholder="RAFIA">
                </div>

                <div>
                    <label for="prefijo_lote" class="etiqueta">Prefijo de lote *</label>
                    <input id="prefijo_lote" name="prefijo_lote" class="campo font-mono uppercase" required
                           maxlength="8" value="{{ old('prefijo_lote', $sector->prefijo_lote) }}" placeholder="RAF">
                    <p class="mt-1 text-xs text-slate-400">
                        Los lotes se numeran <span class="font-mono">PREFIJO-AA-00001</span>.
                        @if ($sector->exists)
                            Cambiarlo no renumera los lotes existentes.
                        @endif
                    </p>
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="active" value="1"
                               @checked(old('active', $sector->active ?? true))
                               class="h-4 w-4 rounded border-slate-300 text-pc-700 focus:ring-pc-500">
                        Sector activo
                    </label>
                </div>
            </div>

            @unless ($sector->exists)
                <p class="mt-4 rounded-lg bg-slate-50 p-3 text-xs leading-relaxed text-slate-600">
                    Después de crear el sector hay que cargarle sus <strong>procesos</strong> y una
                    <strong>plantilla de ensayo activa</strong> por proceso. Sin eso no se pueden
                    registrar inspecciones. Los procesos por ahora se cargan por seeder: la forma
                    más rápida es copiar <code class="font-mono">RafiaSeeder</code> como modelo.
                </p>
            @endunless
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="btn-primario">
                {{ $sector->exists ? 'Guardar cambios' : 'Crear sector' }}
            </button>
            <a href="{{ route('admin.sectores.index') }}" class="btn-secundario">Cancelar</a>
        </div>
    </form>
@endsection
