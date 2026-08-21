@extends('layouts.app')

@section('titulo', 'Productos')

@section('encabezado')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Productos</h1>
            <p class="mt-1 text-sm text-slate-500">
                Los valores nominales de cada codigo definen las tolerancias del ensayo.
            </p>
        </div>
        <a href="{{ route('admin.productos.create') }}" class="btn-primario">Nuevo producto</a>
    </div>
@endsection

@section('contenido')
    @include('admin._nav')

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" class="campo max-w-xs" placeholder="Buscar por codigo">
        <button type="submit" class="btn-primario">Buscar</button>
        @if (request('q'))
            <a href="{{ route('admin.productos.index') }}" class="btn-secundario">Limpiar</a>
        @endif
    </form>

    <div class="tarjeta overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">Codigo</th>
                        <th class="px-4 py-2.5 font-semibold">Color</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Ancho</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Largo</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Gramaje</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Peso bolsa</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Denier</th>
                        <th class="px-4 py-2.5 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($productos as $p)
                        <tr class="{{ $p->active ? '' : 'bg-slate-50 text-slate-400' }} hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-2.5 font-semibold text-slate-800">
                                {{ $p->code }}
                                @unless ($p->active)
                                    <span class="badge ml-1 bg-slate-200 text-slate-600 ring-slate-300">inactivo</span>
                                @endunless
                                <span class="block text-xs font-normal text-slate-400">{{ $p->sector->name }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $p->color ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-right text-slate-600">{{ $p->ancho_nominal ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-right text-slate-600">{{ $p->largo_nominal ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-right text-slate-600">{{ $p->gramaje_nominal ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-right text-slate-600">{{ $p->peso_nominal ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-right text-slate-600">{{ $p->denier_nominal ?? '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                <a href="{{ route('admin.productos.edit', $p) }}"
                                   class="text-xs font-semibold text-pc-700 hover:underline">Editar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                                No hay productos cargados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $productos->links() }}</div>
@endsection
