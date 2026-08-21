@extends('layouts.impresion')

@section('titulo', 'Boleta '.$inspeccion->code)

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

    @if (auth()->user()->puedeEditar() && ! $inspeccion->published_at)
        <a href="{{ route('inspecciones.edit', $inspeccion) }}" class="btn-secundario">Editar</a>
    @endif

    @if ($inspeccion->estaPublicada())
        <a href="{{ route('inspecciones.etiquetas', $inspeccion) }}" class="btn-secundario">Etiquetas QR</a>
        <a href="{{ $inspeccion->urlPublica() }}" target="_blank" rel="noopener" class="btn-secundario">
            Ver certificado
        </a>
    @endif

    @if (auth()->user()->puedeEditar())
        @if ($inspeccion->published_at)
            <form method="POST" action="{{ route('inspecciones.despublicar', $inspeccion) }}"
                  onsubmit="return confirm('Anular la emision deja el certificado publico inaccesible y los QR ya impresos dejaran de resolver. Continuar?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-secundario">Anular emision</button>
            </form>
        @else
            <form method="POST" action="{{ route('inspecciones.publicar', $inspeccion) }}">
                @csrf
                <button type="submit" class="btn-primario">Emitir boleta</button>
            </form>
        @endif
    @endif
@endsection

@section('contenido')

    {{-- Avisos: no salen impresos --}}
    <div class="no-imprimir mb-4">
        <x-avisos />

        @unless ($inspeccion->published_at)
            <div class="rounded-lg bg-amber-50 p-4 ring-1 ring-amber-200">
                <p class="text-sm font-semibold text-amber-900">Boleta sin emitir</p>
                <p class="mt-1 text-sm text-amber-800">
                    El codigo QR todavia no resuelve. Emiti la boleta para que el certificado
                    quede disponible para el cliente. Una vez emitida no se puede editar sin anular la emision.
                </p>
            </div>
        @endunless

        @if ($inspeccion->estado === \App\Models\Inspection::RECHAZADO)
            <div class="mt-3 rounded-lg bg-red-50 p-4 ring-1 ring-red-200">
                <p class="text-sm font-semibold text-red-900">Lote no conforme</p>
                <p class="mt-1 text-sm text-red-800">
                    Este resultado bloquea el avance del lote a los procesos siguientes.
                </p>
            </div>
        @endif
    </div>

    @include('inspecciones._boleta', ['publico' => false])
@endsection
