{{--
    Formulario de inspeccion, compartido por crear y editar.
    Variables: $lote, $proceso, $plantilla, $maquinas, $configGrilla, $inspeccion (opcional)

    La grilla de mediciones se maneja con Alpine: calcula promedios, marca en rojo
    lo que cae fuera de especificacion y muestra el estado sugerido en vivo. El
    servidor vuelve a evaluar todo al guardar, esto es solo el aviso inmediato.
--}}

@php
    $inspeccion = $inspeccion ?? null;
    $simples = $plantilla->parameters->where('muestras', 1);
    $multiples = $plantilla->parameters->where('muestras', '>', 1);

    // Si la validacion fallo, se reponen los valores que el inspector habia
    // escrito, en lugar de los que estaban guardados.
    $config = $configGrilla;

    if (old('m')) {
        $config['valores'] = old('m');
    }
@endphp

<div x-data="grillaMediciones(@js($config))">

    {{-- ===== Cabecera de la boleta ===== --}}
    <div class="tarjeta p-4 sm:p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900">
                {{ $plantilla->name }}
                <span class="font-normal text-slate-400">Rev:{{ $plantilla->revision }}</span>
            </h2>
            @if ($proceso->boleta_code)
                <span class="badge bg-slate-100 text-slate-600 ring-slate-300">{{ $proceso->boleta_code }}</span>
            @endif
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Una inspeccion no puede registrarse con fecha u hora futura. --}}
            <div>
                <label for="fecha" class="etiqueta">Fecha *</label>
                <input id="fecha" name="fecha" type="date" class="campo" required
                       max="{{ now()->format('Y-m-d') }}"
                       value="{{ old('fecha', optional($inspeccion?->fecha)->format('Y-m-d') ?? optional($lote->fecha)->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
            </div>
            <div>
                <label for="hora" class="etiqueta">Hora</label>
                <input id="hora" name="hora" type="time" class="campo"
                       value="{{ old('hora', $inspeccion?->hora ? substr($inspeccion->hora, 0, 5) : ($lote->hora ? substr($lote->hora, 0, 5) : now()->format('H:i'))) }}">
            </div>
            <div>
                <label for="machine_id" class="etiqueta">Maquina</label>
                <select id="machine_id" name="machine_id" class="campo">
                    <option value="">Sin definir</option>
                    @foreach ($maquinas as $m)
                        <option value="{{ $m->id }}"
                            @selected(old('machine_id', $inspeccion?->machine_id ?? $lote->machine_id) == $m->id)>
                            {{ $m->code }} @if ($m->name) - {{ $m->name }} @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="turno" class="etiqueta">Turno</label>
                <select id="turno" name="turno" class="campo">
                    <option value="">Sin definir</option>
                    <option value="Dia" @selected(old('turno', $inspeccion?->turno ?? $lote->turno) === 'Dia')>Dia</option>
                    <option value="Noche" @selected(old('turno', $inspeccion?->turno ?? $lote->turno) === 'Noche')>Noche</option>
                </select>
            </div>
            <div>
                <label for="operador" class="etiqueta">Nombre del operador</label>
                <input id="operador" name="operador" class="campo"
                       value="{{ old('operador', $inspeccion?->operador) }}" placeholder="Ej. Moisés Góngora">
                <p class="mt-1 text-xs text-slate-400">Solo letras: no admite números ni símbolos.</p>
            </div>
            <div>
                <label class="etiqueta">Responsable de Control de Calidad</label>
                {{-- No se elige: es siempre quien está cargando la inspección, para
                     que la boleta no pueda atribuirse a otra persona. --}}
                <div class="campo flex items-center justify-between gap-2 bg-slate-50 text-slate-700">
                    <span class="truncate">{{ auth()->user()->nombre_completo }}</span>
                    <span class="shrink-0 text-xs text-slate-400">automático</span>
                </div>
                <p class="mt-1 text-xs text-slate-400">
                    Es el usuario con la sesión abierta. Se imprime como responsable en la boleta.
                </p>
            </div>

            {{-- Cantidades: relevantes en Corte y Costura e Impresion --}}
            <div>
                <label for="total_unidades" class="etiqueta">Total de unidades</label>
                <input id="total_unidades" name="total_unidades" type="number" min="0" inputmode="numeric" class="campo"
                       value="{{ old('total_unidades', $inspeccion?->total_unidades) }}" placeholder="Ej. 2598">
            </div>
            <div>
                <label for="total_falladas" class="etiqueta">Unidades falladas</label>
                <input id="total_falladas" name="total_falladas" type="number" min="0" inputmode="numeric" class="campo"
                       value="{{ old('total_falladas', $inspeccion?->total_falladas) }}" placeholder="Ej. 61">
            </div>
        </div>
    </div>

    {{-- ===== Caracteristicas de valor unico ===== --}}
    @if ($simples->isNotEmpty())
        <div class="tarjeta mt-5 overflow-hidden">
            <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                <h2 class="text-sm font-semibold text-slate-900">Lecturas</h2>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach ($simples as $p)
                    <div class="grid items-center gap-2 px-4 py-3 sm:grid-cols-12 sm:px-5">
                        <div class="sm:col-span-6">
                            <label for="p{{ $p->id }}" class="block text-sm font-medium text-slate-800">
                                {{ $p->etiqueta }}
                                @if ($p->requerido)
                                    <span class="text-red-600" title="Si queda fuera de especificacion, la inspeccion se rechaza">*</span>
                                @endif
                            </label>
                            <p class="text-xs text-slate-500">
                                Especificacion: {{ $p->specTexto($lote->product) }}
                                @php [$min, $max] = $p->limites($lote->product); @endphp
                                @if ($min !== null || $max !== null)
                                    <span class="text-slate-400">
                                        ({{ $min !== null ? number_format($min, 2, ',', '.') : 'sin minimo' }}
                                        a
                                        {{ $max !== null ? number_format($max, 2, ',', '.') : 'sin maximo' }})
                                    </span>
                                @endif
                            </p>
                        </div>

                        <div class="sm:col-span-6">
                            @if ($p->tipo === 'select')
                                <select id="p{{ $p->id }}" name="m[{{ $p->id }}][1]" class="campo"
                                        x-model="valores[{{ $p->id }}][1]"
                                        :class="claseCelda({{ $p->id }}, valor({{ $p->id }}, 1))">
                                    <option value="">Sin dato</option>
                                    @foreach (($p->opciones['valores'] ?? []) as $opcion)
                                        <option value="{{ $opcion }}"
                                            @selected(old("m.{$p->id}.1", $inspeccion?->measurements->firstWhere('test_parameter_id', $p->id)?->valor) === $opcion)>
                                            {{ $opcion }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <input id="p{{ $p->id }}" name="m[{{ $p->id }}][1]" class="campo"
                                       type="{{ $p->tipo === 'numeric' ? 'number' : 'text' }}"
                                       @if ($p->tipo === 'numeric') step="0.01" inputmode="decimal" @endif
                                       value="{{ old("m.{$p->id}.1", $inspeccion?->measurements->firstWhere('test_parameter_id', $p->id)?->valor) }}"
                                       x-model="valores[{{ $p->id }}][1]"
                                       :class="claseCelda({{ $p->id }}, valor({{ $p->id }}, 1))"
                                       placeholder="{{ $p->unit }}">
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ===== Caracteristicas con varias muestras ===== --}}
    @if ($multiples->isNotEmpty())
        <div class="tarjeta mt-5 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 sm:px-5">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Muestras</h2>
                    <p class="text-xs text-slate-500">
                        Hasta {{ $plantilla->muestras_max }} muestras. El promedio define el veredicto.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="quitarMuestra()" class="btn-secundario !px-3 !py-1.5 text-xs"
                            :disabled="muestras <= 1">Quitar muestra</button>
                    <button type="button" @click="agregarMuestra()" class="btn-secundario !px-3 !py-1.5 text-xs"
                            :disabled="muestras >= muestrasMax">Agregar muestra</button>
                    <span class="text-xs text-slate-500" x-text="muestras + ' / ' + muestrasMax"></span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="sticky left-0 z-10 bg-slate-50 px-3 py-2 text-left font-semibold">
                                Caracteristica
                            </th>
                            <template x-for="n in rangoMuestras" :key="n">
                                <th class="px-1.5 py-2 text-center font-semibold" x-text="'M' + n"></th>
                            </template>
                            <th class="px-3 py-2 text-center font-semibold">Prom.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($multiples as $p)
                            @php
                                [$min, $max] = $p->limites($lote->product);
                                $previos = $inspeccion
                                    ? $inspeccion->measurements->where('test_parameter_id', $p->id)->keyBy('muestra')
                                    : collect();
                            @endphp
                            <tr>
                                <th scope="row" class="sticky left-0 z-10 bg-white px-3 py-2 text-left align-top">
                                    <span class="block text-sm font-medium text-slate-800">{{ $p->etiqueta }}
                                        @if ($p->requerido)<span class="text-red-600">*</span>@endif
                                    </span>
                                    <span class="block text-xs text-slate-500">{{ $p->specTexto($lote->product) }}</span>
                                    @if ($min !== null || $max !== null)
                                        <span class="block text-xs text-slate-400">
                                            {{ $min !== null ? number_format($min, 2, ',', '.') : '-' }}
                                            a
                                            {{ $max !== null ? number_format($max, 2, ',', '.') : '-' }}
                                        </span>
                                    @endif
                                </th>

                                <template x-for="n in rangoMuestras" :key="n">
                                    <td class="px-1 py-2 align-top">
                                        @if ($p->tipo === 'select')
                                            <select :name="'m[{{ $p->id }}][' + n + ']'"
                                                    class="celda-medicion w-16 rounded-md border-0 px-1 py-1.5 text-center text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-pc-500"
                                                    x-model="valores[{{ $p->id }}][n]"
                                                    :class="claseCelda({{ $p->id }}, valor({{ $p->id }}, n))">
                                                <option value=""></option>
                                                @foreach (($p->opciones['valores'] ?? []) as $opcion)
                                                    <option value="{{ $opcion }}">{{ $opcion }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input :name="'m[{{ $p->id }}][' + n + ']'"
                                                   type="{{ $p->tipo === 'numeric' ? 'number' : 'text' }}"
                                                   @if ($p->tipo === 'numeric') step="0.01" inputmode="decimal" @endif
                                                   class="celda-medicion w-20 rounded-md border-0 px-1.5 py-1.5 text-center text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-pc-500"
                                                   x-model="valores[{{ $p->id }}][n]"
                                                   :class="claseCelda({{ $p->id }}, valor({{ $p->id }}, n))">
                                        @endif
                                    </td>
                                </template>

                                <td class="px-3 py-2 text-center align-top">
                                    <span class="text-sm" :class="clasePromedio({{ $p->id }})"
                                          x-text="promedioTexto({{ $p->id }}) || '-'"></span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>
    @endif

    {{-- ===== Estado y observaciones ===== --}}
    <div class="tarjeta mt-5 p-4 sm:p-5">
        <h2 class="text-sm font-semibold text-slate-900">Estado de inspeccion</h2>

        <div class="mt-3 rounded-lg bg-slate-50 p-3">
            <p class="text-xs text-slate-500">El sistema sugiere, segun las mediciones cargadas:</p>
            <p class="mt-1">
                <span class="badge" :class="estadoSugerido.clase" x-text="estadoSugerido.texto"></span>
            </p>
            <p class="mt-2 text-xs text-slate-500">
                <span x-text="desvios"></span>
                <span x-text="desvios === 1 ? 'caracteristica fuera de especificacion' : 'caracteristicas fuera de especificacion'"></span>
            </p>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="estado" class="etiqueta">Estado definitivo</label>
                <select id="estado" name="estado" class="campo">
                    <option value="">Usar la sugerencia del sistema</option>
                    @foreach (\App\Models\Inspection::ESTADOS as $valor => $texto)
                        <option value="{{ $valor }}" @selected(old('estado', $inspeccion?->estado) === $valor)>
                            {{ $texto }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">
                    La decision final es del inspector. Podes sobreescribir la sugerencia.
                </p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="observacion" class="etiqueta">Observacion</label>
                <textarea id="observacion" name="observacion" rows="3" class="campo"
                          placeholder="Sale impresa en la boleta y en el certificado del cliente">{{ old('observacion', $inspeccion?->observacion) }}</textarea>
            </div>
            <div>
                <label for="observacion_interna" class="etiqueta">
                    Observacion interna
                    <span class="font-normal text-slate-400">(no sale en el certificado del cliente)</span>
                </label>
                <textarea id="observacion_interna" name="observacion_interna" rows="3" class="campo"
                          placeholder="Solo visible para personal interno">{{ old('observacion_interna', $inspeccion?->observacion_interna) }}</textarea>
            </div>
        </div>
    </div>
</div>
