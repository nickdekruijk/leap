<aside class="leap-nav-aside">
    <input type="checkbox" id="leap-nav-toggle">
    <label for="leap-nav-toggle"><span></span><span></span><span></span></label>
    <nav class="leap-nav">
        @include('leap::logo')
        <ul class="leap-nav-group">
            @foreach (Leap::modules() as $module)
                @if ($module instanceof NickDeKruijk\Leap\Navigation\Organizations && config('leap.organizations') && count(Context::get('leap.user.organizations')) > 1)
                    <li class="leap-nav-item leap-nav-collapsable @if ($this->showOrganizations) leap-nav-collapsable-open @endif">
                        <a wire:click="toggleOrganizations"><x-leap::icon svg-icon="{{ $module->icon }}" />{{ Context::get('leap.organization')->name }}</a>
                        @if ($this->showOrganizations)
                            <ul class="leap-nav-organizations" wire:transition.scale.origin.top>
                                @foreach (Context::get('leap.user.organizations') as $organization)
                                    <li>
                                        <a wire:navigate href="{{ route('leap.home', $organization['slug']) }}">{{ $organization['name'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @elseif ($module->getOutput())
                    {!! $module->getOutput() !!}
                @elseif ($module->getSlug())
                    <li class="leap-nav-item {{ route('leap.module.' . $module->getSlug(), Context::get('leap.organization')?->slug) == $currentUrl ? 'active' : '' }}">
                        <a wire:navigate href="{{ route('leap.module.' . $module->getSlug(), Context::get('leap.organization')?->slug) }}">
                            <x-leap::icon svg-icon="{{ $module->icon }}" />{{ $module->getTitle() }}
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </nav>
</aside>
