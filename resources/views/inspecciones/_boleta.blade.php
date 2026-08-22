{{--
    Boleta de inspeccion / certificado de calidad.

    Formato MEDIA CARTA A LO LARGO: 107,95 x 279,4 mm (4,25 x 11 pulgadas), o sea
    una hoja carta cortada por la mitad a lo largo. Con 6 mm de margen quedan
    95,95 mm de ancho util.

    Ese ancho obliga a un cambio de fondo en la tabla de resultados. La tabla
    original tiene 17 columnas (caracteristica, especificacion, M1..M13, promedio
    y veredicto): a 5,6 mm por columna no se lee. Por eso, cuando el ensayo tiene
    varias muestras, la tabla se TRANSPONE: las muestras pasan a ser filas y las
    caracteristicas columnas. Cinco caracteristicas entran comodas en 96 mm, hay
    267 mm de alto de sobra para 13 filas, y de paso se lee mejor: el inspector
    recorre una columna hacia abajo, en el mismo orden en que mide.

    Replica el formulario preimpreso de Calidad (COD.02 Tejido, COD.03 Corte y
    Costura): mismo encabezado, mismos rotulos y el recuadro de "ESTADO DE
    INSPECCION". Los rotulos salen de process.etiquetas.

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

    // Se transpone solo si hay muestras individuales que mostrar.
    $maxMuestras = (int) $resumen->max(fn ($f) => $f['valores']->max('muestra') ?? 0);
    $transponer = ! $publico && $maxMuestras > 1;

    $veredictoTexto = fn (?bool $v): string => match ($v) {
        true => 'Conforme',
        false => 'Fuera',
        default => '-',
    };
@endphp

@push('estilos')
    <style>
        @media print {
            @page {
                /* Media carta a lo largo: hoja carta cortada por la mitad a lo largo */
                size: 107.95mm 279.4mm;
                margin: 6mm;
            }
        }

        /* En pantalla se muestra con el ancho real del papel, para que lo que se
           ve sea lo que sale impreso. */
        .hoja-boleta {
            width: 107.95mm;
            min-height: 279.4mm;
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

        /* El QR se dimensiona por su contenedor, en milimetros. */
        .qr-boleta {
            width: 16mm;
            height: 16mm;
        }

        .qr-boleta svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        /* Tabla de resultados: compacta pero legible en 96 mm de ancho. */
        .tabla-resultados {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.8pt;
            line-height: 1.15;
        }

        .tabla-resultados th,
        .tabla-resultados td {
            border: 0.3mm solid #cbd5e1;
            padding: 0.7mm 0.6mm;
        }

        /* Los nombres de caracteristica van verticales en la cabecera cuando la
           tabla esta transpuesta: en 16 mm de columna no entran en horizontal. */
        .cabecera-caracteristica {
            font-size: 6pt;
            line-height: 1.05;
            vertical-align: bottom;
        }
    </style>
@endpush

<article class="hoja-a4 hoja-boleta mx-auto bg-white p-3 shadow-sm ring-1 ring-slate-200 print:ring-0">

    {{-- ===== Encabezado ===== --}}
    <header class="rounded border-2 border-pc-700 p-1.5">
        {{-- En 96 mm el logo y el titulo no caben uno al lado del otro: se apilan. --}}
        <div class="flex items-center gap-1.5 border-b border-slate-200 pb-1.5">
            <x-logo variante="marca" alto="h-7" />
            <div class="min-w-0 leading-tight">
                <p class="text-[8pt] font-bold text-slate-900">PLÁSTICOS CARMEN</p>
                <p class="text-[5pt] tracking-wide text-slate-500">tecnología en plásticos</p>
            </div>
        </div>

        <div class="mt-1.5 text-center">
            <h1 class="text-[8.5pt] font-extrabold uppercase leading-tight text-pc-800">
                {{ $proceso->tituloBoleta() }}
            </h1>
            @if ($publico)
                <p class="text-[5.5pt] font-bold uppercase tracking-wide text-rojo-600">
                    Certificado de calidad
                </p>
            @endif
            <p class="mt-0.5 font-mono text-[12pt] font-bold leading-none text-rojo-600">
                N&deg; {{ Str::afterLast($inspeccion->code, '-') }}
            </p>
            <p class="text-[5pt] text-slate-500">
                Sector {{ $lote->sector->name }}
                @if ($proceso->boleta_code) &middot; {{ $proceso->boleta_code }} @endif
            </p>
        </div>

        {{-- ===== Identificacion, con los rotulos del formulario ===== --}}
        <div class="mt-1.5 grid grid-cols-2 gap-x-2 gap-y-1 border-t border-slate-200 pt-1.5">
            <x-campo-boleta :titulo="$proceso->etiqueta('fecha', 'Fecha')"
                            :valor="$inspeccion->fecha->format('d/m/Y')" />

            <x-campo-boleta titulo="Hora"
                            :valor="$inspeccion->hora ? substr($inspeccion->hora, 0, 5) : null" />

            <x-campo-boleta :titulo="$proceso->etiqueta('maquina', 'Maquina')"
                            :valor="$inspeccion->machine?->code" />

            <x-campo-boleta titulo="Turno" :valor="$inspeccion->turno" />

            <x-campo-boleta :titulo="$proceso->etiqueta('producto', 'Codigo')"
                            :valor="$producto?->code" ancho="full" />

            <x-campo-boleta titulo="Lote" :valor="$lote->code" mono />

            <x-campo-boleta :titulo="$proceso->etiqueta('tarjeta', 'N. de Tarjeta')"
                            :valor="$lote->nro_tarjeta" />

            <x-campo-boleta :titulo="$proceso->etiqueta('peso', 'Peso')"
                            :valor="$lote->peso_neto ? $numero((float) $lote->peso_neto, 2).' kgrs' : null" />

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
                                :valor="$numero($inspeccion->total_buenas)" ancho="full" />
            @endif

            @if ($lote->sourceLot)
                <x-campo-boleta titulo="Proviene del lote" :valor="$lote->sourceLot->code" mono ancho="full" />
            @endif

            <x-campo-boleta titulo="Nombre del Operador" :valor="$inspeccion->operador" ancho="full" />
        </div>
    </header>

    {{-- ===== Estado de inspeccion ===== --}}
    <section class="mt-1.5">
        <h2 class="mb-1 text-center text-[7.5pt] font-extrabold uppercase leading-tight tracking-wide text-pc-800">
            {{ $proceso->tituloEstado() }}
        </h2>

        <div class="rounded border-2 {{ $colorEstado }} p-1.5">

            <div class="flex items-center justify-between gap-2">
                <p class="text-[10.5pt] font-extrabold uppercase leading-none">
                    {{ $inspeccion->estado_completo }}
                </p>

                <div class="shrink-0 text-center">
                    <div class="qr-boleta">
                        {!! \App\Support\Qr::svg($inspeccion->urlPublica(), 200) !!}
                    </div>
                    <p class="text-[4.5pt] leading-none text-slate-500">
                        {{ $publico ? 'verificar' : 'certificado' }}
                    </p>
                </div>
            </div>

            {{-- ===== Resultados de ensayo ===== --}}
            <div class="mt-1.5 rounded bg-white/70 p-0.5">

                @if ($transponer)
                    {{--
                        Tabla transpuesta: las muestras son filas y las
                        caracteristicas columnas. Es la unica forma de que 13
                        muestras entren en 96 mm de ancho y sigan siendo legibles.
                    --}}
                    <table class="tabla-resultados">
                        <thead>
                            <tr class="bg-slate-100 text-slate-700">
                                <th class="w-[11mm] text-left font-semibold">Muestra</th>
                                @foreach ($resumen as $fila)
                                    <th class="cabecera-caracteristica text-center font-semibold">
                                        {{ $fila['parametro']->label }}
                                        @if ($fila['parametro']->grupo)
                                            <span class="block font-normal">{{ $fila['parametro']->grupo }}</span>
                                        @endif
                                        @if ($fila['parametro']->unit)
                                            <span class="block font-normal text-slate-500">{{ $fila['parametro']->unit }}</span>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                            <tr class="bg-slate-50 text-slate-600">
                                <th class="text-left font-medium">Especif.</th>
                                @foreach ($resumen as $fila)
                                    <td class="text-center">{{ $fila['spec'] }}</td>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @for ($n = 1; $n <= $maxMuestras; $n++)
                                <tr>
                                    <th class="text-left font-medium text-slate-600">M {{ $n }}</th>
                                    @foreach ($resumen as $fila)
                                        @php $m = $fila['valores']->firstWhere('muestra', $n); @endphp
                                        <td @class([
                                            'text-center',
                                            'bg-rojo-100 font-semibold text-rojo-800' => $m?->en_especificacion === false,
                                        ])>{{ $m?->valor ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @endfor
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-100">
                                <th class="text-left font-bold">Prom.</th>
                                @foreach ($resumen as $fila)
                                    <td @class([
                                        'text-center font-bold',
                                        'text-rojo-700' => $fila['veredicto'] === false,
                                        'text-emerald-700' => $fila['veredicto'] === true,
                                    ])>
                                        {{ $fila['promedio'] !== null
                                            ? $numero($fila['promedio'], 2)
                                            : ($fila['valores']->first()?->valor ?? '') }}
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th class="text-left font-bold">Vered.</th>
                                @foreach ($resumen as $fila)
                                    <td @class([
                                        'text-center font-semibold',
                                        'bg-rojo-50 text-rojo-700' => $fila['veredicto'] === false,
                                        'text-emerald-700' => $fila['veredicto'] === true,
                                        'text-slate-400' => $fila['veredicto'] === null,
                                    ])>{{ $veredictoTexto($fila['veredicto']) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                @else
                    {{--
                        Una fila por caracteristica. Es el caso de Tejido (un valor
                        por caracteristica) y del certificado del cliente, que no
                        muestra las muestras individuales.
                    --}}
                    <table class="tabla-resultados">
                        <thead>
                            <tr class="bg-slate-100 text-left text-slate-700">
                                <th class="font-semibold">Característica</th>
                                <th class="font-semibold">Especif.</th>
                                <th class="text-center font-semibold">
                                    {{ $maxMuestras > 1 ? 'Prom.' : 'Result.' }}
                                </th>
                                <th class="text-center font-semibold">Vered.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($resumen as $fila)
                                <tr class="{{ $fila['veredicto'] === false ? 'bg-rojo-50' : '' }}">
                                    <th scope="row" class="text-left font-medium text-slate-800">
                                        {{ $fila['parametro']->etiqueta }}
                                    </th>
                                    <td class="text-slate-600">{{ $fila['spec'] }}</td>
                                    <td @class([
                                        'text-center font-bold',
                                        'text-rojo-700' => $fila['veredicto'] === false,
                                        'text-emerald-700' => $fila['veredicto'] === true,
                                        'text-slate-700' => $fila['veredicto'] === null,
                                    ])>
                                        {{ $fila['promedio'] !== null
                                            ? $numero($fila['promedio'], 2)
                                            : ($fila['valores']->first()?->valor ?? '') }}
                                    </td>
                                    <td @class([
                                        'text-center font-semibold',
                                        'text-rojo-700' => $fila['veredicto'] === false,
                                        'text-emerald-700' => $fila['veredicto'] === true,
                                        'text-slate-400' => $fila['veredicto'] === null,
                                    ])>{{ $veredictoTexto($fila['veredicto']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-2 text-center text-slate-400">
                                        Sin mediciones registradas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif
            </div>

            <p class="mt-0.5 text-[4.5pt] leading-tight text-slate-500">
                Ensayo según {{ $inspeccion->template->name }} (Rev:{{ $inspeccion->template->revision }})
            </p>

            <div class="mt-1">
                <p class="text-[5pt] font-bold uppercase tracking-wide text-slate-600">Observación</p>
                <p class="min-h-[7mm] rounded border border-slate-300 bg-white/70 p-1 text-[6pt] leading-snug text-slate-800">
                    {{ $inspeccion->observacion ?: '' }}
                </p>
            </div>
        </div>
    </section>

    {{-- Observacion interna: nunca en el certificado del cliente --}}
    @unless ($publico)
        <section class="mt-1.5">
            <p class="text-[5pt] font-bold uppercase tracking-wide text-slate-500">
                Observación interna
                <span class="block font-normal normal-case text-slate-400">no sale en el certificado del cliente</span>
            </p>
            <p class="min-h-[7mm] rounded border border-dashed border-slate-300 bg-slate-50 p-1 text-[6pt] leading-snug text-slate-700">
                {{ $inspeccion->observacion_interna ?: '' }}
            </p>
        </section>
    @endunless

    {{-- ===== Firmas =====
         En 96 mm tres firmas lado a lado quedarian de 32 mm cada una, que no
         alcanza para una firma real. Se apilan. --}}
    <footer class="mt-4 space-y-3.5">
        <div>
            <div class="h-7 border-b border-slate-500"></div>
            <p class="mt-0.5 truncate text-[6.5pt] font-semibold text-slate-800">{{ $inspeccion->operador ?: '' }}</p>
            <p class="text-[5.5pt] leading-tight text-slate-500">Operador</p>
        </div>
        <div>
            <div class="h-7 border-b border-slate-500"></div>
            <p class="mt-0.5 truncate text-[6.5pt] font-semibold text-slate-800">{{ $inspeccion->responsable ?: '' }}</p>
            <p class="text-[5.5pt] leading-tight text-slate-500">Responsable de Control de Calidad</p>
        </div>
        <div>
            <div class="h-7 border-b border-slate-500"></div>
            <p class="mt-0.5 text-[6.5pt] text-slate-400">&nbsp;</p>
            <p class="text-[5.5pt] leading-tight text-slate-500">Supervisor de turno / Jefe de Producción</p>
        </div>
    </footer>

    <div class="mt-2 border-t border-slate-200 pt-1 text-[4.5pt] leading-relaxed text-slate-400">
        <p>
            Plásticos Carmen S.R.L. &middot; Control de Calidad.
            @if ($inspeccion->published_at)
                Boleta emitida el {{ $inspeccion->published_at->format('d/m/Y H:i') }}.
            @endif
        </p>
        @if ($publico)
            <p class="break-all">
                Verificable escaneando el código QR de la etiqueta, o en
                {{ $inspeccion->urlPublica() }}
            </p>
        @endif
    </div>
</article>
