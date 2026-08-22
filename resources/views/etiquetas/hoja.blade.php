@extends('layouts.impresion')

@section('titulo', $titulo)

@section('barra-titulo')
    <p class="text-sm font-semibold text-slate-900">{{ $titulo }}</p>
    <p class="text-xs text-slate-500">
        {{ $inspecciones->count() }} {{ Str::plural('etiqueta', $inspecciones->count()) }}
        &middot; {{ $formato['nombre'] }}
        @if ($formato['ancho'])
            ({{ rtrim(rtrim(number_format($formato['ancho'], 1, ',', ''), '0'), ',') }} x
             {{ rtrim(rtrim(number_format($formato['alto'], 1, ',', ''), '0'), ',') }} mm)
        @endif
    </p>
@endsection

@section('barra-acciones')
    @if ($origen instanceof \App\Models\Inspection)
        <a href="{{ route('inspecciones.show', $origen) }}" class="btn-secundario">Volver a la boleta</a>
    @else
        <a href="{{ route('lotes.show', $origen) }}" class="btn-secundario">Volver al lote</a>
    @endif
@endsection

@php
    $esTermica = $formato['termica'];
    $anchoMm = $formato['ancho'];
    $altoMm = $formato['alto'];
@endphp

@push('estilos')
    <style>
        /* El QR se dimensiona en milimetros: el SVG se estira al contenedor. */
        .qr-caja {
            width: {{ $formato['qr'] }}mm;
            height: {{ $formato['qr'] }}mm;
        }

        .qr-caja svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        @if ($esTermica)
            /* Termica de rollo: la etiqueta mide exactamente lo que el rollo. */
            .etiqueta-fisica {
                width: {{ $anchoMm }}mm;
                height: {{ $altoMm }}mm;
                font-size: {{ $formato['base'] }}mm;
                line-height: 1.15;
                overflow: hidden;
            }

            @media print {
                @page {
                    size: {{ $anchoMm }}mm {{ $altoMm }}mm;
                    margin: 0;
                }

                /* Una etiqueta por pagina: la termica corta al final de cada una. */
                .etiqueta-fisica {
                    page-break-after: always;
                    break-after: page;
                    border: 0 !important;
                    border-radius: 0 !important;
                }

                .etiqueta-fisica:last-child {
                    page-break-after: auto;
                    break-after: auto;
                }
            }
        @else
            /* Hoja A4: varias etiquetas por pagina. */
            .etiqueta-fisica {
                font-size: {{ $formato['base'] }}mm;
                line-height: 1.15;
            }

            @media print {
                @page {
                    size: A4 portrait;
                    margin: 8mm;
                }
            }
        @endif
    </style>
@endpush

