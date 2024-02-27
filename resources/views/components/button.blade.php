@props(['label', 'svgIcon'])
<button {{ $attributes }}>
    @isset($svgIcon)@svg($svgIcon, 'button-svg')@endif@lang($label)
</button>
