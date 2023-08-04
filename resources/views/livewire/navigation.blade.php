<div>
    @auth(config('leap.guard')) 
        @isset($currentModule)
            <nav class="nav">
                @include('leap::logo')
                <ul>
                    @foreach(Leap::modules() as $module)
                        @if ($module->priority === 1001)
                            <li class="bottom-divider"></li>
                        @endif
                        <li class="{{ $currentModule == $module->slug ? 'active' : '' }}">
                            <a wire:navigate href="{{ route('leap.module', $module->slug) }}">
                                @svg($module->icon, 'nav-icon')@lang($module->title)
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
    @endif
</div>
