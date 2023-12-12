<aside class="nav-aside">
    @auth(config('leap.guard')) 
        <nav>
            @include('leap::logo')
            <ul>
                @foreach(Leap::modules() as $module)
                    @if ($module->getPriority() === 1001)
                        </ul><ul>
                    @endif
                    <li class="{{ route('leap.module.' . $module->getSlug()) == url()->current() ? 'active' : '' }}">
                        <a wire:navigate href="{{ route('leap.module.' . $module->getSlug()) }}">
                            @svg($module->icon, 'nav-icon'){{ $module->getTitle() }}
                        </a>
                    </li>
                @endforeach
                <li class="logout">
                    <form method="post" action="{{ route('leap.logout') }}">
                        @csrf
                        <button class="outline">
                            @svg('fas-sign-out-alt', 'nav-icon')@lang('logout')
                        </button>
                    </form>
                </li>
            </ul>
        </nav>
    @endif
</aside>