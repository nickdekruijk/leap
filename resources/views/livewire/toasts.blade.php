<ul class="toasts">
    @foreach($toasts as $id => $toast)
        <li class="toast toast-{{ $toast['type'] }}" wire:click="click({{ $id }})">
            <span class="icon">
                @svg($toast['icon'], 'svg')
            </span>
            <span class="message">
                {{ $toast['message'] }}
            </span>
        </li>
    @endforeach
</ul>
