<aside class="leap-nav-aside">
    <input type="checkbox" id="leap-nav-toggle">
    <label for="leap-nav-toggle" x-on:click="document.querySelectorAll('INPUT.leap-nav-collapse').forEach(function(e) {console.log(e.checked=false)})"><span></span><span></span><span></span></label>
    <nav class="leap-nav">
        @include('leap::logo')
        <ul class="leap-nav-group">
            @foreach (Leap::modules() as $module)
                @if ($module->getOutput())
                    {!! $module->getOutput() !!}
                @elseif ($module->getSlug())
                    <li class="leap-nav-item {{ $module->navigationClass() }}">
                        <a wire:navigate href="{{ route('leap.module.' . $module->getSlug(), Context::get('leap.organization')?->slug) }}">
                            <x-leap::icon svg-icon="{{ $module->icon }}" />{{ $module->getTitle() }}
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </nav>
</aside>
