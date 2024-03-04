@use('NickDeKruijk\Leap\Controllers\Auth2FAController')
<aside class="leap-nav-aside">
    @if (auth(config('leap.guard'))->user() && !Auth2FAController::mustValidate())
        <nav class="leap-nav">
            @include('leap::logo')
            <ul class="leap-nav-group">
                @foreach(Leap::modules() as $module)
                    <li class="leap-nav-item {{ $module->isActive() ? 'active' : '' }}">
                        @if ($module->getOutput())
                            {!! $module->getOutput() !!}
                        @else
                            <a wire:navigate href="{{ route('leap.module.' . $module->getSlug(), session('leap.role.organization.slug')) }}">
                                <x-leap::icon svg-icon="{{ $module->icon }}" />{{ $module->getTitle() }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</aside>