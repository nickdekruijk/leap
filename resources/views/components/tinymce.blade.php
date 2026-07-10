@props(['attribute', 'placeholder', 'name'])

@php
    // Section rich-text is lazy (click to edit) by default; standalone top-level
    // rich-text keeps the immediate editor by default. Both are configurable.
    $lazy = $attribute->sectionName
        ? config('leap.tinymce.lazy_sections', true)
        : config('leap.tinymce.lazy', false);

    // A stable, unique id per field so re-initializing (a second section, or
    // reopening after save) never collides with a stale editor.
    $fieldId = 'leap-rt-'.str_replace('.', '-', $attribute->dataName);

    // Cache-bust a local content_css stylesheet with its filemtime, the same way
    // Leap busts its own compiled admin CSS, so editors pick up style changes.
    $options = $attribute->options;
    if (isset($options['content_css']) && is_string($options['content_css'])) {
        $options['content_css'] = \NickDeKruijk\Leap\Controllers\AssetController::cacheBust($options['content_css']);
    }
@endphp

<x-leap::label>
    <div
        wire:key="{{ $attribute->dataName . $this->randomSortSeed }}"
        wire:ignore
        x-data="{
            value: $wire.entangle('{{ $attribute->dataName }}'),
            lazy: {{ $lazy ? 'true' : 'false' }},
            editing: false,
            editor: null,
            init() {
                if (!this.lazy) {
                    this.activate();
                    return;
                }
                // Lazy fields drop back to the rendered-HTML preview after a save
                Livewire.on('leap-editor-saved', () => {
                    if (this.lazy) this.deactivate();
                });
            },
            // Load TinyMCE on demand. The admin is a wire:navigate SPA, so relying
            // on Livewire's asset directive is unreliable when a field is first
            // edited after a Livewire update/navigation (window.tinymce can be
            // missing). One shared promise dedupes the script across all fields.
            ensureTinymce() {
                if (window.tinymce) {
                    return Promise.resolve();
                }
                if (!window.leapTinymceLoader) {
                    window.leapTinymceLoader = new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = '{{ config('leap.tinymce.cdn') }}';
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }
                return window.leapTinymceLoader;
            },
            activate() {
                if (this.editor) {
                    this.editing = true;
                    return;
                }
                this.editing = true;
                this.ensureTinymce().then(() => this.$nextTick(() => {
                    // Wait for layout/paint so the (now visible) textarea has a real
                    // height before TinyMCE's autoresize measures it — otherwise a
                    // field activated while TinyMCE is already loaded inits at height 0.
                    requestAnimationFrame(() => {
                        // Drop any stale editor bound to this id before re-initializing
                        if (tinymce.get(this.$refs.textarea.id)) {
                            tinymce.remove('#' + this.$refs.textarea.id);
                        }
                        tinymce.init(Object.assign({{ json_encode($options) }}, {
                            target: this.$refs.textarea,
                            file_picker_callback: (callback, value, meta) => {
                                this.$wire.$parent.tinymceBrowser();
                                Livewire.on('tinymceBrowser', (e) => {
                                    callback(e[0]);
                                });
                            },
                            setup: (editor) => {
                                this.editor = editor;
                                editor.on('blur', (e) => {
                                    this.value = editor.getContent();
                                });
                                editor.on('init', (e) => {
                                    if (this.value != null) {
                                        editor.setContent(this.value);
                                    }
                                });
                                this.$watch('value', (newValue) => {
                                    if (newValue !== editor.getContent()) {
                                        editor.resetContent(newValue || '');
                                        // Put cursor at the end
                                        editor.selection.select(editor.getBody(), true);
                                        editor.selection.collapse(false);
                                    }
                                });
                            },
                        }));
                    });
                }));
            },
            deactivate() {
                if (this.editor) {
                    this.value = this.editor.getContent();
                    tinymce.remove(this.editor);
                    this.editor = null;
                }
                this.editing = false;
            },
        }">
        <div
            x-show="!editing"
            x-html="value"
            @click="activate()"
            @keydown.enter.prevent="activate()"
            @keydown.space.prevent="activate()"
            class="leap-richtext-preview tinymce"
            data-empty-hint="{{ __('leap::resource.click_to_edit') }}"
            role="button"
            tabindex="0"></div>
        <textarea
            x-ref="textarea"
            x-show="editing"
            id="{{ $fieldId }}"
            class="leap-textarea"
            @error($attribute->dataName ?? $name)
                aria-errormessage="{{ $message }}"
                aria-invalid="true"
            @enderror
            placeholder="{{ $placeholder[$attribute->name] ?? $attribute->placeholder }}"
            @if ($attribute->disabled ?? false) disabled @endif
            {{ $attribute->inputAttributes() }}
            {{ $attributes }}></textarea>
    </div>
</x-leap::label>
