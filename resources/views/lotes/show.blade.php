@extends('layouts.app')

@section('titulo', 'Lote '.$lote->code)

@section('encabezado')
    <nav class="mb-2 text-xs text-slate-500">
        <a href="{{ route('lotes.index') }}" class="hover:underline">Lotes</a> / {{ $lote->code }}
    </nav>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="font-mono text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ $lote->code }}</h1>
                <span class="badge {{ $lote->badge_color }}">{{ \App\Models\Lot::ESTADOS[$lote->estado] }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500">
                {{ $lote->sector->name }}
                @if ($lote->product) &middot; {{ $lote->product->code }} @endif
                &middot; {{ $lote->fecha->format('d/m/Y') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($lote->inspections->contains(fn ($i) => $i->estaPublicada()))
                <a href="{{ route('lotes.etiquetas', $lote) }}" class="btn-secundario">Etiquetas QR</a>
            @endif
            @if (auth()->user()->puedeEditar())
                <a href="{{ route('lotes.edit', $lote) }}" class="btn-secundario">Editar</a>
            @endif
        </div>
    </div>
@endsection

@section('contenido')

    <div class="grid gap-5 lg:grid-cols-3">

        {{-- ===== Cadena de procesos ===== --}}
        <div class="lg:col-span-2">
            <div class="tarjeta overflow-hidden">
                <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                    <h2 class="text-sm font-semibold text-slate-900">Cadena de inspeccion</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Un proceso solo se habilita cuando los anteriores estan aprobados.
                    </p>
                </div>

                <ol class="divide-y divide-slate-100">
                    @foreach ($cadena as $paso)
                        @php
                            $proceso = $paso['proceso'];
                            $inspeccion = $paso['inspeccion'];
                            $puerta = $paso['puerta'];
                        @endphp

                        <li class="px-4 py-4 sm:px-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex min-w-0 items-start gap-3">
                                    {{-- Indicador de paso --}}
                                    <span @class([
                                        'grid h-8 w-8 shrink-0 place-items-center rounded-full text-xs font-bold',
                                        'bg-emerald-100 text-emerald-700' => $inspeccion?->estado === \App\Models\Inspection::CONFORME,
                                        'bg-amber-100 text-amber-700' => $inspeccion?->estado === \App\Models\Inspection::OBSERVADO,
                                        'bg-red-100 text-red-700' => $inspeccion?->estado === \App\Models\Inspection::RECHAZADO,
                                        'bg-slate-100 text-slate-500' => $inspeccion === null || $inspeccion->estado === \App\Models\Inspection::PENDIENTE,
                                    ])>
                                        {{ $proceso->orden }}
                                    </span>

                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">
                                            {{ $proceso->name }}
                                            <span class="font-normal text-slate-400">({{ $proceso->code }})</span>
                                            @unless ($proceso->bloquea_siguiente)
                                                <span class="badge ml-1 bg-slate-100 text-slate-600 ring-slate-300">opcional</span>
                                            @endunless
                                        </p>

                                        @if ($inspeccion)
                                            <p class="mt-0.5 text-xs text-slate-500">
                                                Boleta
                                                <a href="{{ route('inspecciones.show', $inspeccion) }}"
                                                   class="font-mono font-semibold text-pc-700 hover:underline">{{ $inspeccion->code }}</a>
                                                &middot; {{ $inspeccion->fecha->format('d/m/Y') }}
                                                @if ($inspeccion->machine) &middot; {{ $inspeccion->machine->code }} @endif
                                                @if ($inspeccion->operador) &middot; {{ $inspeccion->operador }} @endif
                                            </p>
                                            <p class="mt-0.5 text-xs">
                                                @if ($inspeccion->estaPublicada())
                                                    <span class="text-emerald-700">Boleta emitida, el QR ya resuelve</span>
                                                @else
                                                    <span class="text-amber-700">Boleta sin emitir, el QR todavia no resuelve</span>
                                                @endif
                                            </p>
                                        @elseif (! $puerta['permitido'])
                                            <p class="mt-0.5 text-xs text-red-700">{{ $puerta['motivo'] }}</p>
                                        @else
                                            <p class="mt-0.5 text-xs text-slate-400">Sin inspeccion registrada</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-wrap items-center gap-2">
                                    @if ($inspeccion)
                                        <span class="badge {{ $inspeccion->badge_color }}">{{ $inspeccion->estado }}</span>
                                        <a href="{{ route('inspecciones.show', $inspeccion) }}" class="btn-secundario !px-3 !py-1.5 text-xs">
                                            Ver boleta
                                        </a>
                                    @elseif (auth()->user()->puedeEditar() && $puerta['permitido'])
                                        <a href="{{ route('inspecciones.create', [$lote, $proceso]) }}"
                                           class="btn-primario !px-3 !py-1.5 text-xs">
                                            Inspeccionar
                                        </a>
                                    @elseif (! $puerta['permitido'])
                                        <span class="badge bg-slate-100 text-slate-500 ring-slate-300">bloqueado</span>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            @if ($lote->observacion)
                <div class="tarjeta mt-5 p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-slate-900">Observacion del lote</h2>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $lote->observacion }}</p>
                </div>
            @endif
        </div>

        {{-- ===== Datos del lote ===== --}}
        <div class="space-y-5">
            <div class="tarjeta p-4 sm:p-5">
                <h2 class="text-sm font-semibold text-slate-900">Datos del lote</h2>
                <dl class="mt-3 divide-y divide-slate-100 text-sm">
                    <x-dato titulo="Producto" :valor="$lote->product?->code" />
                    <x-dato titulo="N. de tarjeta" :valor="$lote->nro_tarjeta" />
                    <x-dato titulo="N. lote produccion" :valor="$lote->nro_lote_produccion" />
                    <x-dato titulo="Maquina" :valor="$lote->machine?->code" />
                    <x-dato titulo="Fecha" :valor="$lote->fecha->format('d/m/Y')" />
                    <x-dato titulo="Hora" :valor="$lote->hora ? substr($lote->hora, 0, 5) : null" />
                    <x-dato titulo="Turno" :valor="$lote->turno" />
                    <x-dato titulo="Peso neto"
                            :valor="$lote->peso_neto ? rtrim(rtrim(number_format((float) $lote->peso_neto, 3, ',', '.'), '0'), ',').' kg' : null" />
                    <x-dato titulo="Registro" :valor="$lote->creator?->name" />
                </dl>
            </div>

            {{-- Especificacion vigente del producto --}}
            @if ($lote->product)
                <div class="tarjeta p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-slate-900">Nominales del producto</h2>
                    <p class="mt-1 text-xs text-slate-500">De aca salen las tolerancias del ensayo.</p>
                    <dl class="mt-3 divide-y divide-slate-100 text-sm">
                        <x-dato titulo="Ancho" :valor="$lote->product->ancho_nominal ? $lote->product->ancho_nominal.' cm' : null" />
                        <x-dato titulo="Largo" :valor="$lote->product->largo_nominal ? $lote->product->largo_nominal.' cm' : null" />
                        <x-dato titulo="Gramaje" :valor="$lote->product->gramaje_nominal ? $lote->product->gramaje_nominal.' g/m2' : null" />
                        <x-dato titulo="Peso bolsa" :valor="$lote->product->peso_nominal ? $lote->product->peso_nominal.' g' : null" />
                        <x-dato titulo="Denier" :valor="$lote->product->denier_nominal" />
                    </dl>
                </div>
            @endif

            {{-- Trazabilidad --}}
            @if ($lote->sourceLot || $lote->derivedLots->isNotEmpty())
                <div class="tarjeta p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-slate-900">Trazabilidad</h2>

                    @if ($lote->sourceLot)
                        <p class="mt-3 text-xs font-medium uppercase tracking-wide text-slate-400">Proviene de</p>
                        <a href="{{ route('lotes.show', $lote->sourceLot) }}"
                           class="mt-1 block rounded-lg bg-slate-50 p-3 hover:bg-slate-100">
                            <span class="block font-mono text-xs font-semibold text-pc-700">{{ $lote->sourceLot->code }}</span>
                            <span class="block text-sm text-slate-700">{{ $lote->sourceLot->product?->code ?? 'sin producto' }}</span>
                        </a>
                    @endif

                    @if ($lote->derivedLots->isNotEmpty())
                        <p class="mt-4 text-xs font-medium uppercase tracking-wide text-slate-400">Dio origen a</p>
                        <div class="mt-1 space-y-2">
                            @foreach ($lote->derivedLots as $derivado)
                                <a href="{{ route('lotes.show', $derivado) }}"
                                   class="block rounded-lg bg-slate-50 p-3 hover:bg-slate-100">
                                    <span class="block font-mono text-xs font-semibold text-pc-700">{{ $derivado->code }}</span>
                                    <span class="block text-sm text-slate-700">{{ $derivado->product?->code ?? 'sin producto' }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
