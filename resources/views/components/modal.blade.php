@props(['show', 'close' => null, 'title' => null])

{{--
    Reusable centred modal overlay.

    - show:  Alpine expression controlling visibility (e.g. "settingAlt" or "$wire.editingFile").
    - close: Alpine statement run on backdrop click / escape (e.g. "settingAlt = false").
    - title: optional header text.

    Extra attributes (x-data, x-effect, …) are merged onto the overlay, so a
    caller can attach its own behaviour. Inner markup uses the shared
    .leap-modal-field / .leap-modal-actions / .leap-modal-btn classes.
--}}
<div {{ $attributes->merge(['class' => 'leap-modal']) }} x-show="{{ $show }}" style="display: none" x-transition.opacity
    @if ($close) x-on:keydown.escape.window="{{ $close }}" x-on:click="{{ $close }}" @endif>
    <div class="leap-modal-dialog" x-on:click.stop>
        @if ($title)
            <div class="leap-modal-header">{{ $title }}</div>
        @endif
        {{ $slot }}
    </div>
</div>
