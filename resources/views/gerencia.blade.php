@extends('layouts.app')

@section('titulo', 'Tablero gerencial')

@section('encabezado')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Tablero gerencial</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}
            </p>
        </div>

        {{-- Selector de periodo --}}
        <form method="GET" class="flex items-center gap-2">
            <label for="dias" class="text-xs text-slate-500">Período</label>
            <select id="dias" name="dias" class="campo !py-1.5 !text-sm" onchange="this.form.submit()">
                @foreach ($periodos as $valor => $texto)
                    <option value="{{ $valor }}" @selected($dias === $valor)>{{ $texto }}</option>
                @endforeach
            </select>
        </form>
    </div>
@endsection

@section('contenido')

    {{-- ===== Indicadores principales ===== --}}
    <div class="anim-grilla grid grid-cols-2 gap-3 lg:grid-cols-4">

        {{-- Conformidad --}}
        <div class="tarjeta p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Conformidad</p>
            <p @class([
                'mt-1 text-3xl font-bold tracking-tight',
                'text-emerald-700' => $resumen['conformidad'] !== null && $resumen['conformidad'] >= 90,
                'text-amber-700' => $resumen['conformidad'] !== null && $resumen['conformidad'] >= 75 && $resumen['conformidad'] < 90,
                'text-rojo-700' => $resumen['conformidad'] !== null && $resumen['conformidad'] < 75,
                'text-slate-400' => $resumen['conformidad'] === null,
            ])>
                {{ $resumen['conformidad'] === null ? 'sin datos' : number_format($resumen['conformidad'], 1, ',', '.').'%' }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Inspecciones sin ningún desvío, sobre {{ number_format($resumen['total'], 0, ',', '.') }} cerradas
            </p>
        </div>

        {{-- Aprobacion --}}
        <div class="tarjeta p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Aprobación</p>
            <p class="mt-1 text-3xl font-bold tracking-tight text-slate-900">
                {{ $resumen['aprobacion'] === null ? 'sin datos' : number_format($resumen['aprobacion'], 1, ',', '.').'%' }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Lotes que pudieron seguir: conformes más observados
            </p>
        </div>

        {{-- Unidades falladas --}}
        <div class="tarjeta p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Unidades falladas</p>
            <p @class([
                'mt-1 text-3xl font-bold tracking-tight',
                'text-rojo-700' => $unidades['porcentaje'] !== null && $unidades['porcentaje'] > 3,
                'text-slate-900' => ! ($unidades['porcentaje'] !== null && $unidades['porcentaje'] > 3),
            ])>
                {{ $unidades['porcentaje'] === null ? 'sin datos' : number_format($unidades['porcentaje'], 2, ',', '.').'%' }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
                {{ number_format($unidades['falladas'], 0, ',', '.') }} de
                {{ number_format($unidades['inspeccionadas'], 0, ',', '.') }} inspeccionadas
            </p>
        </div>

        {{-- Boletas sin emitir --}}
        <div class="tarjeta p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Boletas sin emitir</p>
            <p @class([
                'mt-1 text-3xl font-bold tracking-tight',
                'text-rojo-700' => $resumen['sinEmitir'] > 0,
                'text-emerald-700' => $resumen['sinEmitir'] === 0,
            ])>{{ number_format($resumen['sinEmitir'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-500">
                @if ($resumen['sinEmitir'] > 0)
                    Su QR todavía no resuelve el certificado
                @else
                    Todas las inspecciones están emitidas
                @endif
            </p>
        </div>
    </div>

    {{-- ===== Distribucion de veredictos ===== --}}
    <div class="tarjeta mt-5 p-4 sm:p-5">
        <h2 class="text-sm font-semibold text-slate-900">Distribución de veredictos</h2>
        <p class="mt-0.5 text-xs text-slate-500">Inspecciones cerradas del período</p>

        @if ($resumen['total'] === 0)
            <p class="py-6 text-center text-sm text-slate-500">Sin inspecciones cerradas en el período.</p>
        @else
            @php
                $tramos = [
                    ['Conforme', $resumen['conformes'], 'bg-emerald-500', 'text-emerald-700'],
                    ['Con observación', $resumen['observadas'], 'bg-amber-500', 'text-amber-700'],
                    ['No conforme', $resumen['rechazadas'], 'bg-rojo-500', 'text-rojo-700'],
                ];
            @endphp

            <div class="mt-3 flex h-6 overflow-hidden rounded-full bg-slate-100">
                @foreach ($tramos as [$nombre, $cantidad, $fondo, $texto])
                    @if ($cantidad > 0)
                        <div class="anim-barra {{ $fondo }} flex items-center justify-center"
                             style="width: {{ round($cantidad / $resumen['total'] * 100, 2) }}%"
                             title="{{ $nombre }}: {{ $cantidad }}">
                            @if ($cantidad / $resumen['total'] > 0.08)
                                <span class="text-[10px] font-bold text-white">
                                    {{ round($cantidad / $resumen['total'] * 100) }}%
                                </span>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="mt-3 grid grid-cols-3 gap-3">
                @foreach ($tramos as [$nombre, $cantidad, $fondo, $texto])
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $fondo }}"></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-bold {{ $texto }}">{{ number_format($cantidad, 0, ',', '.') }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $nombre }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ===== Tendencia ===== --}}
    <x-grafico-tendencia class="mt-5"
                         :serie="$serie"
                         titulo="Conformidad mes a mes"
                         detalle="Últimos 12 meses. La línea punteada marca el 90%." />

    {{-- ===== Rankings ===== --}}
    <div class="mt-5 grid gap-5 lg:grid-cols-2">

        <x-grafico-barras
            titulo="Desvíos por característica"
            detalle="Qué se sale de especificación más seguido. Es la pregunta más útil para decidir dónde intervenir el proceso."
            columna="Mediciones fuera"
            columnaSecundaria="% del total"
            tono="rojo"
            :filas="collect($desvios)->map(fn (array $d) => [
                'etiqueta' => $d['nombre'],
                'valor' => $d['fuera'],
                'secundario' => $d['porcentaje'],
            ])" />

        <x-grafico-barras
            titulo="Máquinas con más desvíos"
            detalle="Inspecciones observadas o no conformes por máquina."
            columna="Inspecciones con desvío"
            columnaSecundaria="% de sus inspecciones"
            tono="ambar"
            :filas="collect($porMaquina)->map(fn (array $m) => [
                'etiqueta' => $m['nombre'],
                'valor' => $m['desvios'],
                'secundario' => $m['porcentaje'],
            ])" />

        <x-grafico-barras
            titulo="Productos con más desvíos"
            detalle="Puede indicar una especificación mal cargada tanto como un problema de proceso."
            columna="Inspecciones con desvío"
            columnaSecundaria="% de sus inspecciones"
            tono="ambar"
            :filas="collect($porProducto)->map(fn (array $p) => [
                'etiqueta' => $p['nombre'],
                'valor' => $p['desvios'],
                'secundario' => $p['porcentaje'],
            ])" />

        {{-- Por proceso --}}
        <div class="tarjeta overflow-hidden">
            <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                <h3 class="text-sm font-semibold text-slate-900">Conformidad por proceso</h3>
                <p class="mt-0.5 text-xs text-slate-500">Dónde se concentra el control y cómo sale</p>
            </div>

            @if ($porProceso->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-slate-500">Sin datos en el período.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2 font-semibold">Proceso</th>
                                <th class="px-3 py-2 text-right font-semibold">Insp.</th>
                                <th class="px-3 py-2 text-right font-semibold">Conf.</th>
                                <th class="px-3 py-2 text-right font-semibold">Obs.</th>
                                <th class="px-3 py-2 text-right font-semibold">No conf.</th>
                                <th class="px-3 py-2 text-right font-semibold">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($porProceso as $p)
                                <tr>
                                    <td class="px-4 py-2 font-medium text-slate-800">{{ $p['nombre'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ $p['total'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-emerald-700">{{ $p['conformes'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-amber-700">{{ $p['observadas'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-rojo-700">{{ $p['rechazadas'] }}</td>
                                    <td @class([
                                        'px-3 py-2 text-right font-bold tabular-nums',
                                        'text-emerald-700' => $p['conformidad'] >= 90,
                                        'text-amber-700' => $p['conformidad'] >= 75 && $p['conformidad'] < 90,
                                        'text-rojo-700' => $p['conformidad'] < 75,
                                    ])>{{ number_format($p['conformidad'], 1, ',', '.') }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ===== Estado de lotes y avisos ===== --}}
    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <div class="tarjeta p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Lotes liberados</p>
            <p class="mt-1 text-3xl font-bold text-emerald-700">{{ number_format($resumen['lotesLiberados'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-500">Pasaron todas las puertas de calidad</p>
        </div>
        <div class="tarjeta p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Lotes bloqueados</p>
            <p @class([
                'mt-1 text-3xl font-bold',
                'text-rojo-700' => $resumen['lotesBloqueados'] > 0,
                'text-emerald-700' => $resumen['lotesBloqueados'] === 0,
            ])>{{ number_format($resumen['lotesBloqueados'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-500">No pueden avanzar al proceso siguiente</p>
        </div>
        <div class="tarjeta p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Unidades buenas</p>
            <p class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($unidades['buenas'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-500">Del total inspeccionado en el período</p>
        </div>
    </div>

    @if ($avisos->isNotEmpty())
        <div class="tarjeta mt-5 overflow-hidden">
            <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                <h3 class="text-sm font-semibold text-slate-900">Requiere atención</h3>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($avisos as $aviso)
                    <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800">{{ $aviso['titulo'] }}</p>
                            <p class="text-xs text-slate-500">{{ $aviso['detalle'] }}</p>
                        </div>
                        <span @class([
                            'shrink-0 rounded-full px-2.5 py-0.5 text-sm font-bold text-white',
                            'bg-rojo-500' => $aviso['tono'] === 'rojo',
                            'bg-amber-500' => $aviso['tono'] === 'ambar',
                        ])>{{ $aviso['cantidad'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
