@props(['activo' => false])

<a {{ $attributes->merge([
    'class' => $activo
        ? 'block rounded-lg bg-pc-800 px-4 py-3 text-base font-semibold text-white'
        : 'block rounded-lg px-4 py-3 text-base font-medium text-pc-100 hover:bg-pc-600 hover:text-white',
]) }}>
    {{ $slot }}
</a>
