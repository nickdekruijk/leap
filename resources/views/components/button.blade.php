@props(['label', 'svgIcon' => null, 'class' => '', 'href' => null])
@if ($href)
    <a class="leap-button {{ $class }}" href="{{ $href }}" wire:navigate>
        <x-leap::icon />@lang($label)
    </a>
@else
    <button class="leap-button {{ $class }}" {{ $attributes }}>
        <x-leap::icon />@lang($label)
    </button>
@endif
