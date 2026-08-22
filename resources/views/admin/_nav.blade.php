{{-- Sub-navegacion de Configuracion. Solo muestra lo que el usuario puede ver. --}}
@php
    use App\Support\Permisos;

    $yo = auth()->user();

    $secciones = collect([
        ['plantillas', 'Plantillas de ensayo', Permisos::PLANTILLAS_VER, route('admin.plantillas.index')],
        ['productos', 'Productos', Permisos::CATALOGOS_VER, route('admin.productos.index')],
        ['maquinas', 'Máquinas', Permisos::CATALOGOS_VER, route('admin.maquinas.index')],
        ['sectores', 'Sectores', Permisos::SECTORES_GESTIONAR, route('admin.sectores.index')],
        ['usuarios', 'Usuarios', Permisos::USUARIOS_GESTIONAR, route('admin.usuarios.index')],
    ])->filter(fn (array $s) => $yo->puede($s[2]));
@endphp

<div class="mb-5 flex flex-wrap gap-2 border-b border-slate-200 pb-3">
    @foreach ($secciones as [$slug, $texto, $permiso, $url])
        @php $activo = request()->routeIs("admin.{$slug}.*"); @endphp
        <a href="{{ $url }}"
           class="{{ $activo
                ? 'rounded-lg bg-pc-700 px-3 py-2 text-sm font-semibold text-white'
                : 'rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100' }}">
            {{ $texto }}
        </a>
    @endforeach
</div>
