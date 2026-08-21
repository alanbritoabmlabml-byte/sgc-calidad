@extends('layouts.app')

@section('titulo', 'Plantillas de ensayo')

@section('encabezado')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Plantillas de ensayo</h1>
            <p class="mt-1 text-sm text-slate-500">
                Aca se define que se mide en cada proceso y con que tolerancia. Sin tocar codigo.
            </p>
        </div>
        <a href="{{ route('admin.plantillas.create') }}" class="btn-primario">Nueva plantilla</a>
    </div>
@endsection

@section('contenido')
    @include('admin._nav')

    <div class="space-y-5">
        @foreach ($procesos->groupBy('sector.name') as $sectorNombre => $procesosDelSector)
            <div class="tarjeta overflow-hidden">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 sm:px-5">
                    <h2 class="text-sm font-bold text-slate-900">Sector {{ $sectorNombre }}</h2>
                </div>

                <ul class="divide-y divide-slate-100">
                    @foreach ($procesosDelSector as $proceso)
                        <li class="px-4 py-4 sm:px-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">
                                        <span class="mr-1 text-slate-400">{{ $proceso->orden }}.</span>
                                        {{ $proceso->name }}
                                        <span class="font-normal text-slate-400">({{ $proceso->code }})</span>
                                    </p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        @if ($proceso->boleta_code)
                                            Boleta {{ $proceso->boleta_code }} &middot;
                                        @endif
                                        {{ $proceso->bloquea_siguiente
                                            ? 'bloquea el avance si sale rechazado'
                                            : 'proceso opcional, no bloquea' }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3 space-y-2">
                                @forelse ($proceso->templates->sortByDesc('revision') as $plantilla)
                                    <a href="{{ route('admin.plantillas.show', $plantilla) }}"
                                       class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2.5 hover:bg-slate-100">
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-medium text-slate-800">
                                                {{ $plantilla->name }}
                                            </span>
                                            <span class="block text-xs text-slate-500">
                                                Rev:{{ $plantilla->revision }} &middot;
                                                {{ $plantilla->parameters->count() }}
                                                {{ Str::plural('parametro', $plantilla->parameters->count()) }} &middot;
                                                hasta {{ $plantilla->muestras_max }}
                                                {{ Str::plural('muestra', $plantilla->muestras_max) }}
                                            </span>
                                        </span>
                                        <span class="shrink-0">
                                            @if ($plantilla->active)
                                                <span class="badge bg-emerald-100 text-emerald-800 ring-emerald-300">vigente</span>
                                            @else
                                                <span class="badge bg-slate-200 text-slate-600 ring-slate-300">inactiva</span>
                                            @endif
                                        </span>
                                    </a>
                                @empty
                                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                        Este proceso no tiene plantilla. No se pueden registrar inspecciones hasta cargar una.
                                    </p>
                                @endforelse
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
@endsection
