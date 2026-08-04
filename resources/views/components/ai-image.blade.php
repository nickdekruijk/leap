@props(['scope' => 'editor'])

{{--
    The AI image generation dialog, shared by the editor and the file manager.

    Rendered once per component and opened by a window event, so a page full of media
    fields does not carry a modal each and the surrounding form layout stays untouched.
    The event carries the scope so the editor's dialog and the file manager's (both on
    screen while browsing) do not both answer the same click.

    scope: 'editor'      — the media attribute is passed along and the result is attached to it.
           'filemanager' — a free-form prompt, stored in the folder currently open.
--}}

{{-- Shape, not an exact ratio: the model keeps its own framing, and these three are
     what the providers actually offer as a canvas. --}}
@php($orientations = ['landscape', 'portrait', 'square'])

{{-- The presets from leap.ai.image.presets, each with the price of picking it. One
     preset means there is nothing to choose, so the picker is left out entirely. --}}
@php($presets = $this->aiImagePresets())

<div x-data="{
    open: false,
    attribute: null,
    prompt: '',
    orientation: @js(reset($orientations)),
    preset: @js(array_key_first($presets)),
    estimates: @js(array_map(fn ($preset) => $preset['estimate'], $presets)),
    busy: false,
    preview: null,
    token: null,
    cost: null,
    zoomed: false,
    start(attribute) {
        this.attribute = attribute ?? null;
        this.prompt = '';
        this.preview = null;
        this.token = null;
        this.cost = null;
        this.busy = false;
        this.zoomed = false;
        this.open = true;
        // Suggest a prompt from what the editor is looking at right now, rather than
        // from what the page held when it was rendered.
        if (attribute) {
            $wire.imagePrompt(attribute).then(prompt => { if (!this.prompt) this.prompt = prompt; });
        }
    },
    generate() {
        if (this.busy || !this.prompt.trim()) return;
        this.busy = true;
        this.preview = null;
        this.token = null;
        this.cost = null;
        $wire.generateImage(this.prompt, this.orientation, this.preset).then(result => {
            if (result && result.token) {
                this.token = result.token;
                this.preview = result.preview;
                this.cost = result.cost;
            }
        }).finally(() => this.busy = false);
    },
    accept() {
        if (this.busy || !this.token) return;
        this.busy = true;
        const done = () => { this.busy = false; this.open = false; };
        @if ($scope === 'editor')
            $wire.useGeneratedImage(this.attribute, this.token).then(done, done);
        @else
            $wire.useGeneratedImage(this.token).then(done, done);
        @endif
    },
}" x-on:leap-generate-image.window="if ($event.detail?.scope === @js($scope)) start($event.detail?.attribute)">
    {{-- teleport: in the editor this sits inside the sliding, scrolling panel. --}}
    <x-leap::modal show="open" close="open = false" teleport title="{{ __('leap::resource.generate_image') }}">
        <div class="leap-modal-field">
            <textarea rows="4" x-model="prompt" placeholder="@lang('leap::resource.image_prompt_placeholder')"></textarea>
        </div>
        @if (count($presets) > 1)
            {{-- Above the shape: it decides the model, and with it the price. --}}
            <div class="leap-modal-field">
                <label>@lang('leap::resource.image_quality')</label>
                <select class="leap-select" x-model="preset" :disabled="busy">
                    @foreach ($presets as $key => $preset)
                        <option value="{{ $key }}">{{ $preset['label'] }}@if ($preset['estimate']) &nbsp;&asymp; ${{ number_format($preset['estimate'], 3) }}@endif</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="leap-modal-field">
            <label>@lang('leap::resource.image_orientation')</label>
            <select class="leap-select" x-model="orientation" :disabled="busy">
                @foreach ($orientations as $orientation)
                    <option value="{{ $orientation }}">@lang('leap::resource.image_orientation_'.$orientation)</option>
                @endforeach
            </select>
        </div>
        {{-- Generating takes tens of seconds, so the wait needs to look like one. --}}
        <div class="leap-ai-image-busy" x-show="busy" x-cloak>
            <span class="leap-ai-image-spinner"></span>
            @lang('leap::resource.image_generating')
        </div>
        <div class="leap-ai-image-preview" x-show="preview && !busy" x-cloak>
            {{-- The dialog is narrow; the result deserves a proper look before it is
                 accepted, so the preview opens full screen on click. --}}
            <img x-bind:src="preview" alt="" role="button" tabindex="0"
                :title="@js(__('leap::resource.image_enlarge'))"
                x-on:click="zoomed = true" x-on:keydown.enter.prevent="zoomed = true">
            <span class="leap-ai-image-cost" x-show="cost !== null" x-text="@js(__('leap::resource.image_cost')) + ' $' + Number(cost).toFixed(3)"></span>
        </div>
        {{-- Same shape as the translate dialog: the action in primary style with its
             icon, then a plain cancel. Which action is primary shifts once there is a
             result to accept. No spinning icon: the placeholder above shows the wait. --}}
        <div class="leap-modal-actions">
            <button type="button" class="leap-modal-btn" :class="{ 'leap-modal-save': !token }" :disabled="busy || !prompt.trim()" x-on:click="generate()">
                @svg('fas-wand-magic-sparkles', 'svg-icon')
                <span x-text="token ? @js(__('leap::resource.image_regenerate')) : @js(__('leap::resource.image_generate'))"></span>
                {{-- The estimate follows the selected preset, so the button never quotes
                     the price of a model that is no longer the one being asked. --}}
                <span class="leap-ai-image-estimate" x-show="estimates[preset]" x-cloak
                    x-text="'≈ $' + Number(estimates[preset]).toFixed(3)"></span>
            </button>
            <button type="button" class="leap-modal-btn leap-modal-save" x-show="token" x-cloak :disabled="busy" x-on:click="accept()">
                @svg('fas-check', 'svg-icon') @lang('leap::resource.image_use')
            </button>
            <button type="button" class="leap-modal-btn" x-on:click="open = false">@lang('leap::resource.cancel')</button>
        </div>
    </x-leap::modal>
    {{-- Teleported to the admin root, not merely placed outside the dialog. The editor
         panel slides in with a transform, and a transformed element becomes the
         containing block for everything fixed inside it — so an overlay left here would
         be bounded by the editor instead of the viewport. It goes to .leap rather than
         the body to stay inside the styling scope. Escape is caught in the capture phase
         so it closes the enlargement first instead of the dialog underneath it. --}}
    <template x-teleport=".leap">
        <div class="leap-ai-image-zoom" x-show="zoomed" x-cloak x-transition.opacity
            x-on:click="zoomed = false"
            x-on:keydown.escape.window.capture="if (zoomed) { zoomed = false; $event.stopPropagation(); }">
            <img x-bind:src="preview" alt="">
        </div>
    </template>
</div>
