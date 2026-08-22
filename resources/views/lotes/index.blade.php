@extends('layouts.app')

@section('titulo', 'Lotes')

@section('encabezado')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Lotes de produccion</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $lotes->total() }} {{ Str::plural('lote', $lotes->total()) }} &middot; cada lote se inspecciona proceso por proceso
            </p>
        </div>
        @if (auth()->user()->puede('lotes.crear'))
            <a href="{{ route('lotes.create') }}" class="btn-primario">Nuevo lote</a>
        @endif
    </div>
@endsection

@section('contenido')

    {{-- Filtros --}}
    <form method="GET" class="tarjeta mb-4 p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label for="q" class="etiqueta">Buscar</label>
                <input id="q" name="q" value="{{ request('q') }}" class="campo"
                       placeholder="Lote, N. de tarjeta o codigo de producto">
            </div>
            <div>
                <label for="sector" class="etiqueta">Sector</label>
                <select id="sector" name="sector" class="campo">
                    <option value="">Todos</option>
                    @foreach ($sectores as $s)
                        <option value="{{ $s->id }}" @selected(request('sector') == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="estado" class="etiqueta">Estado</label>
                <select id="estado" name="estado" class="campo">
                    <option value="">Todos</option>
                    @foreach (\App\Models\Lot::ESTADOS as $valor => $texto)
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
            <a href="{{ route('lotes.index') }}" class="btn-secundario">Limpiar</a>
        </div>
    </form>

    @if ($lotes->isEmpty())
        <div class="tarjeta p-10 text-center">
            <p class="text-sm text-slate-500">No hay lotes que coincidan con el filtro.</p>
        </div>
    @else
        {{-- Tabla en escritorio y tablet --}}
        <div class="tarjeta hidden overflow-hidden md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">Lote</th>
                            <th class="px-4 py-2.5 font-semibold">Producto</th>
                            <th class="px-4 py-2.5 font-semibold">Tarjeta</th>
                            <th class="px-4 py-2.5 font-semibold">Maquina</th>
                            <th class="px-4 py-2.5 font-semibold">Fecha</th>
                            <th class="px-4 py-2.5 font-semibold">Procesos</th>
                            <th class="px-4 py-2.5 font-semibold">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="anim-filas divide-y divide-slate-100">
                        @foreach ($lotes as $lote)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('lotes.show', $lote) }}"
                                       class="font-mono text-xs font-semibold text-pc-700 hover:underline">
                                        {{ $lote->code }}
                                    </a>
                                    <span class="block text-xs text-slate-400">{{ $lote->sector->name }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $lote->product?->code ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $lote->nro_tarjeta ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $lote->machine?->code ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $lote->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($lote->inspections->sortBy(fn ($i) => $i->process->orden) as $i)
                                            <span class="badge {{ $i->badge_color }}" title="{{ $i->process->name }}: {{ $i->estado }}">
                                                {{ $i->process->code }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-slate-400">sin inspecciones</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="badge {{ $lote->badge_color }}">
                                        {{ \App\Models\Lot::ESTADOS[$lote->estado] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tarjetas en celular --}}
        <div class="space-y-3 md:hidden">
            @foreach ($lotes as $lote)
                <a href="{{ route('lotes.show', $lote) }}" class="tarjeta block p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-xs font-semibold text-pc-700">{{ $lote->code }}</p>
                            <p class="truncate text-sm font-medium text-slate-800">
                                {{ $lote->product?->code ?? 'sin producto' }}
                            </p>
                        </div>
                        <span class="badge shrink-0 {{ $lote->badge_color }}">
                            {{ \App\Models\Lot::ESTADOS[$lote->estado] }}
                        </span>
                    </div>
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-xs">
                        <div>
                            <dt class="text-slate-400">Fecha</dt>
                            <dd class="text-slate-700">{{ $lote->fecha->format('d/m/y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Tarjeta</dt>
                            <dd class="text-slate-700">{{ $lote->nro_tarjeta ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Maquina</dt>
                            <dd class="text-slate-700">{{ $lote->machine?->code ?? '-' }}</dd>
                        </div>
                    </dl>
                    @if ($lote->inspections->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-1">
                            @foreach ($lote->inspections->sortBy(fn ($i) => $i->process->orden) as $i)
                                <span class="badge {{ $i->badge_color }}">{{ $i->process->code }} {{ $i->estado }}</span>
                            @endforeach
                        </div>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="mt-4">{{ $lotes->links() }}</div>
    @endif
@endsection
