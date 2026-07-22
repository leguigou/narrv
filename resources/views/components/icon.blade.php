@props([
    'name',
    'class' => 'h-4 w-4',
])

@php
    $paths = [
        'check' => '<path d="M20 6 9 17l-5-5" />',
        'chevron-down' => '<path d="m6 9 6 6 6-6" />',
        'copy' => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2" /><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" />',
        'external-link' => '<path d="M15 3h6v6" /><path d="M10 14 21 3" /><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />',
        'eye' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" />',
        'eye-off' => '<path d="m2 2 20 20" /><path d="M6.7 6.7C3.7 8.7 2 12 2 12s3 7 10 7c1.8 0 3.3-.4 4.6-1" /><path d="M9.9 4.2A9.8 9.8 0 0 1 12 4c7 0 10 8 10 8a15.3 15.3 0 0 1-3.2 4.3" /><path d="M14.1 14.1A3 3 0 0 1 9.9 9.9" />',
        'image' => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21" />',
        'refresh-cw' => '<path d="M21 12a9 9 0 0 1-15.5 6.2L3 16" /><path d="M3 21v-5h5" /><path d="M3 12A9 9 0 0 1 18.5 5.8L21 8" /><path d="M21 3v5h-5" />',
        'rotate-ccw' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8" /><path d="M3 3v5h5" />',
        'search' => '<circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" />',
        'trash' => '<path d="M3 6h18" /><path d="M8 6V4c0-1 .8-2 2-2h4c1.2 0 2 1 2 2v2" /><path d="M19 6 18 20c-.1 1.1-1 2-2 2H8c-1 0-1.9-.9-2-2L5 6" /><path d="M10 11v6" /><path d="M14 11v6" />',
        'upload' => '<path d="M12 3v12" /><path d="m17 8-5-5-5 5" /><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />',
        'x' => '<path d="M18 6 6 18" /><path d="m6 6 12 12" />',
    ];
@endphp

<svg {{ $attributes->merge(['class' => $class]) }}
     viewBox="0 0 24 24"
     fill="none"
     stroke="currentColor"
     stroke-width="2"
     stroke-linecap="round"
     stroke-linejoin="round"
     aria-hidden="true">
    {!! $paths[$name] ?? '' !!}
</svg>
