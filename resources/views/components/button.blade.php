@props(['label', 'svgIcon'])
<button {{ $attributes }}">
    @isset($svgIcon)@svg($svgIcon, 'button-svg')@endif{{ $label }}
</button>
