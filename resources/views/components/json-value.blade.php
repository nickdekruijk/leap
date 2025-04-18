@if (is_array($value))
    @isset($head)
        <tr>
            <th>{{ $head }}</th>
        </tr>
    @endisset
    @foreach ($value as $key => $value)
        @include('leap::components.json-value', ['head' => $key, 'value' => $value, 'depth' => ($depth ?? 0) + 1])
    @endforeach
@else
    <tr class="leap-json-readonly-depth-{{ $depth ?? 0 }}">
        @isset($depth)
            <td>{!! $key !!}</td>
        @endisset
        <td>{!! $value !!}</td>
    </tr>
@endif
