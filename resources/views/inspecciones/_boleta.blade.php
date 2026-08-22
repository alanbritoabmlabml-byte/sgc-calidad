{{--
    Boleta de inspeccion / certificado de calidad. Formato MEDIA CARTA vertical
    (139,7 x 215,9 mm), que es la mitad de una hoja carta: dos boletas por hoja
    si se imprime en carta, o una por hoja en media carta.

    Replica el formulario preimpreso de Calidad (COD.02 para Tejido, COD.03 para
    Corte y Costura): mismo encabezado, mismos rotulos y el recuadro de "ESTADO
    DE INSPECCION". Los rotulos salen de process.etiquetas, asi que cada proceso
    imprime con sus propias palabras sin tocar esta vista.

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
@endphp

@push('estilos')
    <style>
        @media print {
            @page {
                /* Media carta vertical */
                size: 139.7mm 215.9mm;
                margin: 7mm;
            }
        }

        /* En pantalla se muestra con el ancho real del papel, para que lo que se
           ve sea lo que sale impreso. */
        .hoja-media-carta {
            width: 139.7mm;
            min-height: 215.9mm;
        }

        /* El QR se dimensiona por su contenedor, en milimetros. */
        .hoja-media-carta .w-\[15mm\] svg {
            display: block;
            width: 100%;
            height: auto;
        }

        @media print {
            .hoja-media-carta {
                width: auto;
                min-height: 0;
                box-shadow: none !important;
                border: 0 !important;
            }
        }
    </style>
@endpush

<article class="hoja-a4 hoja-media-carta mx-auto bg-white p-4 text-[9.5pt] shadow-sm ring-1 ring-slate-200 print:ring-0">

    {{-- ===== Encabezado ===== --}}
    <header class="rounded border-2 border-pc-700 p-2">
        <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-1.5">
                <x-logo variante="marca" alto="h-8" />
                <div class="leading-tight">
                    <p class="text-[8pt] font-bold text-slate-900">PLÁSTICOS CARMEN</p>
                    <p class="text-[5.5pt] tracking-wide text-slate-500">tecnología en plásticos</p>
                    <p class="text-[5.5pt] text-slate-500">Sector {{ $lote->sector->name }}</p>
                </div>
            </div>

            <div class="text-right leading-tight">
                <h1 class="text-[8pt] font-extrabold uppercase text-pc-800">
                    {{ $proceso->tituloBoleta() }}
                </h1>
                @if ($publico)
                    <p class="text-[5.5pt] font-bold uppercase tracking-wide text-rojo-600">
                        Certificado de calidad
                    </p>
                @endif
                <p class="mt-0.5 font-mono text-[11pt] font-bold leading-none text-rojo-600">
                    N&deg; {{ Str::afterLast($inspeccion->code, '-') }}
                </p>
                @if ($proceso->boleta_code)
                    <p class="text-[5.5pt] text-slate-400">{{ $proceso->boleta_code }}</p>
                @endif
            </div>
        </div>

        {{-- ===== Identificacion, con los rotulos del formulario ===== --}}
        <div class="mt-1.5 grid grid-cols-2 gap-x-3 gap-y-1 border-t border-slate-200 pt-1.5">
            <x-campo-boleta :titulo="$proceso->etiqueta('fecha', 'Fecha')"
                            :valor="$inspeccion->fecha->format('d/m/Y')" />

            <x-campo-boleta titulo="Hora"
                            :valor="$inspeccion->hora ? substr($inspeccion->hora, 0, 5) : null" />

            <x-campo-boleta :titulo="$proceso->etiqueta('producto', 'Codigo')"
                            :valor="$producto?->code" />

            <x-campo-boleta :titulo="$proceso->etiqueta('maquina', 'Maquina')"
                            :valor="$inspeccion->machine?->code" />

            <x-campo-boleta titulo="Lote" :valor="$lote->code" mono />

            <x-campo-boleta :titulo="$proceso->etiqueta('tarjeta', 'N. de Tarjeta')"
                            :valor="$lote->nro_tarjeta" />

            <x-campo-boleta :titulo="$proceso->etiqueta('peso', 'Peso')"
                            :valor="$lote->peso_neto ? $numero((float) $lote->peso_neto, 2).' kgrs' : null" />

            <x-campo-boleta titulo="Turno" :valor="$inspeccion->turno" />

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

            @if ($lote->nro_lote_produccion)
                <x-campo-boleta titulo="N. de Lote" :valor="$lote->nro_lote_produccion" />
            @endif

            @if ($lote->sourceLot)
                <x-campo-boleta titulo="Proviene del lote" :valor="$lote->sourceLot->code" mono />
            @endif

            <x-campo-boleta titulo="Nombre del Operador" :valor="$inspeccion->operador" ancho="full" />
        </div>
    </header>

    {{-- ===== Estado de inspeccion ===== --}}
    <section class="mt-2">
        <h2 class="mb-1 text-center text-[8pt] font-extrabold uppercase tracking-wide text-pc-800">
            {{ $proceso->tituloEstado() }}
        </h2>

        <div class="rounded border-2 {{ $colorEstado }} p-1.5">

            <div class="flex items-center justify-between gap-2">
                <p class="text-[12pt] font-extrabold uppercase leading-none">
                    {{ $inspeccion->estado_completo }}
                </p>

                <div class="shrink-0 text-center">
                    <div class="w-[15mm]">
                        {!! \App\Support\Qr::svg($inspeccion->urlPublica(), 200) !!}
                    </div>
                    <p class="text-[4.5pt] leading-none text-slate-500">
                        {{ $publico ? 'verificar' : 'certificado' }}
                    </p>
                </div>
            </div>

            {{-- Resultados de ensayo --}}
            @php
                $conMuestras = ! $publico && $resumen->contains(fn ($f) => $f['parametro']->muestras > 1);
                $maxMuestras = (int) $resumen->max(fn ($f) => $f['valores']->max('muestra') ?? 0);
            @endphp

            <div class="mt-1.5 overflow-x-auto rounded bg-white/70">
                <table class="w-full border-collapse text-[6.5pt]">
                    <thead>
                        <tr class="bg-slate-100 text-left text-slate-700">
                            <th class="border border-slate-300 px-1 py-0.5 font-semibold">Característica</th>
                            <th class="border border-slate-300 px-1 py-0.5 font-semibold">Especif.</th>

                            @if ($conMuestras)
                                @for ($n = 1; $n <= $maxMuestras; $n++)
                                    <th class="border border-slate-300 px-0.5 py-0.5 text-center font-semibold">{{ $n }}</th>
                                @endfor
                            @endif

                            <th class="border border-slate-300 px-1 py-0.5 text-center font-semibold">
                                {{ $maxMuestras > 1 ? 'Prom.' : 'Result.' }}
                            </th>
                            <th class="border border-slate-300 px-1 py-0.5 text-center font-semibold">Veredicto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($resumen as $fila)
                            @php
                                $p = $fila['parametro'];
                                $valores = $fila['valores']->keyBy('muestra');
                                $resultado = $fila['promedio'] !== null
                                    ? $numero($fila['promedio'], 2)
                                    : ($fila['valores']->first()?->valor ?? '');
                            @endphp

                            <tr class="{{ $fila['veredicto'] === false ? 'bg-rojo-50' : 'bg-white' }}">
                                <th scope="row" class="border border-slate-300 px-1 py-0.5 text-left font-medium text-slate-800">
                                    {{ $p->etiqueta }}
                                </th>
                                <td class="border border-slate-300 px-1 py-0.5 text-slate-600">{{ $fila['spec'] }}</td>

                                @if ($conMuestras)
                                    @for ($n = 1; $n <= $maxMuestras; $n++)
                                        @php $m = $valores->get($n); @endphp
                                        <td @class([
                                            'border border-slate-300 px-0.5 py-0.5 text-center',
                                            'bg-rojo-100 font-semibold text-rojo-800' => $m?->en_especificacion === false,
                                        ])>{{ $m?->valor ?? '' }}</td>
                                    @endfor
                                @endif

                                <td @class([
                                    'border border-slate-300 px-1 py-0.5 text-center font-bold',
                                    'text-rojo-700' => $fila['veredicto'] === false,
                                    'text-emerald-700' => $fila['veredicto'] === true,
                                    'text-slate-700' => $fila['veredicto'] === null,
                                ])>{{ $resultado }}</td>

                                <td class="border border-slate-300 px-1 py-0.5 text-center">
                                    @if ($fila['veredicto'] === true)
                                        <span class="font-semibold text-emerald-700">Conforme</span>
                                    @elseif ($fila['veredicto'] === false)
                                        <span class="font-semibold text-rojo-700">Fuera spec.</span>
                                    @else
                                        <span class="text-slate-400">Sin evaluar</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="border border-slate-300 bg-white px-1 py-2 text-center text-slate-400">
                                    Sin mediciones registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="mt-0.5 text-[5pt] text-slate-500">
                Ensayo según {{ $inspeccion->template->name }} (Rev:{{ $inspeccion->template->revision }})
            </p>

            <div class="mt-1">
                <p class="text-[5.5pt] font-bold uppercase tracking-wide text-slate-600">Observación</p>
                <p class="min-h-[6mm] rounded border border-slate-300 bg-white/70 p-1 text-[6.5pt] text-slate-800">
                    {{ $inspeccion->observacion ?: '' }}
                </p>
            </div>
        </div>
    </section>

    {{-- Observacion interna: nunca en el certificado del cliente --}}
    @unless ($publico)
        <section class="mt-1.5">
            <p class="text-[5.5pt] font-bold uppercase tracking-wide text-slate-500">
                Observación interna
                <span class="font-normal normal-case text-slate-400">&middot; no sale en el certificado del cliente</span>
            </p>
            <p class="min-h-[6mm] rounded border border-dashed border-slate-300 bg-slate-50 p-1 text-[6.5pt] text-slate-700">
                {{ $inspeccion->observacion_interna ?: '' }}
            </p>
        </section>
    @endunless

    {{-- ===== Firmas ===== --}}
    <footer class="mt-6">
        <div class="grid grid-cols-3 gap-3 text-[6pt]">
            <div>
                <div class="h-7 border-b border-slate-500"></div>
                <p class="mt-0.5 truncate font-semibold text-slate-800">{{ $inspeccion->operador ?: '' }}</p>
                <p class="leading-tight text-slate-500">Operador</p>
            </div>
            <div>
                <div class="h-7 border-b border-slate-500"></div>
                <p class="mt-0.5 truncate font-semibold text-slate-800">{{ $inspeccion->responsable ?: '' }}</p>
                <p class="leading-tight text-slate-500">Responsable de Control de Calidad</p>
            </div>
            {{-- Firma de produccion: la boleta la avala tambien el sector que produjo --}}
            <div>
                <div class="h-7 border-b border-slate-500"></div>
                <p class="mt-0.5 text-slate-400">&nbsp;</p>
                <p class="leading-tight text-slate-500">
                    Supervisor de turno / Jefe de Producción
                </p>
            </div>
        </div>

        @if ($proceso->boleta_code)
            <p class="mt-1 text-right text-[6pt] font-bold text-slate-600">{{ $proceso->boleta_code }}</p>
        @endif
    </footer>

    <div class="mt-2 border-t border-slate-200 pt-1 text-[5pt] leading-relaxed text-slate-400">
        <p>
            Plásticos Carmen S.R.L. &middot; Sector {{ $lote->sector->name }} &middot; Control de Calidad.
            @if ($inspeccion->published_at)
                Boleta emitida el {{ $inspeccion->published_at->format('d/m/Y H:i') }}.
            @endif
        </p>
        @if ($publico)
            <p>
                La autenticidad de este certificado se verifica escaneando el código QR de la
                etiqueta del lote, o ingresando a {{ $inspeccion->urlPublica() }}
            </p>
        @endif
    </div>
</article>
