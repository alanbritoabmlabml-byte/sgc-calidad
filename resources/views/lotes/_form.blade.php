{{--
    Formulario de lote, compartido por crear y editar.
    Variables: $lote (puede ser nuevo), $sector, $sectores, $productos, $maquinas, $lotesOrigen
--}}

<div class="grid gap-5 lg:grid-cols-3">

    {{-- Identificacion --}}
    <div class="tarjeta p-4 sm:p-5 lg:col-span-2">
        <h2 class="text-sm font-semibold text-slate-900">Identificacion del lote</h2>
        <p class="mt-1 text-xs text-slate-500">
            El codigo del lote lo genera el sistema. Los numeros de tarjeta y de lote de produccion
            son los que ya se escriben a mano en la boleta.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <div class="flex items-baseline justify-between gap-2">
                    <label for="sector_id" class="etiqueta">Sector *</label>
                    @if (auth()->user()->puede(\App\Support\Permisos::SECTORES_GESTIONAR))
                        <x-modal-alta-rapida tipo="sector" destino="sector_id" />
                    @endif
                </div>
                <select id="sector_id" name="sector_id" class="campo" required>
                    @foreach ($sectores as $s)
                        <option value="{{ $s->id }}" @selected(old('sector_id', $lote->sector_id ?? $sector->id) == $s->id)>
                            {{ $s->name }}
                        </option>
                    @endforeach
                </select>
                @if (! $lote->exists)
                    <p class="mt-1 text-xs text-slate-400">
                        El próximo código será {{ \App\Models\Lot::generarCodigo($sector) }}
                    </p>
                @endif
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-2">
                    <label for="product_id" class="etiqueta">Código de producto</label>
                    @if (auth()->user()->puede(\App\Support\Permisos::PRODUCTOS_CREAR))
                        <x-modal-alta-rapida tipo="producto" destino="product_id" sectorDestino="sector_id" />
                    @endif
                </div>
                <select id="product_id" name="product_id" class="campo">
                    <option value="">Sin definir</option>
                    @foreach ($productos as $p)
                        <option value="{{ $p->id }}" @selected(old('product_id', $lote->product_id) == $p->id)>
                            {{ $p->code }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">
                    Define las tolerancias del ensayo (gramaje, ancho, largo y peso nominales).
                </p>
            </div>

            <div>
                <label for="nro_tarjeta" class="etiqueta">N. de tarjeta / rollo</label>
                <input id="nro_tarjeta" name="nro_tarjeta" class="campo" inputmode="numeric"
                       value="{{ old('nro_tarjeta', $lote->nro_tarjeta) }}" placeholder="Ej. 7">
            </div>

            <div>
                <label for="nro_lote_produccion" class="etiqueta">N. de lote de produccion</label>
                <input id="nro_lote_produccion" name="nro_lote_produccion" class="campo"
                       value="{{ old('nro_lote_produccion', $lote->nro_lote_produccion) }}"
                       placeholder="Lote de planta o codigo SIMEC">
            </div>

            <div>
                <label for="machine_id" class="etiqueta">Maquina / telar</label>
                <select id="machine_id" name="machine_id" class="campo">
                    <option value="">Sin definir</option>
                    @foreach ($maquinas->groupBy('tipo') as $tipo => $grupo)
                        <optgroup label="{{ ucfirst($tipo) }}">
                            @foreach ($grupo as $m)
                                <option value="{{ $m->id }}" @selected(old('machine_id', $lote->machine_id) == $m->id)>
                                    {{ $m->code }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="peso_neto" class="etiqueta">Peso neto del rollo (kg)</label>
                <input id="peso_neto" name="peso_neto" type="number" step="0.001" min="0" class="campo"
                       value="{{ old('peso_neto', $lote->peso_neto) }}" placeholder="251">
            </div>
        </div>
    </div>

    {{-- Fecha, turno y trazabilidad --}}
    <div class="space-y-5">
        <div class="tarjeta p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-slate-900">Fecha y turno</h2>

            <div class="mt-4 space-y-4">
                {{-- La fecha y la hora de corte no pueden estar en el futuro. El
                     navegador ya lo limita con max; el servidor lo vuelve a validar. --}}
                <div>
                    <label for="fecha" class="etiqueta">Fecha de corte *</label>
                    <input id="fecha" name="fecha" type="date" class="campo" required
                           max="{{ now()->format('Y-m-d') }}"
                           value="{{ old('fecha', optional($lote->fecha)->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
                </div>
                <div>
                    <label for="hora" class="etiqueta">Hora</label>
                    <input id="hora" name="hora" type="time" class="campo"
                           value="{{ old('hora', $lote->hora ? substr($lote->hora, 0, 5) : '') }}">
                    <p class="mt-1 text-xs text-slate-400">No puede ser posterior a la hora actual.</p>
                </div>
                <div>
                    <label for="turno" class="etiqueta">Turno</label>
                    <select id="turno" name="turno" class="campo">
                        <option value="">Sin definir</option>
                        <option value="Dia" @selected(old('turno', $lote->turno) === 'Dia')>Dia</option>
                        <option value="Noche" @selected(old('turno', $lote->turno) === 'Noche')>Noche</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="tarjeta p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-slate-900">Trazabilidad</h2>
            <p class="mt-1 text-xs text-slate-500">
                Si este lote se produjo a partir de otro (por ejemplo, un lote de Corte y Costura
                que consume un rollo de Tejido), indicalo aca. El sistema exigira que el lote de
                origen tenga su inspeccion aprobada.
            </p>

            <div class="mt-4">
                <label for="source_lot_id" class="etiqueta">Lote de origen</label>
                <select id="source_lot_id" name="source_lot_id" class="campo">
                    <option value="">Ninguno</option>
                    @foreach ($lotesOrigen->reject(fn ($l) => $lote->exists && $l->id === $lote->id) as $l)
                        <option value="{{ $l->id }}" @selected(old('source_lot_id', $lote->source_lot_id) == $l->id)>
                            {{ $l->code }}
                            @if ($l->nro_tarjeta) &middot; tarjeta {{ $l->nro_tarjeta }} @endif
                            @if ($l->product) &middot; {{ $l->product->code }} @endif
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div class="tarjeta mt-5 p-4 sm:p-5">
    <label for="observacion" class="etiqueta">Observacion</label>
    <textarea id="observacion" name="observacion" rows="3" class="campo"
              placeholder="Notas del lote">{{ old('observacion', $lote->observacion) }}</textarea>
</div>
