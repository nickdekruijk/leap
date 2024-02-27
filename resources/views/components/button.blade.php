@props(['label', 'svgIcon' => null, 'class' => ''])
<button class="leap-button {{ $class }}" {{ $attributes }}>
    <x-leap::icon />@lang($label)
</button>
