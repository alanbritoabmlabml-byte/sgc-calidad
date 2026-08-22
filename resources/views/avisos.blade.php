@extends('layouts.app')

@section('titulo', 'Avisos')

@section('encabezado')
    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Avisos</h1>
    <p class="mt-1 text-sm text-slate-500">
        Lo que quedó a medio camino. El caso más importante es la boleta cerrada sin emitir:
        mientras no se emita, el QR de su etiqueta no resuelve el certificado.
    </p>
@endsection

@section('contenido')

    @if ($avisos->isEmpty())
        <div class="tarjeta p-10 text-center">
            <p class="text-base font-semibold text-emerald-700">No hay nada pendiente.</p>
            <p class="mt-1 text-sm text-slate-500">
                Todas las boletas están emitidas y ningún lote quedó sin inspección.
            </p>
        </div>
    @else
        {{-- Resumen --}}
        <div class="anim-grilla grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($avisos as $aviso)
                <a href="{{ $aviso['ruta'] }}"
                   @class([
                       'tarjeta block border-l-4 p-4 transition hover:shadow-md',
                       'border-rojo-500' => $aviso['tono'] === 'rojo',
                       'border-amber-500' => $aviso['tono'] === 'ambar',
                   ])>
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900">{{ $aviso['titulo'] }}</p>
                        <span @class([
                            'shrink-0 rounded-full px-2.5 py-0.5 text-sm font-bold text-white',
                            'bg-rojo-500' => $aviso['tono'] === 'rojo',
                            'bg-amber-500' => $aviso['tono'] === 'ambar',
                        ])>{{ $aviso['cantidad'] }}</span>
                    </div>
                    <p class="mt-1.5 text-xs leading-relaxed text-slate-500">{{ $aviso['detalle'] }}</p>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ===== Boletas sin emitir ===== --}}
    @if ($sinEmitir->isNotEmpty())
        <div class="tarjeta mt-6 overflow-hidden">
            <div class="border-b border-slate-200 bg-rojo-50 px-4 py-3 sm:px-5">
                <h2 class="text-sm font-bold text-rojo-900">
                    Boletas cerradas sin emitir ({{ $sinEmitir->count() }})
                </h2>
                <p class="mt-0.5 text-xs text-rojo-800">
                    La inspección está lista. Falta emitirla para que el certificado quede disponible.
                </p>
            </div>

            <ul class="divide-y divide-slate-100">
                @foreach ($sinEmitir as $i)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-5">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">
                                <a href="{{ route('inspecciones.show', $i) }}"
                                   class="font-mono text-pc-700 hover:underline">{{ $i->code }}</a>
                                <span class="ml-1 font-normal text-slate-500">{{ $i->process->name }}</span>
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                Lote {{ $i->lot->code }}
                                @if ($i->lot->product) &middot; {{ $i->lot->product->code }} @endif
                                &middot; {{ $i->fecha->format('d/m/Y') }}
                                &middot; hace {{ $i->fecha->diffForHumans(null, true) }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="badge {{ $i->badge_color }}">{{ $i->estado }}</span>
                            @if (auth()->user()->puede(\App\Support\Permisos::INSPECCIONES_EMITIR))
                                <form method="POST" action="{{ route('inspecciones.publicar', $i) }}">
                                    @csrf
                                    <button type="submit" class="btn-primario !px-3 !py-1.5 text-xs">Emitir</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ===== Pendientes de cierre ===== --}}
    @if ($pendientes->isNotEmpty())
        <div class="tarjeta mt-6 overflow-hidden">
            <div class="border-b border-slate-200 bg-amber-50 px-4 py-3 sm:px-5">
                <h2 class="text-sm font-bold text-amber-900">
                    Inspecciones pendientes de cierre ({{ $pendientes->count() }})
                </h2>
                <p class="mt-0.5 text-xs text-amber-800">
                    Hay mediciones cargadas pero nadie definió el estado de inspección.
                </p>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($pendientes as $i)
                    <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                        <div class="min-w-0">
                            <a href="{{ route('inspecciones.show', $i) }}"
                               class="font-mono text-sm font-semibold text-pc-700 hover:underline">{{ $i->code }}</a>
                            <p class="truncate text-xs text-slate-500">
                                {{ $i->process->name }} &middot; lote {{ $i->lot->code }}
                                &middot; {{ $i->fecha->format('d/m/Y') }}
                            </p>
                        </div>
                        @if (auth()->user()->puede(\App\Support\Permisos::INSPECCIONES_EDITAR))
                            <a href="{{ route('inspecciones.edit', $i) }}" class="btn-secundario !px-3 !py-1.5 text-xs">
                                Completar
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ===== Lotes sin inspeccion ===== --}}
    @if ($lotesSinInspeccion->isNotEmpty())
        <div class="tarjeta mt-6 overflow-hidden">
            <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                <h2 class="text-sm font-bold text-slate-900">
                    Lotes sin ninguna inspección ({{ $lotesSinInspeccion->count() }})
                </h2>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($lotesSinInspeccion as $lote)
                    <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                        <div class="min-w-0">
                            <a href="{{ route('lotes.show', $lote) }}"
                               class="font-mono text-sm font-semibold text-pc-700 hover:underline">{{ $lote->code }}</a>
                            <p class="truncate text-xs text-slate-500">
                                {{ $lote->product?->code ?? 'sin producto' }}
                                &middot; {{ $lote->sector->name }}
                                &middot; {{ $lote->fecha->format('d/m/Y') }}
                            </p>
                        </div>
                        <a href="{{ route('lotes.show', $lote) }}" class="btn-secundario !px-3 !py-1.5 text-xs">Ver</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
