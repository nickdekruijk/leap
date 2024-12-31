@props(['attribute', 'name', 'label', 'placeholder', 'browse'])
<x-leap::label>
    @if ($attribute->options['multiple'] || !$this->data[$attribute->name])
        <button class="leap-button-add" wire:click="$parent.fileBrowser('{{ $attribute->name }}')" type="button" aria-label="@lang($attribute->options['multiple'] ? 'leap::resource.add_files' : 'leap::resource.add_file')">@svg('fas-file-circle-plus', 'svg-icon')</button>
    @endif
</x-leap::label>

@if ($this->data[$attribute->name])
    <ul @if ($attribute->options['multiple']) x-sort.ghost="$wire.sortData('{{ $attribute->name }}', $item, $position)" class="leap-files leap-files-sortable" @else class="leap-files" @endif>
        @foreach (explode(PHP_EOL, $this->data[$attribute->name]) as $id => $file)
            <li x-sort:item="{{ $id }}">
                {{ $file }}
                <a href="{{ (new \NickDeKruijk\Leap\Livewire\FileManager())->downloadUrl($file) }}" target="_blank" rel="noopener" aria-label="Open {{ $file }}">@svg('fas-external-link-alt', 'svg-icon')</a>
                <button wire:click="unselectFile('{{ $attribute->name }}', {{ $id }})" aria-label="Delete {{ $file }}">@svg('fas-trash', 'svg-icon')</button>
            </li>
        @endforeach
    </ul>
@endif
