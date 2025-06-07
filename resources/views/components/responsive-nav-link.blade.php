@props(['active'])

@php
$classes = ($active ?? false)
            ? 'bg-indigo-50 border-indigo-500 text-indigo-700 block pr-3 py-2 border-r-4 text-base font-medium'
            : 'border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700 block pr-3 py-2 border-r-4 text-base font-medium';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a> 