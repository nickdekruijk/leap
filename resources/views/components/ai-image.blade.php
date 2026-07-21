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

{{-- The same list as config/leap.php, so an install whose published config predates
     this feature still offers every ratio instead of just the first. --}}
@php($aspects = config('leap.ai.image.aspect_ratios') ?: ['16:9', '4:3', '1:1', '3:4'])
@php($estimate = $this->aiImageEstimate())

<div x-data="{
    open: false,
    attribute: null,
    prompt: '',
    aspect: @js(reset($aspects)),
    busy: false,
    preview: null,
    token: null,
    cost: null,
    start(attribute) {
        this.attribute = attribute ?? null;
        this.prompt = '';
        this.preview = null;
        this.token = null;
        this.cost = null;
        this.busy = false;
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
        $wire.generateImage(this.prompt, this.aspect).then(result => {
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
    <x-leap::modal show="open" close="open = false" title="{{ __('leap::resource.generate_image') }}">
        <div class="leap-modal-field">
            <textarea rows="4" x-model="prompt" placeholder="@lang('leap::resource.image_prompt_placeholder')"></textarea>
        </div>
        <div class="leap-modal-field">
            <label>@lang('leap::resource.image_aspect')</label>
            <select class="leap-select" x-model="aspect" :disabled="busy">
                @foreach ($aspects as $ratio)
                    <option value="{{ $ratio }}">{{ $ratio }}</option>
                @endforeach
            </select>
        </div>
        {{-- Generating takes tens of seconds, so the wait needs to look like one. --}}
        <div class="leap-ai-image-busy" x-show="busy" x-cloak>
            <span class="leap-ai-image-spinner"></span>
            @lang('leap::resource.image_generating')
        </div>
        <div class="leap-ai-image-preview" x-show="preview && !busy" x-cloak>
            <img x-bind:src="preview" alt="">
            <span class="leap-ai-image-cost" x-show="cost !== null" x-text="@js(__('leap::resource.image_cost')) + ' $' + Number(cost).toFixed(3)"></span>
        </div>
        <div class="leap-modal-actions">
            <button type="button" class="leap-modal-btn leap-alt-generate-btn" :class="{ 'leap-alt-generating': busy }" :disabled="busy || !prompt.trim()" x-on:click="generate()">
                @svg('fas-wand-magic-sparkles', 'svg-icon')
                <span x-text="token ? @js(__('leap::resource.image_regenerate')) : @js(__('leap::resource.image_generate'))"></span>
                @if ($estimate)
                    <span class="leap-ai-image-estimate">&asymp; ${{ number_format($estimate, 3) }}</span>
                @endif
            </button>
            <button type="button" class="leap-modal-btn leap-modal-save" x-show="token" x-cloak :disabled="busy" x-on:click="accept()">@lang('leap::resource.image_use')</button>
            <button type="button" class="leap-modal-btn" x-on:click="open = false">@lang('leap::resource.cancel')</button>
        </div>
    </x-leap::modal>
</div>
