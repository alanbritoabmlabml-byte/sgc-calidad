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

@php
    $esYo = $usuario->exists && $usuario->is(auth()->user());
    $actuales = old('permissions', $usuario->exists ? $usuario->permisos() : \App\Support\Permisos::preset(\App\Models\User::CALIDAD));
@endphp

<form method="POST"
      action="{{ $usuario->exists ? route('admin.usuarios.update', $usuario) : route('admin.usuarios.store') }}"
      x-data="{
          rol: '{{ old('role', $usuario->role ?? \App\Models\User::CALIDAD) }}',
          presets: @js($presets),
          descripciones: @js(\App\Models\User::ROLES_DESCRIPCION),
          permisos: @js(array_values((array) $actuales)),

          /* Al elegir un rol se marcan sus permisos de golpe. Despues se puede
             ajustar casilla por casilla: el rol es un punto de partida. */
          aplicarPreset() {
              this.permisos = [...(this.presets[this.rol] ?? [])];
          },

          tiene(p) { return this.permisos.includes(p); },

          alternar(p) {
              const i = this.permisos.indexOf(p);
              i === -1 ? this.permisos.push(p) : this.permisos.splice(i, 1);
          },

          /* Marca o desmarca todo un modulo de una vez. */
          modulo(claves) {
              const faltan = claves.filter(c => !this.tiene(c));
              if (faltan.length) {
                  this.permisos = [...new Set([...this.permisos, ...claves])];
              } else {
                  this.permisos = this.permisos.filter(p => !claves.includes(p));
              }
          },

          enModulo(claves) { return claves.filter(c => this.tiene(c)).length; },

          get esAdmin() { return this.rol === 'admin'; },
      }">
    @csrf
    @if ($usuario->exists)
        @method('PUT')
    @endif

    <div class="grid gap-5 lg:grid-cols-3">

        {{-- ===== Datos ===== --}}
        <div class="tarjeta p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-slate-900">Datos del usuario</h2>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="name" class="etiqueta">Nombre completo *</label>
                    <input id="name" name="name" class="campo" required
                           value="{{ old('name', $usuario->name) }}" placeholder="Ej. María Fernández">
                    <p class="mt-1 text-xs text-slate-400">
                        Es el nombre que se imprime como responsable en la boleta. Solo letras.
                    </p>
                </div>

                <div>
                    <label for="email" class="etiqueta">Correo *</label>
                    <input id="email" name="email" type="email" class="campo" required
                           value="{{ old('email', $usuario->email) }}">
                </div>

                <div>
                    <label for="role" class="etiqueta">Rol *</label>
                    <select id="role" name="role" class="campo" required x-model="rol"
                            @change="aplicarPreset()" @disabled($esYo)>
                        @foreach (\App\Models\User::ROLES as $valor => $texto)
                            <option value="{{ $valor }}">{{ $texto }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500" x-text="descripciones[rol] ?? ''"></p>
                    @if ($esYo)
                        <p class="mt-1 text-xs text-amber-700">
                            No podés cambiar tu propio rol ni tus permisos: evita que el sistema
                            quede sin nadie que pueda configurarlo.
                        </p>
                    @endif
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="active" value="1"
                           @checked(old('active', $usuario->active ?? true)) @disabled($esYo)
                           class="h-4 w-4 rounded border-slate-300 text-pc-700 focus:ring-pc-500">
                    Usuario activo
                </label>

                <div class="border-t border-slate-200 pt-4">
                    <div>
                        <label for="password" class="etiqueta">
                            Contraseña {{ $usuario->exists ? '' : '*' }}
                        </label>
                        <input id="password" name="password" type="password" class="campo"
                               autocomplete="new-password" @required(! $usuario->exists)>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ $usuario->exists ? 'Dejala vacía para no cambiarla.' : 'Mínimo 8 caracteres.' }}
                        </p>
                    </div>

                    <div class="mt-4">
                        <label for="password_confirmation" class="etiqueta">Repetir contraseña</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="campo"
                               autocomplete="new-password" @required(! $usuario->exists)>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Permisos ===== --}}
        <div class="lg:col-span-2">
            <div class="tarjeta overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 sm:px-5">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Permisos</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            El rol marca un punto de partida. Desde acá se ajusta cada permiso por separado.
                        </p>
                    </div>
                    <span class="badge bg-pc-50 text-pc-800 ring-pc-200">
                        <span x-text="esAdmin ? 'todos' : permisos.length"></span>
                        <span x-show="! esAdmin">&nbsp;de {{ count(\App\Support\Permisos::todos()) }}</span>
                    </span>
                </div>

                {{-- El administrador siempre tiene todo --}}
                <div x-show="esAdmin" x-cloak class="border-b border-pc-200 bg-pc-50 px-4 py-3 sm:px-5">
                    <p class="text-sm font-medium text-pc-900">Acceso total</p>
                    <p class="mt-0.5 text-xs text-pc-800">
                        El rol Administrador tiene todos los permisos, incluidos los que se agreguen
                        en el futuro. No hace falta marcar nada.
                    </p>
                </div>

                <div x-show="! esAdmin" x-cloak class="divide-y divide-slate-100">
                    @foreach ($catalogo as $modulo => $permisos)
                        @php $claves = array_keys($permisos); @endphp

                        <div class="px-4 py-3.5 sm:px-5">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-slate-800">{{ $modulo }}</h3>
                                <button type="button" @click="modulo(@js($claves))"
                                        class="shrink-0 text-xs font-semibold text-pc-700 hover:underline"
                                        x-text="enModulo(@js($claves)) === {{ count($claves) }} ? 'Quitar todo' : 'Marcar todo'">
                                </button>
                            </div>

                            <div class="mt-2 grid gap-1.5 sm:grid-cols-2">
                                @foreach ($permisos as $clave => $descripcion)
                                    <label class="flex cursor-pointer items-start gap-2 rounded-lg p-2 hover:bg-slate-50">
                                        <input type="checkbox" name="permissions[]" value="{{ $clave }}"
                                               :checked="tiene('{{ $clave }}')"
                                               @change="alternar('{{ $clave }}')"
                                               @disabled($esYo)
                                               class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-pc-700 focus:ring-pc-500">
                                        <span class="min-w-0">
                                            <span class="block text-sm leading-tight text-slate-800">{{ $descripcion }}</span>
                                            <span class="block font-mono text-[10px] leading-tight text-slate-400">{{ $clave }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
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
