@extends('layouts.app')

@section('titulo', 'Editar '.$inspeccion->code)

@section('encabezado')
    <nav class="mb-2 text-xs text-slate-500">
        <a href="{{ route('inspecciones.index') }}" class="hover:underline">Inspecciones</a> /
        <a href="{{ route('inspecciones.show', $inspeccion) }}" class="hover:underline">{{ $inspeccion->code }}</a> /
        Editar
    </nav>
    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
        Editar boleta <span class="font-mono">{{ $inspeccion->code }}</span>
    </h1>
    <p class="mt-1 text-sm text-slate-500">
        {{ $proceso->name }} &middot; lote
        <a href="{{ route('lotes.show', $lote) }}" class="font-mono font-semibold text-pc-700 hover:underline">{{ $lote->code }}</a>
    </p>
@endsection

@section('contenido')
    <form method="POST" action="{{ route('inspecciones.update', $inspeccion) }}">
        @csrf
        @method('PUT')

        @include('inspecciones._form')

        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="btn-primario">Guardar cambios</button>
            <a href="{{ route('inspecciones.show', $inspeccion) }}" class="btn-secundario">Cancelar</a>
        </div>
    </form>
@endsection
