{{--
    Boleta de inspeccion / certificado de calidad.

    Formato MEDIA CARTA APAISADA: 215,9 x 139,7 mm (8,5 x 5,5 pulgadas), o sea una
    hoja carta cortada por la mitad a lo ancho, con el lado ancho arriba. Salen
    dos boletas por hoja carta, una arriba y una abajo.

    Conserva el ancho completo de la carta: con 6 mm de margen quedan 203,9 mm de
    ancho util. Por eso la tabla de resultados va con las MUESTRAS COMO COLUMNAS,
    igual que en la planilla de Excel de Calidad: 17 columnas a unos 12 mm cada
    una se leen sin problema. Lo escaso pasa a ser el alto (127,7 mm), asi que el
    contenido se organiza en bandas horizontales y las firmas van lado a lado.

    Replica el formulario preimpreso (COD.02 Tejido, COD.03 Corte y Costura):
    mismo encabezado, mismos rotulos y el recuadro de "ESTADO DE INSPECCION".
    Los rotulos salen de process.etiquetas.

    Variables:
      $inspeccion, $resumen
      $publico  true  -> certificado del cliente: sin observacion interna ni
                          muestras individuales.
                false -> boleta interna: todo.
--}}

@php
    $publico = $publico ?? false;
    $lote = $inspeccion->lot;
    $proceso = $inspeccion->process;
    $producto = $lote->product;

    $numero = fn (?float $v, int $dec = 0): ?string => $v === null
        ? null
        : number_format($v, $dec, ',', '.');

    $colorEstado = match ($inspeccion->estado) {
        \App\Models\Inspection::CONFORME => 'border-emerald-600 bg-emerald-50 text-emerald-800',
        \App\Models\Inspection::OBSERVADO => 'border-amber-600 bg-amber-50 text-amber-800',
        \App\Models\Inspection::RECHAZADO => 'border-rojo-600 bg-rojo-50 text-rojo-800',
        default => 'border-slate-400 bg-slate-50 text-slate-700',
    };

    // El certificado del cliente no muestra las muestras individuales.
    $maxMuestras = (int) $resumen->max(fn ($f) => $f['valores']->max('muestra') ?? 0);
    $conMuestras = ! $publico && $maxMuestras > 1;

    $veredictoTexto = fn (?bool $v): string => match ($v) {
        true => 'Conforme',
        false => 'Fuera spec.',
        default => 'Sin evaluar',
    };
@endphp

@push('estilos')
    <style>
        @media print {
            @page {
                /* Media carta apaisada: hoja carta cortada por la mitad a lo ancho */
                size: 215.9mm 139.7mm;
                margin: 6mm;
            }
        }

        /* En pantalla se muestra con las medidas reales del papel. */
        .hoja-boleta {
            width: 215.9mm;
            min-height: 139.7mm;
        }

        @media print {
            .hoja-boleta {
                width: auto;
                min-height: 0;
                padding: 0;
                box-shadow: none !important;
                border: 0 !important;
            }
        }

        .qr-boleta {
            width: 17mm;
            height: 17mm;
        }

        .qr-boleta svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        /* Tabla de resultados: 203 mm de ancho alcanzan para las 13 muestras. */
        .tabla-resultados {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
            line-height: 1.2;
        }

        .tabla-resultados th,
        .tabla-resultados td {
            border: 0.3mm solid #cbd5e1;
            padding: 0.8mm 1mm;
        }

        /*
         * Las columnas de muestra son angostas y todas iguales. Con 13 muestras
         * son 13 x 9,5 = 123,5 mm; sumadas las columnas fijas quedan unos 31 mm
         * para el nombre de la caracteristica, que es lo que necesita para no
         * partirse en tres lineas.
         */
        .col-muestra {
            width: 9.5mm;
        }

        .col-spec {
            width: 20mm;
        }

        .col-promedio {
            width: 16mm;
        }

        .col-veredicto {
            width: 17mm;
        }
    </style>
@endpush

