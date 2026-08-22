@extends('layouts.impresion')

@section('titulo', 'Boleta '.$inspeccion->code)

@php
    use App\Support\Permisos;

    $yo = auth()->user();
    $faltan = $inspeccion->published_at === null ? $inspeccion->datosFaltantes() : [];
@endphp

@section('barra-titulo')
    <p class="font-mono text-sm font-bold text-slate-900">{{ $inspeccion->code }}</p>
    <p class="truncate text-xs text-slate-500">
        {{ $inspeccion->process->name }} &middot;
        <a href="{{ route('lotes.show', $inspeccion->lot) }}" class="font-mono hover:underline">{{ $inspeccion->lot->code }}</a>
        &middot; <span class="badge {{ $inspeccion->badge_color }}">{{ $inspeccion->estado }}</span>
    </p>
@endsection

@section('barra-acciones')
    <a href="{{ route('dashboard') }}" class="btn-secundario">Tablero</a>

    @if ($yo->puede(Permisos::INSPECCIONES_EDITAR) && ! $inspeccion->published_at)
        <a href="{{ route('inspecciones.edit', $inspeccion) }}" class="btn-secundario">Editar</a>
    @endif

    @if ($inspeccion->estaPublicada())
        @if ($yo->puede(Permisos::ETIQUETAS_IMPRIMIR))
            <a href="{{ route('inspecciones.etiquetas', $inspeccion) }}" class="btn-secundario">Etiquetas QR</a>
        @endif
        <a href="{{ $inspeccion->urlPublica() }}" target="_blank" rel="noopener" class="btn-secundario">
            Ver certificado
        </a>
    @endif

    @if ($inspeccion->published_at)
        @if ($yo->puede(Permisos::INSPECCIONES_ANULAR))
            <form method="POST" action="{{ route('inspecciones.despublicar', $inspeccion) }}"
                  onsubmit="return confirm('Anular la emisión deja el certificado público inaccesible y los QR ya impresos dejarán de resolver. ¿Continuar?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-secundario">Anular emisión</button>
            </form>
        @endif
    @elseif ($yo->puede(Permisos::INSPECCIONES_EMITIR))
        <form method="POST" action="{{ route('inspecciones.publicar', $inspeccion) }}">
            @csrf
            <button type="submit" class="btn-primario" @disabled($faltan !== [])>Emitir boleta</button>
        </form>
    @endif
@endsection

@section('contenido')

    {{-- Avisos: no salen impresos --}}
    <div class="no-imprimir mb-4">
        <x-avisos />

        @if ($inspeccion->published_at === null)
            @if ($faltan !== [])
                <div class="rounded-lg border-l-4 border-rojo-500 bg-rojo-50 p-4">
                    <p class="text-sm font-bold text-rojo-900">
                        No se puede emitir: faltan {{ count($faltan) }}
                        {{ Str::plural('dato', count($faltan)) }}
                    </p>
                    <p class="mt-1 text-sm text-rojo-800">
                        Una boleta emitida es el certificado de calidad de un producto vendido:
                        no puede salir con campos en blanco.
                    </p>
                    <ul class="mt-2 list-disc space-y-0.5 pl-5 text-sm text-rojo-800">
                        @foreach ($faltan as $dato)
                            <li>{{ $dato }}</li>
                        @endforeach
                    </ul>
                    @if ($yo->puede(Permisos::INSPECCIONES_EDITAR))
                        <a href="{{ route('inspecciones.edit', $inspeccion) }}"
                           class="btn-primario mt-3 !px-3 !py-1.5 text-xs">Completar la inspección</a>
                    @endif
                </div>
            @else
                <div class="rounded-lg border-l-4 border-amber-500 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-900">Boleta lista, sin emitir</p>
                    <p class="mt-1 text-sm text-amber-800">
                        Los datos están completos. El código QR todavía no resuelve: emití la boleta
                        para que el certificado quede disponible para el cliente. Una vez emitida no se
                        puede editar sin anular la emisión.
                    </p>
                </div>
            @endif
        @endif

        @if ($inspeccion->estado === \App\Models\Inspection::RECHAZADO)
            <div class="mt-3 rounded-lg border-l-4 border-rojo-600 bg-rojo-50 p-4">
                <p class="text-sm font-bold text-rojo-900">PRODUCTO NO CONFORME</p>
                <p class="mt-1 text-sm text-rojo-800">
                    Este resultado bloquea el avance del lote a los procesos siguientes.
                </p>
            </div>
        @endif
    </div>

    @include('inspecciones._boleta', ['publico' => false])
@endsection
