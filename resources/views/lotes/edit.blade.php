@extends('layouts.app')

@section('titulo', 'Editar lote '.$lote->code)

@section('encabezado')
    <nav class="mb-2 text-xs text-slate-500">
        <a href="{{ route('lotes.index') }}" class="hover:underline">Lotes</a> /
        <a href="{{ route('lotes.show', $lote) }}" class="hover:underline">{{ $lote->code }}</a> / Editar
    </nav>
    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
        Editar lote <span class="font-mono">{{ $lote->code }}</span>
    </h1>
@endsection

@section('contenido')
    <form method="POST" action="{{ route('lotes.update', $lote) }}">
        @csrf
        @method('PUT')

        @include('lotes._form')

        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="btn-primario">Guardar cambios</button>
            <a href="{{ route('lotes.show', $lote) }}" class="btn-secundario">Cancelar</a>
        </div>
    </form>
@endsection
