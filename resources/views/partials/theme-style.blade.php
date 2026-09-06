@php
    $__platform ??= \App\Models\PlatformSetting::current();
@endphp
@if ($__platform->hasCustomTheme())
    <style>
        :root {
            @if ($__platform->theme_primary_color)
                @php($__shades = \App\Support\ColorTheme::shades($__platform->theme_primary_color))
                --color-primary-50: {{ $__shades['50'] }};
                --color-primary-100: {{ $__shades['100'] }};
                --color-primary-200: {{ $__shades['200'] }};
                --color-primary-500: {{ $__shades['500'] }};
                --color-primary-600: {{ $__shades['600'] }};
                --color-primary-700: {{ $__shades['700'] }};
            @endif
            @if ($__platform->fontStack())
                {{-- Raw output: fontStack() only ever returns one of the
                     fixed strings in PlatformSetting::FONTS (a controlled
                     list, never arbitrary/user-supplied CSS), and {{ }}'s
                     HTML-entity escaping of the quotes would otherwise be
                     taken as literal text inside <style>'s raw-text content
                     model instead of being decoded back to a quote. --}}
                --font-sans: {!! $__platform->fontStack() !!};
            @endif
        }
    </style>
@endif
