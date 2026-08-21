@extends('layouts.app')

@section('titulo', 'Maquinas')

@section('encabezado')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Maquinas</h1>
            <p class="mt-1 text-sm text-slate-500">Telares, extrusoras, impresoras y maquinas de corte y costura.</p>
        </div>
        <a href="{{ route('admin.maquinas.create') }}" class="btn-primario">Nueva maquina</a>
    </div>
@endsection

@section('contenido')
    @include('admin._nav')

    <div class="space-y-5">
        @forelse ($maquinas as $tipo => $grupo)
            <div class="tarjeta overflow-hidden">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 sm:px-5">
                    <h2 class="text-sm font-bold text-slate-900">{{ ucfirst($tipo) }}</h2>
                </div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($grupo as $m)
                        <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ $m->code }}
                                    @unless ($m->active)
                                        <span class="badge ml-1 bg-slate-200 text-slate-600 ring-slate-300">inactiva</span>
                                    @endunless
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ $m->name ?? 'sin nombre' }} &middot; {{ $m->sector->name }}
                                </p>
                            </div>
                            <a href="{{ route('admin.maquinas.edit', $m) }}"
                               class="shrink-0 text-xs font-semibold text-pc-700 hover:underline">Editar</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="tarjeta p-10 text-center">
                <p class="text-sm text-slate-500">No hay maquinas cargadas.</p>
            </div>
        @endforelse
    </div>
@endsection
