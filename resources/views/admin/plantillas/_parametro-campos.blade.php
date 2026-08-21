{{--
    Campos de un parametro de ensayo. Compartido por el alta y la edicion.
    Variables: $p (TestParameter, puede ser nuevo), $plantilla, $atributosProducto, $prefijo (id unico)
--}}

@php
    $opciones = $p->opciones ?? [];
@endphp

<div x-data="{ modo: '{{ old('spec_modo', $p->spec_modo ?? \App\Models\TestParameter::MODO_LIBRE) }}', tipo: '{{ old('tipo', $p->tipo ?? 'numeric') }}' }"
     class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">

    {{-- ----- Identificacion ----- --}}
    <div>
        <label for="{{ $prefijo }}_label" class="etiqueta">Nombre *</label>
        <input id="{{ $prefijo }}_label" name="label" class="campo" required
               value="{{ old('label', $p->label) }}" placeholder="Ej. Gramaje promedio">
    </div>

    <div>
        <label for="{{ $prefijo }}_code" class="etiqueta">Codigo *</label>
        <input id="{{ $prefijo }}_code" name="code" class="campo" required
               value="{{ old('code', $p->code) }}" placeholder="gramaje" pattern="[a-z0-9_]+">
        <p class="mt-1 text-xs text-slate-400">Solo minusculas, numeros y guion bajo.</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="{{ $prefijo }}_unit" class="etiqueta">Unidad</label>
            <input id="{{ $prefijo }}_unit" name="unit" class="campo"
                   value="{{ old('unit', $p->unit) }}" placeholder="g/m2">
        </div>
        <div>
            <label for="{{ $prefijo }}_grupo" class="etiqueta">Grupo</label>
            <input id="{{ $prefijo }}_grupo" name="grupo" class="campo"
                   value="{{ old('grupo', $p->grupo) }}" placeholder="T o U">
            <p class="mt-1 text-xs text-slate-400">Trama / urdimbre</p>
        </div>
    </div>

    <div>
        <label for="{{ $prefijo }}_tipo" class="etiqueta">Tipo de dato *</label>
        <select id="{{ $prefijo }}_tipo" name="tipo" class="campo" x-model="tipo" required>
            <option value="numeric">Numerico</option>
            <option value="text">Texto libre</option>
            <option value="select">Lista de valores</option>
        </select>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="{{ $prefijo }}_muestras" class="etiqueta">Muestras *</label>
            <input id="{{ $prefijo }}_muestras" name="muestras" type="number" min="1"
                   max="{{ $plantilla->muestras_max }}" class="campo" required
                   value="{{ old('muestras', $p->muestras ?? 1) }}">
            <p class="mt-1 text-xs text-slate-400">1 = valor unico</p>
        </div>
        <div>
            <label for="{{ $prefijo }}_orden" class="etiqueta">Orden</label>
            <input id="{{ $prefijo }}_orden" name="orden" type="number" min="1" class="campo"
                   value="{{ old('orden', $p->orden) }}">
        </div>
    </div>

    <div class="flex flex-col justify-end gap-2">
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="promediar" value="1" @checked(old('promediar', $p->promediar ?? true))
                   class="h-4 w-4 rounded border-slate-300 text-pc-600 focus:ring-pc-500">
            Calcular promedio
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="requerido" value="1" @checked(old('requerido', $p->requerido ?? false))
                   class="h-4 w-4 rounded border-slate-300 text-pc-600 focus:ring-pc-500">
            Critico
            <span class="text-xs text-slate-400">(fuera de spec. = RECHAZADO)</span>
        </label>
    </div>

    {{-- ----- Especificacion ----- --}}
    <div class="sm:col-span-2 lg:col-span-3">
        <div class="rounded-lg bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-800">Especificacion</p>
            <p class="mt-0.5 text-xs text-slate-500">
                Define cuando una medicion es conforme. Si no queres evaluacion automatica, elegi "Sin evaluacion".
            </p>

            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="{{ $prefijo }}_spec_modo" class="etiqueta">Modo *</label>
                    <select id="{{ $prefijo }}_spec_modo" name="spec_modo" class="campo" x-model="modo" required>
                        @foreach (\App\Models\TestParameter::MODOS as $valor => $texto)
                            <option value="{{ $valor }}">{{ $texto }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Minimo / maximo --}}
                <div x-show="['rango', 'minimo'].includes(modo)" x-cloak>
                    <label for="{{ $prefijo }}_spec_min" class="etiqueta">Minimo</label>
                    <input id="{{ $prefijo }}_spec_min" name="spec_min" type="number" step="0.001" class="campo"
                           value="{{ old('spec_min', $p->spec_min) }}">
                </div>

                <div x-show="['rango', 'maximo'].includes(modo)" x-cloak>
                    <label for="{{ $prefijo }}_spec_max" class="etiqueta">Maximo</label>
                    <input id="{{ $prefijo }}_spec_max" name="spec_max" type="number" step="0.001" class="campo"
                           value="{{ old('spec_max', $p->spec_max) }}">
                </div>

                {{-- Objetivo y tolerancias --}}
                <div x-show="['objetivo_tol', 'objetivo_pct'].includes(modo)" x-cloak>
                    <label for="{{ $prefijo }}_spec_objetivo" class="etiqueta">Objetivo</label>
                    <input id="{{ $prefijo }}_spec_objetivo" name="spec_objetivo" type="number" step="0.001" class="campo"
                           value="{{ old('spec_objetivo', $p->spec_objetivo) }}">
                    <p class="mt-1 text-xs text-slate-400">Se usa si el producto no aporta el valor.</p>
                </div>

                <div x-show="modo === 'objetivo_tol'" x-cloak>
                    <label for="{{ $prefijo }}_spec_tolerancia" class="etiqueta">Tolerancia (+/-)</label>
                    <input id="{{ $prefijo }}_spec_tolerancia" name="spec_tolerancia" type="number" step="0.001" min="0"
                           class="campo" value="{{ old('spec_tolerancia', $p->spec_tolerancia) }}" placeholder="2">
                </div>

                <div x-show="modo === 'objetivo_pct'" x-cloak>
                    <label for="{{ $prefijo }}_spec_tolerancia_pct" class="etiqueta">Tolerancia (%)</label>
                    <input id="{{ $prefijo }}_spec_tolerancia_pct" name="spec_tolerancia_pct" type="number" step="0.01"
                           min="0" max="100" class="campo"
                           value="{{ old('spec_tolerancia_pct', $p->spec_tolerancia_pct) }}" placeholder="3.5">
                </div>

                {{-- Objetivo tomado del producto --}}
                <div x-show="['objetivo_tol', 'objetivo_pct'].includes(modo)" x-cloak class="sm:col-span-2">
                    <label for="{{ $prefijo }}_spec_desde_producto" class="etiqueta">
                        Tomar el objetivo del producto
                    </label>
                    <select id="{{ $prefijo }}_spec_desde_producto" name="spec_desde_producto" class="campo">
                        @foreach ($atributosProducto as $valor => $texto)
                            <option value="{{ $valor }}"
                                @selected(old('spec_desde_producto', $p->spec_desde_producto) === ($valor ?: null))>
                                {{ $texto }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-400">
                        Asi el objetivo lo define el codigo de producto del lote. Es lo que hace que
                        "Bl 65x104/66" se evalue contra gramaje 66 y "V.Cl 56x96/64" contra 64.
                    </p>
                </div>

                {{-- Lista de valores --}}
                <div x-show="tipo === 'select' || modo === 'opciones'" x-cloak>
                    <label for="{{ $prefijo }}_valores" class="etiqueta">Valores posibles</label>
                    <input id="{{ $prefijo }}_valores" name="valores" class="campo"
                           value="{{ old('valores', implode(', ', $opciones['valores'] ?? [])) }}"
                           placeholder="B, M">
                    <p class="mt-1 text-xs text-slate-400">Separados por coma.</p>
                </div>

                <div x-show="tipo === 'select' || modo === 'opciones'" x-cloak>
                    <label for="{{ $prefijo }}_conformes" class="etiqueta">Valores conformes</label>
                    <input id="{{ $prefijo }}_conformes" name="conformes" class="campo"
                           value="{{ old('conformes', implode(', ', $opciones['conformes'] ?? [])) }}"
                           placeholder="B">
                </div>

                {{-- Etiqueta libre --}}
                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="{{ $prefijo }}_spec_label" class="etiqueta">
                        Texto de la especificacion en la boleta
                    </label>
                    <input id="{{ $prefijo }}_spec_label" name="spec_label" class="campo"
                           value="{{ old('spec_label', $p->spec_label) }}" placeholder="Ej. +/- 2, minimo 60, 23 +/- 5">
                    <p class="mt-1 text-xs text-slate-400">
                        Opcional. Sirve para que la boleta impresa diga exactamente lo mismo que la
                        planilla en Excel. Si lo dejas vacio, el sistema lo arma solo.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
