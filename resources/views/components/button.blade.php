{{-- navigate: wire:navigate keeps panel links inside the SPA. Set it false for a link
     that leaves the panel (the frontend preview, an external file), because Livewire
     would otherwise swap the page into the panel it was opened from. --}}
@props(['label', 'svgIcon' => null, 'class' => '', 'href' => null, 'navigate' => true])
@if ($href)
    <a class="leap-button {{ $class }}" href="{{ $href }}" @if ($navigate) wire:navigate @endif {{ $attributes }}>
        <x-leap::icon />@lang($label)
    </a>
@else
    <button class="leap-button {{ $class }}" {{ $attributes }}>
        <x-leap::icon />@lang($label)
    </button>
@endif
