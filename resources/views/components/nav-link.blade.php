@props(['activo' => false])

<a {{ $attributes->merge([
    'class' => $activo
        ? 'rounded-lg bg-pc-800 px-3 py-2 text-sm font-semibold text-white'
        : 'rounded-lg px-3 py-2 text-sm font-medium text-pc-100 hover:bg-pc-600 hover:text-white',
]) }}>
    {{ $slot }}
</a>