@section('contenido')

    @php
        $motivo = \App\Support\UrlPublica::motivoParaNoImprimir();
    @endphp

    {{-- ===== Barra de formato ===== --}}
    <form method="GET" class="no-imprimir mb-4 rounded-lg bg-white p-4 ring-1 ring-slate-200"
          x-data="{ formato: '{{ $formato['clave'] }}' }">
        <p class="text-sm font-semibold text-slate-900">Formato de impresion</p>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <label for="formato" class="etiqueta">Etiqueta</label>
                <select id="formato" name="formato" class="campo" x-model="formato">
                    @foreach ($formatos as $clave => $f)
                        <option value="{{ $clave }}">{{ $f['nombre'] }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">{{ $formato['detalle'] }}</p>
            </div>

            <div x-show="formato === 'personalizado'" x-cloak class="grid grid-cols-2 gap-2">
                <div>
                    <label for="ancho" class="etiqueta">Ancho (mm)</label>
                    <input id="ancho" name="ancho" type="number" step="0.5"
                           min="{{ \App\Support\FormatoEtiqueta::MIN_MM }}"
                           max="{{ \App\Support\FormatoEtiqueta::MAX_MM }}"
                           value="{{ request('ancho', $anchoMm) }}" class="campo">
                </div>
                <div>
                    <label for="alto" class="etiqueta">Alto (mm)</label>
                    <input id="alto" name="alto" type="number" step="0.5"
                           min="{{ \App\Support\FormatoEtiqueta::MIN_MM }}"
                           max="{{ \App\Support\FormatoEtiqueta::MAX_MM }}"
                           value="{{ request('alto', $altoMm) }}" class="campo">
                </div>
            </div>

            <div>
                <label for="cantidad" class="etiqueta">Cantidad</label>
                <input id="cantidad" name="cantidad" type="number" min="1" max="200"
                       value="{{ $cantidad }}" class="campo">
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn-secundario">Aplicar formato</button>
        </div>
    </form>

    {{-- ===== Advertencias ===== --}}
    @if ($motivo)
        <div class="no-imprimir mb-4 rounded-lg border-l-4 border-rojo-500 bg-rojo-50 p-4">
            <p class="text-sm font-bold text-rojo-900">No imprimas estas etiquetas todavia</p>
            <p class="mt-1 text-sm text-rojo-800">
                Los codigos QR apuntan a <code class="font-mono">{{ \App\Support\UrlPublica::url() }}</code>,
                que {{ $motivo }}.
            </p>
            <p class="mt-2 text-sm text-rojo-800">
                Sirve para probar, no para produccion. Configura <code class="font-mono">APP_URL</code>
                con la direccion definitiva y ejecuta <code class="font-mono">php artisan config:clear</code>.
            </p>
        </div>
    @endif

    <div class="no-imprimir mb-4 rounded-lg bg-pc-50 p-4 ring-1 ring-pc-200">
        <p class="text-sm font-semibold text-pc-900">Antes de imprimir</p>
        <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm text-pc-800">
            @if ($esTermica)
                <li>En el driver de la Zebra, configura el tamano de etiqueta en
                    <strong>{{ $anchoMm }} x {{ $altoMm }} mm</strong> y la escala al
                    <strong>100%</strong>. Si el driver reescala, el QR puede dejar de leerse.</li>
                <li>Desactiva encabezados y pies de pagina del navegador, y los margenes.</li>
                <li>Imprimi <strong>una etiqueta de prueba</strong> y escaneala con el celular antes de la tanda.</li>
            @else
                <li>Imprimi en A4 al <strong>100% de escala</strong>, sin "ajustar a pagina".</li>
                <li>Desactiva encabezados y pies de pagina del navegador.</li>
                <li>Verifica una etiqueta con el celular antes de imprimir la tanda completa.</li>
            @endif
        </ul>
    </div>

    {{-- ===== Etiquetas ===== --}}
    <div class="{{ $esTermica
            ? 'flex flex-wrap gap-3 print:block print:gap-0'
            : 'grid grid-cols-2 gap-2 sm:grid-cols-3 print:grid-cols-3 print:gap-1.5' }}">

        @foreach ($inspecciones as $inspeccion)
            @php
                $lote = $inspeccion->lot;
                $producto = $lote->product;
            @endphp

            <div class="etiqueta-qr etiqueta-fisica flex gap-1 rounded border border-slate-400 bg-white p-1
                        print:rounded-none">

                {{-- QR --}}
                <div class="qr-caja shrink-0 self-start">
                    {!! \App\Support\Qr::svg($inspeccion->urlPublica(), 200) !!}
                </div>

                {{-- Datos legibles: la etiqueta tiene que servir aunque no haya con que escanear --}}
                <div class="flex min-w-0 flex-1 flex-col justify-between leading-tight">

                    <div class="min-w-0">
                        {{-- Marca --}}
                        <div class="flex items-center gap-1">
                            <x-logo variante="marca" alto="" class="h-[3.2mm] w-auto" />
                            <span class="truncate font-extrabold text-pc-800" style="font-size: 0.85em">
                                PLÁSTICOS CARMEN
                            </span>
                        </div>

                        {{-- Producto y gramaje --}}
                        <p class="mt-[0.5mm] truncate font-bold text-slate-900" style="font-size: 1.15em">
                            {{ $producto?->nombre_con_gramaje ?? 'Sin producto' }}
                        </p>
                        <p class="truncate text-slate-600" style="font-size: 0.85em">
                            {{ $producto?->code }}
                        </p>

                        {{-- Lote, fecha, maquina, operador --}}
                        <dl class="mt-[0.5mm] text-slate-800" style="font-size: 0.9em">
                            <div class="flex justify-between gap-1">
                                <dt class="text-slate-500">Lote</dt>
                                <dd class="truncate font-mono font-bold">{{ $lote->code }}</dd>
                            </div>
                            @if ($lote->nro_tarjeta)
                                <div class="flex justify-between gap-1">
                                    <dt class="text-slate-500">Tarjeta</dt>
                                    <dd class="font-semibold">{{ $lote->nro_tarjeta }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between gap-1">
                                <dt class="text-slate-500">Fecha</dt>
                                <dd class="font-semibold">
                                    {{ $lote->fecha->format('d/m/y') }}
                                    @if ($lote->hora) {{ substr($lote->hora, 0, 5) }} @endif
                                </dd>
                            </div>
                            <div class="flex justify-between gap-1">
                                <dt class="text-slate-500">{{ $inspeccion->process->etiqueta('maquina', 'Maquina') }}</dt>
                                <dd class="truncate font-semibold">{{ $inspeccion->machine?->code ?? '-' }}</dd>
                            </div>
                            <div class="flex justify-between gap-1">
                                <dt class="shrink-0 text-slate-500">Operador</dt>
                                <dd class="truncate font-semibold">{{ $inspeccion->operador ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Estado, en texto completo y mayusculas --}}
                    <p @class([
                        'mt-[0.5mm] rounded px-[0.5mm] py-[0.3mm] text-center font-extrabold leading-none',
                        'bg-emerald-600 text-white' => $inspeccion->estado === \App\Models\Inspection::CONFORME,
                        'bg-amber-500 text-white' => $inspeccion->estado === \App\Models\Inspection::OBSERVADO,
                        'bg-rojo-600 text-white' => $inspeccion->estado === \App\Models\Inspection::RECHAZADO,
                        'bg-slate-500 text-white' => $inspeccion->estado === \App\Models\Inspection::PENDIENTE,
                    ]) style="font-size: 0.95em">
                        {{ $inspeccion->estado_completo }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>
@endsection
