@use('NickDeKruijk\Leap\Controllers\Auth2FAController')
<aside class="leap-nav-aside">
    @if (auth(config('leap.guard'))->user() && !Auth2FAController::mustValidate())
        <nav class="leap-nav">
            @include('leap::logo')
            <ul class="leap-nav-group">
                @foreach(Leap::modules() as $module)
                    @if ($module->getPriority() === 1001)
                        <li class="leap-nav-divider"></li>
                    @endif
                    <li class="leap-nav-item {{ route('leap.module.' . $module->getSlug(), session('leap.role.organization.slug')) == url()->current() ? 'active' : '' }}">
                        <a wire:navigate href="{{ route('leap.module.' . $module->getSlug(), session('leap.role.organization.slug')) }}">
                            <x-leap::icon svg-icon="{{ $module->icon }}" />{{ $module->getTitle() }}
                        </a>
                    </li>
                @endforeach
                <li class="leap-nav-item">
                    <form method="post" action="{{ route('leap.logout') }}">
                        @csrf
                        <button>
                            <x-leap::icon svg-icon="fas-sign-out-alt" />@lang('logout')
                        </button>
                    </form>
                </li>
            </ul>
        </nav>
    @endif
</aside>