<article class="hoja-a4 hoja-boleta mx-auto bg-white p-3 shadow-sm ring-1 ring-slate-200 print:ring-0">

    {{-- ===== Banda de encabezado: marca, titulo, estado y QR ===== --}}
    <header class="flex items-stretch gap-2">

        {{-- Marca y titulo --}}
        <div class="flex flex-1 items-center gap-2 rounded border-2 border-pc-700 px-2 py-1.5">
            <x-logo variante="marca" alto="h-9" />
            <div class="min-w-0 leading-tight">
                <p class="text-[9pt] font-bold text-slate-900">PLÁSTICOS CARMEN</p>
                <p class="text-[5.5pt] tracking-wide text-slate-500">tecnología en plásticos</p>
                <p class="text-[5.5pt] text-slate-500">Sector {{ $lote->sector->name }}</p>
            </div>

            <div class="ml-auto pl-2 text-right leading-tight">
                <h1 class="text-[10pt] font-extrabold uppercase text-pc-800">
                    {{ $proceso->tituloBoleta() }}
                </h1>
                @if ($publico)
                    <p class="text-[6pt] font-bold uppercase tracking-wide text-rojo-600">
                        Certificado de calidad
                    </p>
                @endif
                <p class="font-mono text-[13pt] font-bold leading-none text-rojo-600">
                    N&deg; {{ Str::afterLast($inspeccion->code, '-') }}
                </p>
                @if ($proceso->boleta_code)
                    <p class="text-[6pt] text-slate-400">{{ $proceso->boleta_code }}</p>
                @endif
            </div>
        </div>

        {{-- Estado y QR --}}
        <div class="flex w-[62mm] shrink-0 items-center gap-2 rounded border-2 {{ $colorEstado }} px-2 py-1.5">
            <div class="min-w-0 flex-1">
                <p class="text-[5.5pt] font-bold uppercase leading-tight tracking-wide opacity-80">
                    {{ $proceso->tituloEstado() }}
                </p>
                <p class="mt-0.5 text-[11pt] font-extrabold uppercase leading-tight">
                    {{ $inspeccion->estado_completo }}
                </p>
            </div>
            <div class="shrink-0 text-center">
                <div class="qr-boleta">
                    {!! \App\Support\Qr::svg($inspeccion->urlPublica(), 200) !!}
                </div>
                <p class="text-[4.5pt] leading-none text-slate-500">
                    {{ $publico ? 'verificar' : 'certificado' }}
                </p>
            </div>
        </div>
    </header>

    {{-- ===== Identificacion del lote ===== --}}
    <section class="mt-1.5 grid grid-cols-6 gap-x-2.5 gap-y-1 rounded border border-slate-300 px-2 py-1.5">
        <x-campo-boleta :titulo="$proceso->etiqueta('fecha', 'Fecha')"
                        :valor="$inspeccion->fecha->format('d/m/Y')" />

        <x-campo-boleta titulo="Hora"
                        :valor="$inspeccion->hora ? substr($inspeccion->hora, 0, 5) : null" />

        <x-campo-boleta :titulo="$proceso->etiqueta('maquina', 'Maquina')"
                        :valor="$inspeccion->machine?->code" />

        <x-campo-boleta :titulo="$proceso->etiqueta('producto', 'Codigo')"
                        :valor="$producto?->code" />

        <x-campo-boleta titulo="Lote" :valor="$lote->code" mono />

        <x-campo-boleta :titulo="$proceso->etiqueta('tarjeta', 'N. de Tarjeta')"
                        :valor="$lote->nro_tarjeta" />

        <x-campo-boleta :titulo="$proceso->etiqueta('peso', 'Peso')"
                        :valor="$lote->peso_neto ? $numero((float) $lote->peso_neto, 2).' kgrs' : null" />

        <x-campo-boleta titulo="Turno" :valor="$inspeccion->turno" />

        @if ($lote->nro_lote_produccion)
            <x-campo-boleta titulo="N. de Lote" :valor="$lote->nro_lote_produccion" />
        @endif

        @if ($proceso->requiere_cantidades || $inspeccion->total_unidades !== null)
            <x-campo-boleta :titulo="$proceso->etiqueta('unidades', 'Total de unidades')"
                            :valor="$numero($inspeccion->total_unidades)" />

            <x-campo-boleta :titulo="$proceso->etiqueta('falladas', 'Falladas')"
                            :valor="$inspeccion->total_falladas === null ? null :
                                $numero((int) $inspeccion->total_falladas).
                                ($inspeccion->porcentaje_falladas !== null
                                    ? ' ('.$numero($inspeccion->porcentaje_falladas, 2).'%)'
                                    : '')" />

            <x-campo-boleta :titulo="$proceso->etiqueta('buenas', 'Total buenas')"
                            :valor="$numero($inspeccion->total_buenas)" />
        @endif

        @if ($lote->sourceLot)
            <x-campo-boleta titulo="Proviene del lote" :valor="$lote->sourceLot->code" mono />
        @endif

        <x-campo-boleta titulo="Nombre del Operador" :valor="$inspeccion->operador" ancho="doble" />
    </section>

    {{-- ===== Resultados de ensayo ===== --}}
    <section class="mt-1.5">
        <table class="tabla-resultados">
            <thead>
                <tr class="bg-slate-100 text-left text-slate-700">
                    <th class="font-semibold">Característica</th>
                    <th class="col-spec font-semibold">Especificación</th>

                    @if ($conMuestras)
                        @for ($n = 1; $n <= $maxMuestras; $n++)
                            <th class="col-muestra text-center font-semibold">M{{ $n }}</th>
                        @endfor
                    @endif

                    <th class="col-promedio text-center font-semibold">
                        {{ $maxMuestras > 1 ? 'Promedio' : 'Resultado' }}
                    </th>
                    <th class="col-veredicto text-center font-semibold">Veredicto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($resumen as $fila)
                    @php
                        $valores = $fila['valores']->keyBy('muestra');
                        $resultado = $fila['promedio'] !== null
                            ? $numero($fila['promedio'], 2)
                            : ($fila['valores']->first()?->valor ?? '');
                    @endphp

                    <tr class="{{ $fila['veredicto'] === false ? 'bg-rojo-50' : '' }}">
                        <th scope="row" class="text-left font-medium text-slate-800">
                            {{ $fila['parametro']->etiqueta }}
                        </th>
                        <td class="text-slate-600">{{ $fila['spec'] }}</td>

                        @if ($conMuestras)
                            @for ($n = 1; $n <= $maxMuestras; $n++)
                                @php $m = $valores->get($n); @endphp
                                <td @class([
                                    'text-center',
                                    'bg-rojo-100 font-semibold text-rojo-800' => $m?->en_especificacion === false,
                                ])>{{ $m?->valor ?? '' }}</td>
                            @endfor
                        @endif

                        <td @class([
                            'text-center font-bold',
                            'text-rojo-700' => $fila['veredicto'] === false,
                            'text-emerald-700' => $fila['veredicto'] === true,
                            'text-slate-700' => $fila['veredicto'] === null,
                        ])>{{ $resultado }}</td>

                        <td @class([
                            'text-center font-semibold',
                            'text-rojo-700' => $fila['veredicto'] === false,
                            'text-emerald-700' => $fila['veredicto'] === true,
                            'text-slate-400' => $fila['veredicto'] === null,
                        ])>{{ $veredictoTexto($fila['veredicto']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-3 text-center text-slate-400">
                            Sin mediciones registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p class="mt-0.5 text-[5pt] text-slate-500">
            Ensayo según {{ $inspeccion->template->name }} (Rev:{{ $inspeccion->template->revision }})
        </p>
    </section>

    {{-- ===== Observaciones ===== --}}
    <section class="mt-1 grid gap-2 {{ $publico ? '' : 'grid-cols-2' }}">
        <div>
            <p class="text-[5.5pt] font-bold uppercase tracking-wide text-slate-600">Observación</p>
            <p class="min-h-[8mm] rounded border border-slate-300 p-1 text-[6.5pt] leading-snug text-slate-800">
                {{ $inspeccion->observacion ?: '' }}
            </p>
        </div>

        @unless ($publico)
            <div>
                <p class="text-[5.5pt] font-bold uppercase tracking-wide text-slate-500">
                    Observación interna
                    <span class="font-normal normal-case text-slate-400">&middot; no sale en el certificado</span>
                </p>
                <p class="min-h-[8mm] rounded border border-dashed border-slate-300 bg-slate-50 p-1 text-[6.5pt] leading-snug text-slate-700">
                    {{ $inspeccion->observacion_interna ?: '' }}
                </p>
            </div>
        @endunless
    </section>

    {{-- ===== Firmas =====
         En 204 mm de ancho las tres firmas entran comodas lado a lado,
         a unos 65 mm cada una. --}}
    <footer class="mt-3 grid grid-cols-3 gap-5">
        <div>
            <div class="h-6 border-b border-slate-500"></div>
            <p class="mt-0.5 truncate text-[6.5pt] font-semibold text-slate-800">{{ $inspeccion->operador ?: '' }}</p>
            <p class="text-[5.5pt] leading-tight text-slate-500">Operador</p>
        </div>
        <div>
            <div class="h-6 border-b border-slate-500"></div>
            <p class="mt-0.5 truncate text-[6.5pt] font-semibold text-slate-800">{{ $inspeccion->responsable ?: '' }}</p>
            <p class="text-[5.5pt] leading-tight text-slate-500">Responsable de Control de Calidad</p>
        </div>
        <div>
            <div class="h-6 border-b border-slate-500"></div>
            <p class="mt-0.5 text-[6.5pt] text-slate-400">&nbsp;</p>
            <p class="text-[5.5pt] leading-tight text-slate-500">Supervisor de turno / Jefe de Producción</p>
        </div>
    </footer>

    <div class="mt-1.5 flex flex-wrap items-baseline justify-between gap-2 border-t border-slate-200 pt-1 text-[5pt] leading-relaxed text-slate-400">
        <p>
            Plásticos Carmen S.R.L. &middot; Control de Calidad.
            @if ($inspeccion->published_at)
                Boleta emitida el {{ $inspeccion->published_at->format('d/m/Y H:i') }}.
            @endif
        </p>
        @if ($publico)
            <p>Verificable escaneando el QR de la etiqueta, o en {{ $inspeccion->urlPublica() }}</p>
        @endif
    </div>
</article>
