@extends('layouts.app')

@section('titulo', 'Inspeccion de '.$proceso->name)

@section('encabezado')
    <nav class="mb-2 text-xs text-slate-500">
        <a href="{{ route('lotes.index') }}" class="hover:underline">Lotes</a> /
        <a href="{{ route('lotes.show', $lote) }}" class="hover:underline">{{ $lote->code }}</a> /
        {{ $proceso->name }}
    </nav>
    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
        Inspeccion de {{ $proceso->name }}
    </h1>
    <p class="mt-1 text-sm text-slate-500">
        Lote <span class="font-mono font-semibold">{{ $lote->code }}</span>
        @if ($lote->product) &middot; {{ $lote->product->code }} @endif
        @if ($lote->nro_tarjeta) &middot; tarjeta {{ $lote->nro_tarjeta }} @endif
    </p>
@endsection

@section('contenido')
    <form method="POST" action="{{ route('inspecciones.store', [$lote, $proceso]) }}">
        @csrf

        @include('inspecciones._form')

        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="btn-primario">Guardar inspeccion</button>
            <a href="{{ route('lotes.show', $lote) }}" class="btn-secundario">Cancelar</a>
        </div>
        <p class="mt-2 text-xs text-slate-400">
            Al guardar, la boleta queda registrada pero sin emitir. El QR recien resuelve
            el certificado cuando la emitas desde la boleta.
        </p>
    </form>
@endsection
