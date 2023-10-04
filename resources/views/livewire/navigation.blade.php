<div>
    @auth(config('leap.guard')) 
        <nav class="nav">
            @include('leap::logo')
            <ul>
                @foreach(Leap::modules() as $module)
                    @if ($module->getPriority() === 1001)
                        <li class="bottom-divider"></li>
                    @endif
                    <li class="{{ route('leap.module.' . $module->getSlug()) == url()->current() ? 'active' : '' }}">
                        <a wire:navigate href="{{ route('leap.module.' . $module->getSlug()) }}">
                            @svg($module->icon, 'nav-icon'){{ $module->getTitle() }}
                        </a>
                    </li>
                @endforeach
                <li class="logout">
                    <form method="post" action="{{ route('leap.logout') }}" onclick="this.submit()">
                        @csrf
                        @svg('fas-sign-out-alt', 'nav-icon')@lang('logout')
                    </form>
                </li>
            </ul>
        </nav>
    @endif
</div>
