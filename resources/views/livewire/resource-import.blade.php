<div>
    <div class="leap-buttons" role="group" x-on:keydown.escape.window="$wire.closeImport">
        <x-leap::button svg-icon="fas-file-import" wire:click="import(false)" label="Test" />
        <x-leap::button svg-icon="fas-file-import" wire:click="import" label="leap::resource.importRows" />
        <x-leap::button svg-icon="fas-xmark" x-on:click="$wire.closeImport" label="leap::resource.cancel" />
    </div>
    <div class="leap-form">
        <fieldset class="leap-fieldset">
            <div class="leap-table">
                @foreach ($this->importAttributes() as $attribute)
                    @if ($attribute->input)
                        <x-dynamic-component :component="'leap::' . $attribute->input" :attribute="$attribute" :value="$data[$attribute->name]" />
                    @endif
                @endforeach
                <label class="leap-label">
                    <span class="leap-label">@lang('leap::resource.importData')</span>
                </label>
                <table>
                    @foreach ($importData as $row)
                        @if ($loop->first)
                            <tr>
                                <td></td>
                                @for ($i = 0; $i < $importColumnCount; $i++)
                                    <td>
                                        <select class="leap-input" wire:model.live="importColumns.{{ $i }}">
                                            <option value=""></option>
                                            @foreach ($importColumnOptions as $option)
                                                <option value="{{ $option }}">{{ $this->getAttribute($option)->label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endfor
                            </tr>
                        @endif
                        <tr @class([
                            'leap-table-row-disabled' => empty($importRows[$loop->index]),
                        ])>
                            <td>
                                <input class="leap-input" type="checkbox" wire:model.live="importRows.{{ $loop->index }}" />
                            </td>
                            @for ($i = 0; $i < $importColumnCount; $i++)
                                <td @class([
                                    'error' =>
                                        isset($importErrors[$loop->index][$importColumns[$i] ?? null]) ||
                                        ($importErrors[$loop->index] ?? false) === true,
                                ])>
                                    {{ $row[$i] ?? '' }}
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                </table>
            </div>
        </fieldset>
    </div>
</div>
