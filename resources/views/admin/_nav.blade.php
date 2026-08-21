{{-- Sub-navegacion de la seccion Configuracion. --}}
<div class="mb-5 flex flex-wrap gap-2 border-b border-slate-200 pb-3">
    @php
        $secciones = [
            'admin.plantillas.index' => 'Plantillas de ensayo',
            'admin.productos.index' => 'Productos',
            'admin.maquinas.index' => 'Maquinas',
            'admin.usuarios.index' => 'Usuarios',
        ];
    @endphp

    @foreach ($secciones as $ruta => $texto)
        @php $activo = request()->routeIs(Str::beforeLast($ruta, '.index').'.*'); @endphp
        <a href="{{ route($ruta) }}"
           class="{{ $activo
                ? 'rounded-lg bg-pc-700 px-3 py-2 text-sm font-semibold text-white'
                : 'rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100' }}">
            {{ $texto }}
        </a>
    @endforeach
</div>
