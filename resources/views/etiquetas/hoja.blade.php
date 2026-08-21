@extends('layouts.impresion')

@section('titulo', $titulo)

@section('barra-titulo')
    <p class="text-sm font-semibold text-slate-900">{{ $titulo }}</p>
    <p class="text-xs text-slate-500">{{ $inspecciones->count() }} etiquetas &middot; A4, 3 por fila</p>
@endsection

@section('barra-acciones')
    @if ($origen instanceof \App\Models\Inspection)
        <a href="{{ route('inspecciones.show', $origen) }}" class="btn-secundario">Volver a la boleta</a>
    @else
        <a href="{{ route('lotes.show', $origen) }}" class="btn-secundario">Volver al lote</a>
    @endif

    {{-- Cantidad de etiquetas a generar --}}
    <form method="GET" class="flex items-center gap-2">
        <label for="cantidad" class="text-xs text-slate-500">Cantidad</label>
        <input id="cantidad" name="cantidad" type="number" min="1" max="120"
               value="{{ request('cantidad', $inspecciones->count()) }}"
               class="w-20 rounded-lg border-0 px-2 py-1.5 text-sm ring-1 ring-inset ring-slate-300">
        <button type="submit" class="btn-secundario !px-3 !py-1.5 text-xs">Generar</button>
    </form>
@endsection

@section('contenido')

    @php
        // El QR codifica APP_URL, y esa direccion queda impresa para siempre.
        $motivo = \App\Support\UrlPublica::motivoParaNoImprimir();
    @endphp

    @if ($motivo)
        <div class="no-imprimir mb-4 rounded-lg bg-red-50 p-4 ring-1 ring-red-300">
            <p class="text-sm font-bold text-red-900">No imprimas estas etiquetas todavia</p>
            <p class="mt-1 text-sm text-red-800">
                Los codigos QR apuntan a <code class="font-mono">{{ \App\Support\UrlPublica::url() }}</code>,
                que {{ $motivo }}.
            </p>
            <p class="mt-2 text-sm text-red-800">
                Sirve para probar, no para produccion. Antes de imprimir etiquetas que van a salir de la
                planta, configura <code class="font-mono">APP_URL</code> en el archivo
                <code class="font-mono">.env</code> con la direccion definitiva
                (por ejemplo <code class="font-mono">https://calidad.plasticoscarmen.com</code>) y ejecuta
                <code class="font-mono">php artisan config:clear</code>.
            </p>
        </div>
    @elseif (\App\Support\UrlPublica::esSinCifrado())
        <div class="no-imprimir mb-4 rounded-lg bg-amber-50 p-4 ring-1 ring-amber-300">
            <p class="text-sm font-bold text-amber-900">La direccion del QR no usa HTTPS</p>
            <p class="mt-1 text-sm text-amber-800">
                <code class="font-mono">{{ \App\Support\UrlPublica::url() }}</code> es http, no https.
                El celular del cliente va a mostrar una advertencia de seguridad al abrir el certificado.
            </p>
        </div>
    @endif

    <div class="no-imprimir mb-4 rounded-lg bg-pc-50 p-4 ring-1 ring-pc-200">
        <p class="text-sm font-semibold text-pc-900">Antes de imprimir</p>
        <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm text-pc-800">
            <li>Imprimi en A4 al <strong>100% de escala</strong>, sin "ajustar a pagina": si se reduce, el QR puede no leerse.</li>
            <li>Desactiva encabezados y pies de pagina del navegador.</li>
            <li>Verifica una etiqueta con el celular antes de imprimir la tanda completa.</li>
        </ul>
    </div>

    {{-- Hoja de etiquetas: 3 columnas en papel, 2 en pantalla chica. --}}
    <div class="hoja-etiquetas grid grid-cols-2 gap-2 sm:grid-cols-3 print:grid-cols-3 print:gap-1.5">
        @foreach ($inspecciones as $inspeccion)
            @php $lote = $inspeccion->lot; @endphp

            <div class="etiqueta-qr flex gap-2 rounded border border-slate-400 bg-white p-2">

                {{-- QR --}}
                <div class="shrink-0">
                    {!! \App\Support\Qr::svg($inspeccion->urlPublica(), 88) !!}
                </div>

                {{-- Datos legibles: la etiqueta tiene que servir aunque no haya con que escanear. --}}
                <div class="min-w-0 flex-1 text-[8px] leading-tight">
                    <p class="flex items-baseline justify-between gap-1">
                        <span class="font-extrabold text-pc-800">PLASTICOS CARMEN</span>
                        @if ($inspeccion->process->boleta_code)
                            <span class="text-slate-400">{{ $inspeccion->process->boleta_code }}</span>
                        @endif
                    </p>

                    <p class="mt-0.5 truncate font-bold text-slate-900">
                        {{ $lote->product?->code ?? 'Sin producto' }}
                    </p>

                    <dl class="mt-0.5 space-y-px text-slate-700">
                        <div class="flex justify-between gap-1">
                            <dt class="text-slate-400">Lote</dt>
                            <dd class="truncate font-mono font-semibold">{{ $lote->code }}</dd>
                        </div>
                        @if ($lote->nro_tarjeta)
                            <div class="flex justify-between gap-1">
                                <dt class="text-slate-400">Tarjeta</dt>
                                <dd class="font-semibold">{{ $lote->nro_tarjeta }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-1">
                            <dt class="text-slate-400">Boleta</dt>
                            <dd class="truncate font-mono font-semibold">{{ $inspeccion->code }}</dd>
                        </div>
                        <div class="flex justify-between gap-1">
                            <dt class="text-slate-400">Fecha</dt>
                            <dd>{{ $inspeccion->fecha->format('d/m/y') }}</dd>
                        </div>
                        @if ($inspeccion->machine)
                            <div class="flex justify-between gap-1">
                                <dt class="text-slate-400">Maquina</dt>
                                <dd>{{ $inspeccion->machine->code }}</dd>
                            </div>
                        @endif
                    </dl>

                    <p @class([
                        'mt-1 rounded px-1 py-0.5 text-center text-[8px] font-extrabold',
                        'bg-emerald-100 text-emerald-800' => $inspeccion->estado === \App\Models\Inspection::CONFORME,
                        'bg-amber-100 text-amber-800' => $inspeccion->estado === \App\Models\Inspection::OBSERVADO,
                        'bg-red-100 text-red-800' => $inspeccion->estado === \App\Models\Inspection::RECHAZADO,
                    ])>
                        {{ $inspeccion->estado }}
                    </p>

                    <p class="mt-0.5 text-center text-[7px] leading-none text-slate-400">
                        Escanea para el certificado
                    </p>
                </div>
            </div>
        @endforeach
    </div>
@endsection
