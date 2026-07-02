<aside class="leap-nav-aside">
    <input type="checkbox" id="leap-nav-toggle">
    <label for="leap-nav-toggle"><span></span><span></span><span></span></label>
    <nav class="leap-nav">
        @include('leap::logo')
        <ul class="leap-nav-group">
            @foreach (Leap::modules() as $module)
                @if (str_ends_with($module->priority, '00'))
                    <li class="leap-nav-item">
                        <hr>
                    </li>
                @endif
                @if ($module->getOutput())
                    {!! $module->getOutput() !!}
                @elseif ($module->getSlug())
                    <li class="leap-nav-item {{ route('leap.module.' . $module->getSlug()) == $currentUrl ? 'active' : '' }}">
                        <a wire:navigate href="{{ route('leap.module.' . $module->getSlug()) }}">
                            <x-leap::icon svg-icon="{{ $module->icon }}" />{{ $module->getTitle() }}
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </nav>
</aside>
