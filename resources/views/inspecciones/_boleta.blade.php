{{--
    Boleta de inspeccion / certificado de calidad. Formato A4 vertical.

    Replica el formulario preimpreso de Calidad (COD.02 para Tejido, COD.03 para
    Corte y Costura): mismo encabezado, mismos rotulos y el recuadro grande de
    "ESTADO DE INSPECCION". Los rotulos salen de process.etiquetas, asi que cada
    proceso imprime con sus propias palabras sin tocar esta vista.

    Variables:
      $inspeccion, $resumen
      $publico  true  -> certificado para el cliente: oculta la observacion interna
                          y las muestras individuales.
                false -> boleta interna: muestra todo.
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
        \App\Models\Inspection::RECHAZADO => 'border-red-600 bg-red-50 text-red-800',
        default => 'border-slate-400 bg-slate-50 text-slate-700',
    };

    $leyendaEstado = match ($inspeccion->estado) {
        \App\Models\Inspection::CONFORME => 'Pasa conforme',
        \App\Models\Inspection::OBSERVADO => 'Pasa con observacion',
        \App\Models\Inspection::RECHAZADO => 'No conforme',
        default => 'Pendiente de cierre',
    };
@endphp

<article class="hoja-a4 mx-auto max-w-[210mm] bg-white p-5 shadow-sm ring-1 ring-slate-200 print:ring-0 sm:p-7">

    {{-- ===== Encabezado, como la cabecera del formulario en papel ===== --}}
    <header class="rounded-lg border-2 border-pc-700 p-3">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-2.5">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-pc-700 text-lg font-extrabold text-white">
                    PC
                </span>
                <div>
                    <p class="text-sm font-bold leading-tight text-slate-900">PLASTICOS CARMEN</p>
                    <p class="text-[9px] leading-tight tracking-wide text-slate-500">tecnologia en plasticos</p>
                </div>
            </div>

            <div class="text-center">
                <h1 class="text-[13px] font-extrabold uppercase leading-tight text-pc-800 sm:text-sm">
                    {{ $proceso->tituloBoleta() }}
                </h1>
                @if ($publico)
                    <p class="text-[9px] font-bold uppercase tracking-wide text-pc-600">
                        Certificado de calidad
                    </p>
                @endif
                <p class="mt-1 font-mono text-base font-bold text-red-600">
                    N&deg; {{ Str::afterLast($inspeccion->code, '-') }}
                </p>
            </div>
        </div>

        {{-- ===== Identificacion, con los rotulos del formulario ===== --}}
        <div class="mt-3 grid gap-x-5 gap-y-2 border-t border-slate-200 pt-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-campo-boleta :titulo="$proceso->etiqueta('fecha', 'Fecha')"
                            :valor="$inspeccion->fecha->format('d/m/Y')" />

            <x-campo-boleta titulo="Hora"
                            :valor="$inspeccion->hora ? substr($inspeccion->hora, 0, 5) : null" />

            <x-campo-boleta :titulo="$proceso->etiqueta('maquina', 'Maquina')"
                            :valor="$inspeccion->machine?->code" />

            <x-campo-boleta :titulo="$proceso->etiqueta('producto', 'Codigo')"
                            :valor="$producto?->code" />

            <x-campo-boleta :titulo="$proceso->etiqueta('peso', 'Peso')"
                            :valor="$lote->peso_neto ? $numero((float) $lote->peso_neto, 2).' kgrs' : null" />

            <x-campo-boleta :titulo="$proceso->etiqueta('tarjeta', 'N. de Tarjeta')"
                            :valor="$lote->nro_tarjeta" />

            <x-campo-boleta titulo="Lote" :valor="$lote->code" mono />

            <x-campo-boleta titulo="N. de Lote" :valor="$lote->nro_lote_produccion" />

            <x-campo-boleta titulo="Turno" :valor="$inspeccion->turno" />

            {{-- Cantidades: solo cuando el proceso las registra --}}
            @if ($inspeccion->total_unidades !== null)
                <x-campo-boleta :titulo="$proceso->etiqueta('unidades', 'Total de unidades')"
                                :valor="$numero($inspeccion->total_unidades)" />

                <x-campo-boleta :titulo="$proceso->etiqueta('falladas', 'Falladas')"
                                :valor="$numero((int) $inspeccion->total_falladas).
                                    ($inspeccion->porcentaje_falladas !== null
                                        ? ' ('.$numero($inspeccion->porcentaje_falladas, 2).'%)'
                                        : '')" />

                <x-campo-boleta :titulo="$proceso->etiqueta('buenas', 'Total buenas')"
                                :valor="$numero((int) $inspeccion->total_buenas)" />
            @endif

            @if ($lote->sourceLot)
                <x-campo-boleta titulo="Proviene del lote" :valor="$lote->sourceLot->code" mono />
            @endif

            <x-campo-boleta titulo="Nombre del Operador" :valor="$inspeccion->operador" ancho="full" />
        </div>
    </header>

    {{-- ===== Estado de inspeccion: el recuadro grande del formulario ===== --}}
    <section class="mt-4">
        <h2 class="mb-1.5 text-center text-[13px] font-extrabold uppercase tracking-wide text-pc-800">
            {{ $proceso->tituloEstado() }}
        </h2>

        <div class="rounded-lg border-2 {{ $colorEstado }} p-3">

            {{-- Veredicto y QR --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xl font-extrabold uppercase leading-none">
                    {{ $inspeccion->estado }}
                    <span class="text-sm font-semibold">&mdash; {{ $leyendaEstado }}</span>
                </p>

                <div class="shrink-0 text-center">
                    {!! \App\Support\Qr::svg($inspeccion->urlPublica(), 68) !!}
                    <p class="mt-0.5 text-[7px] leading-none text-slate-500">
                        {{ $publico ? 'verificar' : 'certificado' }}
                    </p>
                </div>
            </div>

            {{-- Resultados de ensayo --}}
            @php
                $conMuestras = ! $publico && $resumen->contains(fn ($f) => $f['parametro']->muestras > 1);
                $maxMuestras = (int) $resumen->max(fn ($f) => $f['valores']->max('muestra') ?? 0);
            @endphp

            <div class="mt-3 overflow-x-auto rounded bg-white/70">
                <table class="w-full border-collapse text-[10px]">
                    <thead>
                        <tr class="bg-slate-100 text-left text-slate-700">
                            <th class="border border-slate-300 px-2 py-1 font-semibold">Caracteristica</th>
                            <th class="border border-slate-300 px-2 py-1 font-semibold">Especificacion</th>

                            @if ($conMuestras)
                                @for ($n = 1; $n <= $maxMuestras; $n++)
                                    <th class="border border-slate-300 px-1 py-1 text-center font-semibold">M{{ $n }}</th>
                                @endfor
                            @endif

                            <th class="border border-slate-300 px-2 py-1 text-center font-semibold">
                                {{ $maxMuestras > 1 ? 'Promedio' : 'Resultado' }}
                            </th>
                            <th class="border border-slate-300 px-2 py-1 text-center font-semibold">Veredicto</th>
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

                            <tr class="{{ $fila['veredicto'] === false ? 'bg-red-50' : 'bg-white' }}">
                                <th scope="row" class="border border-slate-300 px-2 py-1 text-left font-medium text-slate-800">
                                    {{ $p->etiqueta }}
                                </th>
                                <td class="border border-slate-300 px-2 py-1 text-slate-600">{{ $fila['spec'] }}</td>

                                @if ($conMuestras)
                                    @for ($n = 1; $n <= $maxMuestras; $n++)
                                        @php $m = $valores->get($n); @endphp
                                        <td @class([
                                            'border border-slate-300 px-1 py-1 text-center',
                                            'bg-red-100 font-semibold text-red-800' => $m?->en_especificacion === false,
                                        ])>{{ $m?->valor ?? '' }}</td>
                                    @endfor
                                @endif

                                <td @class([
                                    'border border-slate-300 px-2 py-1 text-center font-bold',
                                    'text-red-700' => $fila['veredicto'] === false,
                                    'text-emerald-700' => $fila['veredicto'] === true,
                                    'text-slate-700' => $fila['veredicto'] === null,
                                ])>{{ $resultado }}</td>

                                <td class="border border-slate-300 px-2 py-1 text-center">
                                    @if ($fila['veredicto'] === true)
                                        <span class="font-semibold text-emerald-700">Conforme</span>
                                    @elseif ($fila['veredicto'] === false)
                                        <span class="font-semibold text-red-700">Fuera de spec.</span>
                                    @else
                                        <span class="text-slate-400">Sin evaluar</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="border border-slate-300 bg-white px-2 py-4 text-center text-slate-400">
                                    Sin mediciones registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="mt-1 text-[8px] text-slate-500">
                Ensayo segun {{ $inspeccion->template->name }} (Rev:{{ $inspeccion->template->revision }})
            </p>

            {{-- Observacion --}}
            <div class="mt-2">
                <p class="text-[9px] font-bold uppercase tracking-wide text-slate-600">Observacion</p>
                <p class="min-h-[2rem] rounded border border-slate-300 bg-white/70 p-1.5 text-[10px] text-slate-800">
                    {{ $inspeccion->observacion ?: '' }}
                </p>
            </div>
        </div>
    </section>

    {{-- Observacion interna: nunca en el certificado del cliente --}}
    @unless ($publico)
        <section class="mt-3">
            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-500">
                Observacion interna
                <span class="font-normal normal-case text-slate-400">&middot; no sale en el certificado del cliente</span>
            </p>
            <p class="min-h-[2rem] rounded border border-dashed border-slate-300 bg-slate-50 p-1.5 text-[10px] text-slate-700">
                {{ $inspeccion->observacion_interna ?: '' }}
            </p>
        </section>
    @endunless

    {{-- ===== Firmas ===== --}}
    <footer class="mt-6 flex items-end justify-between gap-6 text-[10px]">
        <div class="grid flex-1 grid-cols-2 gap-8">
            <div>
                <div class="h-8 border-b border-slate-500"></div>
                <p class="mt-1 font-semibold text-slate-800">{{ $inspeccion->operador ?: '' }}</p>
                <p class="text-slate-500">Operador</p>
            </div>
            <div>
                <div class="h-8 border-b border-slate-500"></div>
                <p class="mt-1 font-semibold text-slate-800">{{ $inspeccion->responsable ?: '' }}</p>
                <p class="text-slate-500">Responsable de Control de Calidad</p>
            </div>
        </div>

        {{-- Codigo del formulario, como en el pie del papel --}}
        @if ($proceso->boleta_code)
            <p class="shrink-0 text-[10px] font-bold text-slate-600">{{ $proceso->boleta_code }}</p>
        @endif
    </footer>

    <div class="mt-4 border-t border-slate-200 pt-1.5 text-[8px] leading-relaxed text-slate-400">
        <p>
            Plasticos Carmen S.R.L. &middot; Sector {{ $lote->sector->name }} &middot; Control de Calidad.
            @if ($inspeccion->published_at)
                Boleta emitida el {{ $inspeccion->published_at->format('d/m/Y H:i') }}.
            @endif
        </p>
        @if ($publico)
            <p>
                La autenticidad de este certificado se verifica escaneando el codigo QR de la
                etiqueta del lote, o ingresando a {{ $inspeccion->urlPublica() }}
            </p>
        @endif
    </div>
</article>
