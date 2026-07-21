@props(['attribute', 'name', 'label', 'placeholder', 'browse'])
{{-- tag="div": the slot holds action buttons, not a form control to label. --}}
<x-leap::label tag="div">
    @if ($attribute->options['multiple'] || !($this->data[$attribute->name] ?? false))
        {{-- Browsing and generating are two separate actions, not two halves of one
             control, so they get their own spacing rather than the tight pairing
             .leap-button-add gives adjacent siblings. --}}
        <span class="leap-button-add-group">
            <button class="leap-button-add" wire:click="$parent.fileBrowser('{{ $attribute->name }}', null, '{{ $attribute->sectionName }}')" type="button" aria-label="@lang($attribute->options['multiple'] ? 'leap::resource.add_files' : 'leap::resource.add_file')">@svg('fas-file-circle-plus', 'svg-icon')</button>
            @if ($this->aiImageEnabled())
                {{-- The dialog itself lives once at the bottom of the editor; this only opens it. --}}
                <button class="leap-button-add" type="button" aria-label="@lang('leap::resource.generate_image')"
                    x-on:click.prevent="$dispatch('leap-generate-image', { scope: 'editor', attribute: '{{ $attribute->name }}' })">@svg('fas-wand-magic-sparkles', 'svg-icon')</button>
            @endif
        </span>
    @endif
</x-leap::label>

@isset($this->data[$attribute->name])
    <ul @if ($attribute->options['multiple']) x-sort.ghost="$wire.sortData('{{ $attribute->name }}', $item, $position)" class="leap-files leap-files-media leap-files-sortable" @else class="leap-files leap-files-media" @endif>
        @foreach ($this->media($attribute->name) as $id => $media)
            <li x-sort:item="{{ $id }}">
                @if ($media->isImage())
                    <img src="{{ $media->downloadUrl }}" alt="">
                @elseif ($media->isVideo($media->file_name))
                    <video controls src="{{ $media->downloadUrl }}"></video>
                @elseif ($media->isAudio($media->file_name))
                    <audio controls src="{{ $media->downloadUrl }}"></audio>
                @else
                    {{ $media->file_name }}
                @endif
                <a href="{{ $media->downloadUrl }}" target="_blank" rel="noopener">
                    <span>@svg('fas-external-link-alt', 'svg-icon') {{ basename($media->file_name) }}</span>
                </a>
                <button wire:click="unselectMedia('{{ $attribute->name }}', {{ $id }})" aria-label="Delete {{ $id }}">@svg('fas-trash-alt', 'svg-icon')</button>
            </li>
        @endforeach
    </ul>
@endisset
