@extends('layouts.app')

@section('titulo', 'Sectores')

@section('encabezado')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Sectores</h1>
            <p class="mt-1 text-sm text-slate-500">
                Cada sector tiene su cadena de procesos y sus plantillas de ensayo.
            </p>
        </div>
        <a href="{{ route('admin.sectores.create') }}" class="btn-primario">Nuevo sector</a>
    </div>
@endsection

@section('contenido')
    @include('admin._nav')

    <div class="space-y-4">
        @foreach ($sectores as $sector)
            <div class="tarjeta overflow-hidden">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-3 sm:px-5">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">
                            {{ $sector->name }}
                            <span class="ml-1 font-mono font-normal text-slate-400">{{ $sector->code }}</span>
                            @unless ($sector->active)
                                <span class="badge ml-1 bg-slate-200 text-slate-600 ring-slate-300">inactivo</span>
                            @endunless
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Prefijo de lote <span class="font-mono">{{ $sector->prefijo_lote }}-AA-00001</span>
                            &middot; {{ $sector->lots_count }} {{ Str::plural('lote', $sector->lots_count) }}
                        </p>
                    </div>
                    <a href="{{ route('admin.sectores.edit', $sector) }}"
                       class="text-xs font-semibold text-pc-700 hover:underline">Editar</a>
                </div>

                @if ($sector->processes->isEmpty())
                    <p class="px-4 py-4 text-sm text-amber-800 sm:px-5">
                        Este sector no tiene procesos cargados. Sin procesos no se pueden registrar
                        inspecciones. Por ahora los procesos se cargan por seeder.
                    </p>
                @else
                    <ol class="divide-y divide-slate-100">
                        @foreach ($sector->processes as $proceso)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 sm:px-5">
                                <span class="min-w-0">
                                    <span class="text-sm font-medium text-slate-800">
                                        <span class="mr-1 text-slate-400">{{ $proceso->orden }}.</span>
                                        {{ $proceso->name }}
                                        <span class="font-normal text-slate-400">({{ $proceso->code }})</span>
                                    </span>
                                    <span class="block text-xs text-slate-500">
                                        @if ($proceso->boleta_code) {{ $proceso->boleta_code }} &middot; @endif
                                        {{ $proceso->bloquea_siguiente
                                            ? 'puerta de calidad: bloquea el avance si sale no conforme'
                                            : 'no bloquea el avance' }}
                                        @if ($proceso->requiere_cantidades) &middot; lleva conteo de unidades @endif
                                    </span>
                                </span>
                                <span class="flex shrink-0 gap-1">
                                    @if ($proceso->bloquea_siguiente)
                                        <span class="badge bg-pc-50 text-pc-800 ring-pc-200">puerta</span>
                                    @else
                                        <span class="badge bg-slate-100 text-slate-600 ring-slate-300">opcional</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @endforeach
    </div>
@endsection
