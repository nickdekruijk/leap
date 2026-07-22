@props(['show', 'close' => null, 'title' => null, 'teleport' => false])

{{--
    Reusable centred modal overlay.

    - show:     Alpine expression controlling visibility (e.g. "settingAlt" or "$wire.editingFile").
    - close:    Alpine statement run on backdrop click / escape (e.g. "settingAlt = false").
    - title:    optional header text.
    - teleport: render at the admin root instead of in place. Needed inside the editor
                panel: it slides in with a transform, and a transformed element becomes
                the containing block for everything fixed inside it — so the overlay
                would cover only the panel and scroll along with it instead of staying
                put over the window. Moved to .leap rather than the body because the
                font is set there; outside it the dialog falls back to the browser
                default. (The colours survive either way, being :root variables.)

    Extra attributes (x-data, x-effect, …) are merged onto the overlay, so a
    caller can attach its own behaviour. Inner markup uses the shared
    .leap-modal-field / .leap-modal-actions / .leap-modal-btn classes.
--}}
@if ($teleport)
    <template x-teleport=".leap">
@endif
<div {{ $attributes->merge(['class' => 'leap-modal']) }} x-show="{{ $show }}" style="display: none" x-transition.opacity
    @if ($close) x-on:keydown.escape.window="{{ $close }}" x-on:click="{{ $close }}" @endif>
    <div class="leap-modal-dialog" x-on:click.stop>
        @if ($title)
            <div class="leap-modal-header">{{ $title }}</div>
        @endif
        {{ $slot }}
    </div>
</div>
@if ($teleport)
    </template>
@endif
