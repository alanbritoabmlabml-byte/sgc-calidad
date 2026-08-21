{{--
    Boleta de inspeccion / certificado de calidad. Formato A4 vertical.

    Variables:
      $inspeccion, $resumen
      $publico  true  -> certificado para el cliente: oculta la observacion interna
                          y las mediciones individuales, muestra el promedio y el veredicto.
                false -> boleta interna: muestra todo.
--}}

@php
    $publico = $publico ?? false;
    $lote = $inspeccion->lot;
    $producto = $lote->product;

    $colorEstado = match ($inspeccion->estado) {
        \App\Models\Inspection::CONFORME => 'border-emerald-600 text-emerald-700',
        \App\Models\Inspection::OBSERVADO => 'border-amber-600 text-amber-700',
        \App\Models\Inspection::RECHAZADO => 'border-red-600 text-red-700',
        default => 'border-slate-400 text-slate-600',
    };
@endphp

<article class="hoja-a4 mx-auto max-w-[210mm] bg-white p-6 shadow-sm ring-1 ring-slate-200 print:ring-0 sm:p-8">

    {{-- ===== Encabezado ===== --}}
    <header class="flex items-start justify-between gap-4 border-b-2 border-pc-700 pb-4">
        <div class="flex items-center gap-3">
            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-lg bg-pc-700 text-xl font-extrabold text-white">
                PC
            </span>
            <div>
                <p class="text-base font-bold leading-tight text-slate-900">PLASTICOS CARMEN S.R.L.</p>
                <p class="text-[11px] leading-tight text-slate-500">tecnologia en plasticos</p>
                <p class="mt-1 text-[11px] leading-tight text-slate-500">Sector {{ $lote->sector->name }}</p>
            </div>
        </div>

        <div class="text-right">
            <h1 class="text-sm font-bold uppercase leading-tight text-pc-800 sm:text-base">
                @if ($publico)
                    Certificado de calidad
                @else
                    Boleta de inspeccion
                @endif
            </h1>
            <p class="text-xs font-semibold uppercase text-slate-600">{{ $inspeccion->process->name }}</p>
            <p class="mt-1 font-mono text-lg font-bold text-red-600">{{ $inspeccion->code }}</p>
            @if ($inspeccion->process->boleta_code)
                <p class="text-[10px] text-slate-400">{{ $inspeccion->process->boleta_code }}</p>
            @endif
        </div>
    </header>

    {{-- ===== Veredicto ===== --}}
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border-2 {{ $colorEstado }} px-4 py-3">
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Estado de inspeccion</p>
            <p class="text-xl font-extrabold uppercase leading-tight">
                {{ $inspeccion->estado }}
                <span class="text-sm font-semibold">
                    @if ($inspeccion->estado === \App\Models\Inspection::CONFORME)
                        &mdash; Pasa conforme
                    @elseif ($inspeccion->estado === \App\Models\Inspection::OBSERVADO)
                        &mdash; Pasa con observacion
                    @elseif ($inspeccion->estado === \App\Models\Inspection::RECHAZADO)
                        &mdash; No conforme
                    @else
                        &mdash; Pendiente de cierre
                    @endif
                </span>
            </p>
        </div>

        {{-- QR: en la boleta interna sirve para verificar que apunte bien. --}}
        <div class="shrink-0 text-center">
            {!! \App\Support\Qr::svg($inspeccion->urlPublica(), 76) !!}
            <p class="mt-0.5 text-[8px] leading-none text-slate-400">verificar</p>
        </div>
    </div>

    {{-- ===== Identificacion ===== --}}
    <section class="mt-4">
        <h2 class="mb-2 text-[11px] font-bold uppercase tracking-wide text-slate-500">Identificacion del lote</h2>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-1.5 text-xs sm:grid-cols-3">
            <x-boleta-dato titulo="Lote" :valor="$lote->code" mono />
            <x-boleta-dato titulo="Codigo de producto" :valor="$producto?->code" />
            <x-boleta-dato titulo="N. de tarjeta / rollo" :valor="$lote->nro_tarjeta" />
            <x-boleta-dato titulo="N. lote de produccion" :valor="$lote->nro_lote_produccion" />
            <x-boleta-dato titulo="Fecha de inspeccion" :valor="$inspeccion->fecha->format('d/m/Y')" />
            <x-boleta-dato titulo="Hora" :valor="$inspeccion->hora ? substr($inspeccion->hora, 0, 5) : null" />
            <x-boleta-dato titulo="Maquina / telar" :valor="$inspeccion->machine?->code" />
            <x-boleta-dato titulo="Turno" :valor="$inspeccion->turno" />
            <x-boleta-dato titulo="Peso del rollo"
                           :valor="$lote->peso_neto ? rtrim(rtrim(number_format((float) $lote->peso_neto, 3, ',', '.'), '0'), ',').' kg' : null" />

            @if ($inspeccion->total_unidades !== null)
                <x-boleta-dato titulo="Total de unidades" :valor="number_format($inspeccion->total_unidades, 0, ',', '.')" />
                <x-boleta-dato titulo="Unidades falladas"
                               :valor="number_format((int) $inspeccion->total_falladas, 0, ',', '.').
                                       ($inspeccion->porcentaje_falladas !== null ? ' ('.number_format($inspeccion->porcentaje_falladas, 2, ',', '.').'%)' : '')" />
                <x-boleta-dato titulo="Unidades buenas" :valor="number_format((int) $inspeccion->total_buenas, 0, ',', '.')" />
            @endif

            @if ($lote->sourceLot)
                <x-boleta-dato titulo="Proviene del lote" :valor="$lote->sourceLot->code" mono />
            @endif
        </dl>
    </section>

    {{-- ===== Resultados ===== --}}
    <section class="mt-5">
        <h2 class="mb-2 text-[11px] font-bold uppercase tracking-wide text-slate-500">
            Resultados de ensayo
            <span class="font-normal normal-case text-slate-400">
                &middot; {{ $inspeccion->template->name }} (Rev:{{ $inspeccion->template->revision }})
            </span>
        </h2>

        @php
            // En el certificado del cliente se muestra el promedio y el veredicto.
            // En la boleta interna, ademas, cada muestra individual.
            $conMuestras = ! $publico && $resumen->contains(fn ($f) => $f['parametro']->muestras > 1);
            $maxMuestras = (int) $resumen->max(fn ($f) => $f['valores']->max('muestra') ?? 0);
        @endphp

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[11px]">
                <thead>
                    <tr class="bg-slate-100 text-left">
                        <th class="border border-slate-300 px-2 py-1.5 font-semibold">Caracteristica</th>
                        <th class="border border-slate-300 px-2 py-1.5 font-semibold">Especificacion</th>

                        @if ($conMuestras)
                            @for ($n = 1; $n <= $maxMuestras; $n++)
                                <th class="border border-slate-300 px-1 py-1.5 text-center font-semibold">M{{ $n }}</th>
                            @endfor
                        @endif

                        <th class="border border-slate-300 px-2 py-1.5 text-center font-semibold">
                            {{ $conMuestras || $maxMuestras > 1 ? 'Promedio' : 'Resultado' }}
                        </th>
                        <th class="border border-slate-300 px-2 py-1.5 text-center font-semibold">Veredicto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($resumen as $fila)
                        @php
                            $p = $fila['parametro'];
                            $valores = $fila['valores']->keyBy('muestra');
                            // Cuando el parametro no promedia (texto o seleccion),
                            // el resultado que se muestra es el primer valor cargado.
                            $resultado = $fila['promedio'] !== null
                                ? number_format($fila['promedio'], 2, ',', '.')
                                : ($fila['valores']->first()?->valor ?? '-');
                        @endphp

                        <tr class="{{ $fila['veredicto'] === false ? 'bg-red-50' : '' }}">
                            <th scope="row" class="border border-slate-300 px-2 py-1.5 text-left font-medium">
                                {{ $p->etiqueta }}
                            </th>
                            <td class="border border-slate-300 px-2 py-1.5 text-slate-600">
                                {{ $fila['spec'] }}
                            </td>

                            @if ($conMuestras)
                                @for ($n = 1; $n <= $maxMuestras; $n++)
                                    @php $m = $valores->get($n); @endphp
                                    <td @class([
                                        'border border-slate-300 px-1 py-1.5 text-center',
                                        'bg-red-100 font-semibold text-red-800' => $m?->en_especificacion === false,
                                    ])>
                                        {{ $m?->valor ?? '' }}
                                    </td>
                                @endfor
                            @endif

                            <td @class([
                                'border border-slate-300 px-2 py-1.5 text-center font-bold',
                                'text-red-700' => $fila['veredicto'] === false,
                                'text-emerald-700' => $fila['veredicto'] === true,
                            ])>
                                {{ $resultado }}
                            </td>

                            <td class="border border-slate-300 px-2 py-1.5 text-center">
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
                            <td colspan="4" class="border border-slate-300 px-2 py-4 text-center text-slate-400">
                                No hay mediciones registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ===== Observaciones ===== --}}
    <section class="mt-5 grid gap-3 {{ $publico ? '' : 'sm:grid-cols-2' }}">
        <div>
            <h2 class="mb-1 text-[11px] font-bold uppercase tracking-wide text-slate-500">Observaciones</h2>
            <div class="min-h-[3.5rem] rounded border border-slate-300 p-2 text-[11px] text-slate-700">
                {{ $inspeccion->observacion ?: '-' }}
            </div>
        </div>

        @unless ($publico)
            <div>
                <h2 class="mb-1 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                    Observacion interna
                    <span class="font-normal normal-case text-slate-400">(no sale en el certificado)</span>
                </h2>
                <div class="min-h-[3.5rem] rounded border border-dashed border-slate-300 bg-slate-50 p-2 text-[11px] text-slate-700">
                    {{ $inspeccion->observacion_interna ?: '-' }}
                </div>
            </div>
        @endunless
    </section>

    {{-- ===== Firmas ===== --}}
    <footer class="mt-6 grid grid-cols-2 gap-8 text-[11px]">
        <div>
            <div class="h-9 border-b border-slate-400"></div>
            <p class="mt-1 font-semibold text-slate-700">{{ $inspeccion->operador ?: '' }}</p>
            <p class="text-slate-500">Operador</p>
        </div>
        <div>
            <div class="h-9 border-b border-slate-400"></div>
            <p class="mt-1 font-semibold text-slate-700">{{ $inspeccion->responsable ?: '' }}</p>
            <p class="text-slate-500">Responsable de Control de Calidad</p>
        </div>
    </footer>

    <div class="mt-5 border-t border-slate-200 pt-2 text-[9px] leading-relaxed text-slate-400">
        <p>
            Documento generado por el sistema de gestion de calidad de Plasticos Carmen S.R.L.
            @if ($inspeccion->published_at)
                Emitido el {{ $inspeccion->published_at->format('d/m/Y H:i') }}.
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
