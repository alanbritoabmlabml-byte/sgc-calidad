@extends('layouts.app')

@section('titulo', 'Usuarios')

@section('encabezado')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Usuarios</h1>
            <p class="mt-1 text-sm text-slate-500">
                Administrador configura, Calidad carga inspecciones, Solo lectura consulta y reimprime.
            </p>
        </div>
        <a href="{{ route('admin.usuarios.create') }}" class="btn-primario">Nuevo usuario</a>
    </div>
@endsection

@section('contenido')
    @include('admin._nav')

    <div class="tarjeta overflow-hidden">
        <ul class="divide-y divide-slate-100">
            @foreach ($usuarios as $u)
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-5">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-800">
                            {{ $u->name }}
                            @if ($u->is(auth()->user()))
                                <span class="badge ml-1 bg-pc-50 text-pc-800 ring-pc-200">vos</span>
                            @endif
                            @unless ($u->active)
                                <span class="badge ml-1 bg-slate-200 text-slate-600 ring-slate-300">desactivado</span>
                            @endunless
                        </p>
                        <p class="truncate text-xs text-slate-500">{{ $u->email }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <span @class([
                            'badge',
                            'bg-pc-100 text-pc-800 ring-pc-300' => $u->role === \App\Models\User::ADMIN,
                            'bg-emerald-100 text-emerald-800 ring-emerald-300' => $u->role === \App\Models\User::CALIDAD,
                            'bg-slate-100 text-slate-700 ring-slate-300' => $u->role === \App\Models\User::LECTURA,
                        ])>{{ $u->role_label }}</span>
                        <a href="{{ route('admin.usuarios.edit', $u) }}"
                           class="text-xs font-semibold text-pc-700 hover:underline">Editar</a>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endsection
