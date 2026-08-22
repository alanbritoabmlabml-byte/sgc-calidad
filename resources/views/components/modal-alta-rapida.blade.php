@props([
    // 'producto' | 'sector'
    'tipo' => 'producto',
    // id del <select> al que se agrega la opcion nueva
    'destino',
    // id del <select> de sector, para saber a que sector pertenece el producto
    'sectorDestino' => null,
])

@php
    $esProducto = $tipo === 'producto';
    $ruta = $esProducto ? route('admin.productos.rapido') : route('admin.sectores.rapido');
    $id = 'alta-'.$tipo;
@endphp

{{--
    Alta rapida sin salir del formulario de lote.

    Envia por fetch, agrega la opcion al desplegable y la deja seleccionada.
    Asi cargar un producto nuevo no obliga a abandonar el lote a medio llenar.
--}}
<div x-data="{
        abierto: false,
        enviando: false,
        errores: {},
        aviso: null,
        campos: {},

        abrir() {
            this.abierto = true;
            this.errores = {};
            this.aviso = null;
            this.campos = {};
            $nextTick(() => this.$refs.primero?.focus());
        },

        cerrar() { this.abierto = false; },

        async guardar() {
            this.enviando = true;
            this.errores = {};

            const cuerpo = { ...this.campos };

            @if ($esProducto)
                cuerpo.sector_id = document.getElementById('{{ $sectorDestino }}')?.value;
            @endif

            try {
                const r = await fetch('{{ $ruta }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify(cuerpo),
                });

                const datos = await r.json();

                if (r.status === 422) {
                    this.errores = datos.errors ?? {};
                    return;
                }

                if (! r.ok) {
                    this.errores = { general: [datos.message ?? 'No se pudo guardar.'] };
                    return;
                }

                // Se agrega la opcion nueva al desplegable y se deja elegida.
                const select = document.getElementById('{{ $destino }}');
                const nuevo = datos.{{ $esProducto ? 'producto' : 'sector' }};
                const opcion = new Option(nuevo.{{ $esProducto ? 'code' : 'name' }}, nuevo.id, true, true);
                select.add(opcion);
                select.dispatchEvent(new Event('change', { bubbles: true }));

                this.aviso = datos.mensaje;
                this.abierto = false;
            } catch (e) {
                this.errores = { general: ['Error de red. Revisá la conexión e intentá de nuevo.'] };
            } finally {
                this.enviando = false;
            }
        },
     }"
     class="inline">

    {{-- Boton chico al lado del desplegable --}}
    <button type="button" @click="abrir()"
            class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-semibold text-pc-700 ring-1 ring-inset ring-pc-200 hover:bg-pc-50"
            title="{{ $esProducto ? 'Crear un producto nuevo sin salir de esta pantalla' : 'Crear un sector nuevo sin salir de esta pantalla' }}">
        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        Nuevo
    </button>

    {{-- Aviso tras crear --}}
    <template x-if="aviso">
        <p class="mt-1 rounded bg-emerald-50 p-2 text-xs text-emerald-800" x-text="aviso"></p>
    </template>

    {{-- Panel --}}
    <div x-show="abierto" x-cloak
         class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center"
         @keydown.escape.window="cerrar()">

        <div @click.outside="cerrar()"
             class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">

            <h3 class="text-base font-semibold text-slate-900">
                {{ $esProducto ? 'Nuevo producto' : 'Nuevo sector' }}
            </h3>
            <p class="mt-1 text-xs text-slate-500">
                @if ($esProducto)
                    Se crea en el sector elegido arriba. Si el código tiene el formato
                    <span class="font-mono">Bl 65x104/66</span>, el sistema deduce el ancho,
                    el largo y el gramaje.
                @else
                    Después habrá que cargarle procesos y plantillas de ensayo desde Configuración.
                @endif
            </p>

            <template x-if="errores.general">
                <p class="mt-3 rounded bg-rojo-50 p-2 text-xs text-rojo-800" x-text="errores.general[0]"></p>
            </template>

            <div class="mt-4 space-y-3">
                @if ($esProducto)
                    <div>
                        <label class="etiqueta" for="{{ $id }}-code">Código *</label>
                        <input id="{{ $id }}-code" x-ref="primero" x-model="campos.code" class="campo"
                               placeholder="Bl 65x104/66">
                        <template x-if="errores.code">
                            <p class="mt-1 text-xs text-rojo-700" x-text="errores.code[0]"></p>
                        </template>
                    </div>

                    <div>
                        <label class="etiqueta" for="{{ $id }}-name">Nombre comercial</label>
                        <input id="{{ $id }}-name" x-model="campos.name" class="campo"
                               placeholder="Saco Blanco 65x104">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="etiqueta" for="{{ $id }}-gramaje">Gramaje (g/m²)</label>
                            <input id="{{ $id }}-gramaje" x-model="campos.gramaje_nominal" type="number"
                                   step="0.01" class="campo" placeholder="se deduce">
                        </div>
                        <div>
                            <label class="etiqueta" for="{{ $id }}-peso">Peso bolsa (g)</label>
                            <input id="{{ $id }}-peso" x-model="campos.peso_nominal" type="number"
                                   step="0.01" class="campo">
                        </div>
                    </div>

                    <p class="rounded bg-amber-50 p-2 text-xs leading-relaxed text-amber-800">
                        El <strong>peso de la bolsa</strong> no se puede deducir del código. Si lo dejás
                        vacío, el ensayo de Corte y Costura no va a tener contra qué evaluar el peso.
                        Se puede completar después en Configuración → Productos.
                    </p>
                @else
                    <div>
                        <label class="etiqueta" for="{{ $id }}-name">Nombre *</label>
                        <input id="{{ $id }}-name" x-ref="primero" x-model="campos.name" class="campo"
                               placeholder="Ej. Expandido">
                        <template x-if="errores.name">
                            <p class="mt-1 text-xs text-rojo-700" x-text="errores.name[0]"></p>
                        </template>
                    </div>

                    <div>
                        <label class="etiqueta" for="{{ $id }}-prefijo">Prefijo de lote</label>
                        <input id="{{ $id }}-prefijo" x-model="campos.prefijo_lote" class="campo font-mono uppercase"
                               maxlength="8" placeholder="se deduce del nombre">
                        <p class="mt-1 text-xs text-slate-400">Los lotes se numeran PREFIJO-AA-00001.</p>
                    </div>
                @endif
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="cerrar()" class="btn-secundario">Cancelar</button>
                <button type="button" @click="guardar()" class="btn-primario" :disabled="enviando">
                    <span x-show="! enviando">Crear</span>
                    <span x-show="enviando" x-cloak>Guardando...</span>
                </button>
            </div>
        </div>
    </div>
</div>
