<button {{ $attributes }}>
@props(['label', 'svgIcon' => null, 'class' => ''])
    <x-leap::icon />@lang($label)
</button>
