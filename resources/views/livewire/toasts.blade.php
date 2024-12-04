<ul class="toasts" wire:poll.60s="clearExpired">
    @foreach ($toasts as $id => $toast)
        <li class="toast toast-{{ $toast['type'] }}" @if ($toast['focus']) onclick="document.getElementById('{{ $toast['focus'] }}').focus()" @endif>
            <div class="toast-close" wire:click="close({{ $id }})">&times;</div>
            <span class="icon">
                @svg($toast['icon'], 'svg')
            </span>
            <span class="message">
                {{ $toast['message'] }}
            </span>
        </li>
    @endforeach
</ul>
