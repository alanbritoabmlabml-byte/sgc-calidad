@extends('layouts.app')

@section('titulo', $plantilla->name)

@section('encabezado')
    <nav class="mb-2 text-xs text-slate-500">
        <a href="{{ route('admin.plantillas.index') }}" class="hover:underline">Plantillas</a> /
        {{ $plantilla->process->name }}
    </nav>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ $plantilla->name }}</h1>
                @if ($plantilla->active)
                    <span class="badge bg-emerald-100 text-emerald-800 ring-emerald-300">vigente</span>
                @else
                    <span class="badge bg-slate-200 text-slate-600 ring-slate-300">inactiva</span>
                @endif
            </div>
            <p class="mt-1 text-sm text-slate-500">
                {{ $plantilla->process->sector->name }} &middot; {{ $plantilla->process->name }} &middot;
                Rev:{{ $plantilla->revision }}
                @if ($usos > 0)
                    &middot; usada en {{ $usos }} {{ Str::plural('boleta', $usos) }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($puedeCrear)
                <form method="POST" action="{{ route('admin.plantillas.duplicar', $plantilla) }}">
                    @csrf
                    <button type="submit" class="btn-secundario">Crear revisión nueva</button>
                </form>
            @endif
            @if ($puedeActivar && ! $plantilla->active)
                <form method="POST" action="{{ route('admin.plantillas.activar', $plantilla) }}">
                    @csrf
                    <button type="submit" class="btn-primario">Activar</button>
                </form>
            @endif
        </div>
    </div>
@endsection

@section('contenido')
    @include('admin._nav')

    {{-- Registro de autoría: quién creó la revisión y quién la puso vigente --}}
    <div class="tarjeta mb-5 p-4 sm:p-5">
        <h2 class="text-sm font-semibold text-slate-900">Registro</h2>
        <dl class="mt-2 grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
            <div class="flex justify-between gap-3 border-b border-slate-100 py-1">
                <dt class="text-xs text-slate-500">Creada</dt>
                <dd class="text-right text-sm text-slate-800">
                    {{ $plantilla->created_at->format('d/m/Y H:i') }}
                    @if ($plantilla->creador)
                        <span class="text-slate-500">por {{ $plantilla->creador->name }}</span>
                    @endif
                </dd>
            </div>
            <div class="flex justify-between gap-3 border-b border-slate-100 py-1">
                <dt class="text-xs text-slate-500">Puesta vigente</dt>
                <dd class="text-right text-sm text-slate-800">
                    @if ($plantilla->activada_at)
                        {{ $plantilla->activada_at->format('d/m/Y H:i') }}
                        @if ($plantilla->activadaPor)
                            <span class="text-slate-500">por {{ $plantilla->activadaPor->name }}</span>
                        @endif
                    @else
                        <span class="text-slate-400">nunca</span>
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    @unless ($puedeEditar)
        <div class="mb-5 rounded-lg border-l-4 border-pc-500 bg-pc-50 p-4">
            <p class="text-sm font-semibold text-pc-900">Solo lectura</p>
            <p class="mt-1 text-sm text-pc-800">
                Tu usuario puede <strong>crear</strong> plantillas y revisiones nuevas, pero no modificar
                ni eliminar las existentes. Es a propósito: una especificación vigente no se retoca,
                porque cambiaría cómo se leen las boletas ya emitidas. Para cambiar una tolerancia usá
                <strong>Crear revisión nueva</strong>: la revisión vieja queda intacta y las inspecciones
                nuevas usan la siguiente.
            </p>
        </div>
    @endunless

    @if ($usos > 0 && $puedeEditar)
        <div class="mb-5 rounded-lg border-l-4 border-amber-500 bg-amber-50 p-4">
            <p class="text-sm font-semibold text-amber-900">Esta plantilla ya tiene boletas emitidas</p>
            <p class="mt-1 text-sm text-amber-800">
                Cambiar una especificación acá alteraría cómo se muestran boletas ya emitidas.
                Si necesitás cambiar tolerancias, usá <strong>Crear revisión nueva</strong>.
            </p>
        </div>
    @endif

    {{-- ===== Datos de la plantilla ===== --}}
    @if ($puedeEditar)
        <form method="POST" action="{{ route('admin.plantillas.update', $plantilla) }}" class="tarjeta mb-5 p-4 sm:p-5">
            @csrf
            @method('PUT')
            <input type="hidden" name="process_id" value="{{ $plantilla->process_id }}">

            <h2 class="text-sm font-semibold text-slate-900">Datos del formulario</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <label for="name" class="etiqueta">Nombre *</label>
                    <input id="name" name="name" class="campo" required value="{{ old('name', $plantilla->name) }}">
                </div>
                <div>
                    <label for="revision" class="etiqueta">Revisión *</label>
                    <input id="revision" name="revision" class="campo" required value="{{ old('revision', $plantilla->revision) }}">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="muestras_max" class="etiqueta">Máx. *</label>
                        <input id="muestras_max" name="muestras_max" type="number" min="1" max="13" class="campo" required
                               value="{{ old('muestras_max', $plantilla->muestras_max) }}">
                    </div>
                    <div>
                        <label for="muestras_default" class="etiqueta">Defecto *</label>
                        <input id="muestras_default" name="muestras_default" type="number" min="1" max="13" class="campo" required
                               value="{{ old('muestras_default', $plantilla->muestras_default) }}">
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn-secundario">Guardar datos</button>
            </div>
        </form>
    @else
        <div class="tarjeta mb-5 p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-slate-900">Datos del formulario</h2>
            <dl class="mt-3 grid gap-x-6 text-sm sm:grid-cols-3">
                <x-dato titulo="Nombre" :valor="$plantilla->name" />
                <x-dato titulo="Revisión" :valor="$plantilla->revision" />
                <x-dato titulo="Muestras" :valor="$plantilla->muestras_default.' de hasta '.$plantilla->muestras_max" />
            </dl>
        </div>
    @endif

    {{-- ===== Parametros ===== --}}
    <div class="tarjeta mb-5 overflow-hidden">
        <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
            <h2 class="text-sm font-semibold text-slate-900">
                Parámetros de ensayo
                <span class="font-normal text-slate-400">({{ $plantilla->parameters->count() }})</span>
            </h2>
            <p class="mt-0.5 text-xs text-slate-500">
                {{ $puedeEditar ? 'Toca un parámetro para editarlo.' : 'Especificación de cada característica.' }}
            </p>
        </div>

        @if ($plantilla->parameters->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-slate-500 sm:px-5">
                Todavía no hay parámetros. Agregá el primero con el formulario de abajo.
            </p>
        @elseif ($puedeEditar)
            <ul class="divide-y divide-slate-100">
                @foreach ($plantilla->parameters as $p)
                    <li x-data="{ abierto: false }">
                        <button type="button" @click="abierto = !abierto"
                                class="flex w-full flex-wrap items-center justify-between gap-3 px-4 py-3 text-left hover:bg-slate-50 sm:px-5">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-800">
                                    <span class="mr-1 text-slate-400">{{ $p->orden }}.</span>
                                    {{ $p->etiqueta }}
                                    @if ($p->requerido)
                                        <span class="badge ml-1 bg-rojo-50 text-rojo-700 ring-rojo-200">crítico</span>
                                    @endif
                                </span>
                                <span class="block text-xs text-slate-500">
                                    {{ \App\Models\TestParameter::MODOS[$p->spec_modo] }}
                                    &middot; {{ $p->specTexto() }}
                                    @if ($p->spec_desde_producto)
                                        &middot; objetivo del producto ({{ $p->spec_desde_producto }})
                                    @endif
                                    &middot; {{ $p->muestras }} {{ Str::plural('muestra', $p->muestras) }}
                                </span>
                            </span>
                            <span class="shrink-0 text-xs text-pc-700" x-text="abierto ? 'Cerrar' : 'Editar'"></span>
                        </button>

                        <div x-show="abierto" x-cloak class="border-t border-slate-100 bg-slate-50/50 px-4 py-4 sm:px-5">
                            <form method="POST" action="{{ route('admin.parametros.update', $p) }}">
                                @csrf
                                @method('PUT')

                                @include('admin.plantillas._parametro-campos', ['p' => $p, 'prefijo' => 'p'.$p->id])

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button type="submit" class="btn-primario">Guardar parámetro</button>
                                    <button type="button" @click="abierto = false" class="btn-secundario">Cancelar</button>
                                </div>
                            </form>

                            @if ($puedeEliminar)
                                <form method="POST" action="{{ route('admin.parametros.destroy', $p) }}" class="mt-3"
                                      onsubmit="return confirm('¿Eliminar el parámetro {{ $p->label }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-rojo-700 hover:underline">
                                        Eliminar este parámetro
                                    </button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            {{-- Solo lectura: tabla de especificaciones --}}
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 font-semibold">Característica</th>
                            <th class="px-4 py-2 font-semibold">Modo</th>
                            <th class="px-4 py-2 font-semibold">Especificación</th>
                            <th class="px-4 py-2 text-right font-semibold">Muestras</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($plantilla->parameters as $p)
                            <tr>
                                <td class="px-4 py-2.5 font-medium text-slate-800">
                                    <span class="mr-1 text-slate-400">{{ $p->orden }}.</span>{{ $p->etiqueta }}
                                    @if ($p->requerido)
                                        <span class="badge ml-1 bg-rojo-50 text-rojo-700 ring-rojo-200">crítico</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-slate-600">{{ \App\Models\TestParameter::MODOS[$p->spec_modo] }}</td>
                                <td class="px-4 py-2.5 text-slate-600">
                                    {{ $p->specTexto() }}
                                    @if ($p->spec_desde_producto)
                                        <span class="block text-xs text-slate-400">del producto: {{ $p->spec_desde_producto }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-600">{{ $p->muestras }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ===== Agregar parametro ===== --}}
    @if ($puedeCrear)
        <div x-data="{ abierto: {{ $plantilla->parameters->isEmpty() ? 'true' : 'false' }} }" class="tarjeta p-4 sm:p-5">
            <button type="button" @click="abierto = !abierto" class="flex w-full items-center justify-between text-left">
                <span>
                    <span class="block text-sm font-semibold text-slate-900">Agregar parámetro de ensayo</span>
                    <span class="block text-xs text-slate-500">Una característica nueva a medir en este proceso.</span>
                </span>
                <span class="text-xs text-pc-700" x-text="abierto ? 'Cerrar' : 'Abrir'"></span>
            </button>

            <div x-show="abierto" x-cloak class="mt-4 border-t border-slate-200 pt-4">
                <form method="POST" action="{{ route('admin.parametros.store', $plantilla) }}">
                    @csrf

                    @include('admin.plantillas._parametro-campos', [
                        'p' => new \App\Models\TestParameter(['muestras' => 1, 'promediar' => true, 'tipo' => 'numeric']),
                        'prefijo' => 'nuevo',
                    ])

                    <div class="mt-4">
                        <button type="submit" class="btn-primario">Agregar parámetro</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
