@extends('layouts.app')

@section('titulo', 'Inspecciones')

@section('encabezado')
    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Inspecciones</h1>
    <p class="mt-1 text-sm text-slate-500">
        {{ $inspecciones->total() }} {{ Str::plural('boleta', $inspecciones->total()) }} registradas
    </p>
@endsection

@section('contenido')

    <form method="GET" class="tarjeta mb-4 p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="q" class="etiqueta">Buscar</label>
                <input id="q" name="q" value="{{ request('q') }}" class="campo" placeholder="Boleta, lote o producto">
            </div>
            <div>
                <label for="proceso" class="etiqueta">Proceso</label>
                <select id="proceso" name="proceso" class="campo">
                    <option value="">Todos</option>
                    @foreach ($procesos as $p)
                        <option value="{{ $p->id }}" @selected(request('proceso') == $p->id)>
                            {{ $p->sector->name }} &middot; {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="estado" class="etiqueta">Estado</label>
                <select id="estado" name="estado" class="campo">
                    <option value="">Todos</option>
                    @foreach (\App\Models\Inspection::ESTADOS as $valor => $texto)
                        <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="desde" class="etiqueta">Desde</label>
                    <input id="desde" name="desde" type="date" value="{{ request('desde') }}" class="campo">
                </div>
                <div>
                    <label for="hasta" class="etiqueta">Hasta</label>
                    <input id="hasta" name="hasta" type="date" value="{{ request('hasta') }}" class="campo">
                </div>
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            <button type="submit" class="btn-primario">Filtrar</button>
            <a href="{{ route('inspecciones.index') }}" class="btn-secundario">Limpiar</a>
        </div>
    </form>

    @if ($inspecciones->isEmpty())
        <div class="tarjeta p-10 text-center">
            <p class="text-sm text-slate-500">No hay inspecciones que coincidan con el filtro.</p>
        </div>
    @else
        <div class="tarjeta hidden overflow-hidden md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">Boleta</th>
                            <th class="px-4 py-2.5 font-semibold">Proceso</th>
                            <th class="px-4 py-2.5 font-semibold">Lote</th>
                            <th class="px-4 py-2.5 font-semibold">Producto</th>
                            <th class="px-4 py-2.5 font-semibold">Fecha</th>
                            <th class="px-4 py-2.5 font-semibold">Emitida</th>
                            <th class="px-4 py-2.5 font-semibold">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="anim-filas divide-y divide-slate-100">
                        @foreach ($inspecciones as $i)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('inspecciones.show', $i) }}"
                                       class="font-mono text-xs font-semibold text-pc-700 hover:underline">{{ $i->code }}</a>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $i->process->name }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('lotes.show', $i->lot) }}"
                                       class="font-mono text-xs text-slate-600 hover:underline">{{ $i->lot->code }}</a>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $i->lot->product?->code ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $i->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    @if ($i->estaPublicada())
                                        <span class="badge bg-pc-50 text-pc-800 ring-pc-200">si</span>
                                    @else
                                        <span class="badge bg-slate-100 text-slate-500 ring-slate-300">no</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="badge {{ $i->badge_color }}">{{ $i->estado }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-3 md:hidden">
            @foreach ($inspecciones as $i)
                <a href="{{ route('inspecciones.show', $i) }}" class="tarjeta block p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-xs font-semibold text-pc-700">{{ $i->code }}</p>
                            <p class="text-sm font-medium text-slate-800">{{ $i->process->name }}</p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $i->lot->code }} &middot; {{ $i->lot->product?->code ?? 'sin producto' }}
                            </p>
                        </div>
                        <span class="badge shrink-0 {{ $i->badge_color }}">{{ $i->estado }}</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">
                        {{ $i->fecha->format('d/m/Y') }}
                        &middot; {{ $i->estaPublicada() ? 'emitida' : 'sin emitir' }}
                    </p>
                </a>
            @endforeach
        </div>

        <div class="mt-4">{{ $inspecciones->links() }}</div>
    @endif
@endsection
