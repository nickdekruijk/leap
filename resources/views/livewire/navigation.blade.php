<aside class="leap-nav-aside">
    @if (auth(config('leap.guard'))->user() && !NickDeKruijk\Leap\Controllers\Auth2FAController::mustValidate())
        <nav class="leap-nav">
            @include('leap::logo')
            <ul class="leap-nav-group">
                @foreach(Leap::modules() as $module)
                    @if ($module->getOutput())
                        {!! $module->getOutput() !!}
                    @elseif ($module->getSlug())
                        <li class="leap-nav-item {{ $module->navigationClass() }}">
                            <a wire:navigate href="{{ route('leap.module.' . $module->getSlug(), session('leap.role.organization.slug')) }}">
                                <x-leap::icon svg-icon="{{ $module->icon }}" />{{ $module->getTitle() }}
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
    @endif
</aside>