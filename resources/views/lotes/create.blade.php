@extends('layouts.app')

@section('titulo', 'Nuevo lote')

@section('encabezado')
    <nav class="mb-2 text-xs text-slate-500">
        <a href="{{ route('lotes.index') }}" class="hover:underline">Lotes</a> / Nuevo
    </nav>
    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Nuevo lote de produccion</h1>
@endsection

@section('contenido')
    <form method="POST" action="{{ route('lotes.store') }}">
        @csrf

        @include('lotes._form', ['lote' => new \App\Models\Lot(['sector_id' => $sector->id])])

        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="btn-primario">Crear lote</button>
            <a href="{{ route('lotes.index') }}" class="btn-secundario">Cancelar</a>
        </div>
    </form>
@endsection
