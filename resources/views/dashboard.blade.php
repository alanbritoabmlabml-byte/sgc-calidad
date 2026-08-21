@extends('layouts.app')

@section('titulo', 'Tablero')

@section('encabezado')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Tablero de calidad</h1>
            <p class="mt-1 text-sm text-slate-500">
                Ultimos 30 dias &middot; {{ $periodo[0]->format('d/m/Y') }} al {{ $periodo[1]->format('d/m/Y') }}
            </p>
        </div>
        @if (auth()->user()->puedeEditar())
            <a href="{{ route('lotes.create') }}" class="btn-primario">Nuevo lote</a>
        @endif
    </div>
@endsection

@section('contenido')

    {{-- Indicadores --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5">
        <x-kpi titulo="Inspecciones hoy" :valor="$inspeccionesHoy" />
        <x-kpi titulo="Conformidad 30d"
               :valor="$conformidad === null ? 'sin datos' : $conformidad . '%'"
               :tono="$conformidad === null ? 'neutro' : ($conformidad >= 90 ? 'bien' : ($conformidad >= 75 ? 'alerta' : 'mal'))" />
        <x-kpi titulo="Lotes abiertos" :valor="$lotesAbiertos" />
        <x-kpi titulo="Lotes bloqueados" :valor="$lotesBloqueados" :tono="$lotesBloqueados > 0 ? 'mal' : 'bien'" />
        <x-kpi titulo="Boletas pendientes" :valor="$pendientes" :tono="$pendientes > 0 ? 'alerta' : 'bien'"
               class="col-span-2 lg:col-span-1" />
    </div>

    {{-- Acceso rapido por sector --}}
    <div class="tarjeta mt-5 p-4 sm:p-5">
        <h2 class="text-sm font-semibold text-slate-900">Registrar una inspeccion</h2>
        <p class="mt-1 text-xs text-slate-500">
            Elegi el lote y el proceso. El sistema no deja avanzar a un proceso si el anterior no esta aprobado.
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($sectores as $sector)
                <a href="{{ route('lotes.index', ['sector' => $sector->id]) }}" class="btn-secundario">
                    Lotes de {{ $sector->name }}
                </a>
            @endforeach
            <a href="{{ route('inspecciones.index') }}" class="btn-secundario">Todas las inspecciones</a>
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">

        {{-- Ultimas inspecciones --}}
        <div class="tarjeta lg:col-span-2">
            <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                <h2 class="text-sm font-semibold text-slate-900">Ultimas inspecciones</h2>
            </div>

            @if ($ultimas->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-slate-500 sm:px-5">
                    Todavia no hay inspecciones registradas.
                </p>
            @else
                {{-- Tabla en escritorio --}}
                <div class="hidden overflow-x-auto sm:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2 font-semibold">Boleta</th>
                                <th class="px-4 py-2 font-semibold">Proceso</th>
                                <th class="px-4 py-2 font-semibold">Producto</th>
                                <th class="px-4 py-2 font-semibold">Fecha</th>
                                <th class="px-4 py-2 font-semibold">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($ultimas as $i)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2.5 font-mono text-xs font-semibold">
                                        <a href="{{ route('inspecciones.show', $i) }}" class="text-pc-700 hover:underline">
                                            {{ $i->code }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $i->process->name }}</td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $i->lot->product?->code ?? '-' }}</td>
                                    <td class="px-4 py-2.5 text-slate-500">{{ $i->fecha->format('d/m/Y') }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="badge {{ $i->badge_color }}">{{ $i->estado }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Lista en celular --}}
                <ul class="divide-y divide-slate-100 sm:hidden">
                    @foreach ($ultimas as $i)
                        <li>
                            <a href="{{ route('inspecciones.show', $i) }}" class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="min-w-0">
                                    <span class="block font-mono text-xs font-semibold text-pc-700">{{ $i->code }}</span>
                                    <span class="block truncate text-sm text-slate-700">
                                        {{ $i->process->name }} &middot; {{ $i->lot->product?->code ?? 'sin producto' }}
                                    </span>
                                    <span class="block text-xs text-slate-400">{{ $i->fecha->format('d/m/Y') }}</span>
                                </span>
                                <span class="badge shrink-0 {{ $i->badge_color }}">{{ $i->estado }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Lotes bloqueados --}}
        <div class="tarjeta">
            <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                <h2 class="text-sm font-semibold text-slate-900">Lotes bloqueados</h2>
                <p class="mt-0.5 text-xs text-slate-500">No pueden pasar al proceso siguiente</p>
            </div>

            @if ($bloqueados->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-emerald-700 sm:px-5">
                    Ningun lote bloqueado.
                </p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($bloqueados as $lote)
                        <li>
                            <a href="{{ route('lotes.show', $lote) }}" class="block px-4 py-3 hover:bg-slate-50 sm:px-5">
                                <span class="block font-mono text-xs font-semibold text-red-700">{{ $lote->code }}</span>
                                <span class="block text-sm text-slate-700">{{ $lote->product?->code ?? 'sin producto' }}</span>
                                <span class="block text-xs text-slate-400">{{ $lote->fecha->format('d/m/Y') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
