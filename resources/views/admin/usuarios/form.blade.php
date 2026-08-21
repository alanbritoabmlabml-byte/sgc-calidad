@extends('layouts.app')

@section('titulo', $usuario->exists ? 'Editar '.$usuario->name : 'Nuevo usuario')

@section('encabezado')
    <nav class="mb-2 text-xs text-slate-500">
        <a href="{{ route('admin.usuarios.index') }}" class="hover:underline">Usuarios</a> /
        {{ $usuario->exists ? $usuario->name : 'Nuevo' }}
    </nav>
    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
        {{ $usuario->exists ? 'Editar usuario' : 'Nuevo usuario' }}
    </h1>
@endsection

@section('contenido')
    <form method="POST"
          action="{{ $usuario->exists ? route('admin.usuarios.update', $usuario) : route('admin.usuarios.store') }}"
          class="max-w-xl">
        @csrf
        @if ($usuario->exists)
            @method('PUT')
        @endif

        <div class="tarjeta p-4 sm:p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="etiqueta">Nombre *</label>
                    <input id="name" name="name" class="campo" required value="{{ old('name', $usuario->name) }}">
                </div>

                <div class="sm:col-span-2">
                    <label for="email" class="etiqueta">Correo *</label>
                    <input id="email" name="email" type="email" class="campo" required
                           value="{{ old('email', $usuario->email) }}">
                </div>

                <div>
                    <label for="role" class="etiqueta">Rol *</label>
                    <select id="role" name="role" class="campo" required
                            @disabled($usuario->exists && $usuario->is(auth()->user()))>
                        @foreach (\App\Models\User::ROLES as $valor => $texto)
                            <option value="{{ $valor }}" @selected(old('role', $usuario->role) === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                    @if ($usuario->exists && $usuario->is(auth()->user()))
                        <p class="mt-1 text-xs text-amber-700">
                            No podes cambiar tu propio rol ni desactivarte.
                        </p>
                    @endif
                </div>

                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="active" value="1"
                               @checked(old('active', $usuario->active ?? true))
                               @disabled($usuario->exists && $usuario->is(auth()->user()))
                               class="h-4 w-4 rounded border-slate-300 text-pc-600 focus:ring-pc-500">
                        Activo
                    </label>
                </div>

                <div>
                    <label for="password" class="etiqueta">
                        Contrasena {{ $usuario->exists ? '' : '*' }}
                    </label>
                    <input id="password" name="password" type="password" class="campo"
                           autocomplete="new-password" @required(! $usuario->exists)>
                    @if ($usuario->exists)
                        <p class="mt-1 text-xs text-slate-400">Dejala vacia para no cambiarla.</p>
                    @else
                        <p class="mt-1 text-xs text-slate-400">Minimo 8 caracteres.</p>
                    @endif
                </div>

                <div>
                    <label for="password_confirmation" class="etiqueta">Repetir contrasena</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="campo"
                           autocomplete="new-password" @required(! $usuario->exists)>
                </div>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="btn-primario">
                {{ $usuario->exists ? 'Guardar cambios' : 'Crear usuario' }}
            </button>
            <a href="{{ route('admin.usuarios.index') }}" class="btn-secundario">Cancelar</a>
        </div>
    </form>
@endsection
