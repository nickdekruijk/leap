<main class="leap-main leap-dashboard">
    <header class="leap-header">
        <h2>{{ $this->getTitle() }}</h2>
    </header>
    <article>
        <hgroup>
            <h3 x-init="$el.innerHTML = greeting()">&nbsp;</h3>
            <h4>
                @lang(config('leap.organizations') ? 'leap::dashboard.logged_in_as_organization' : 'leap::dashboard.logged_in_as', ['role' => Context::getHidden('leap.role.name'), 'organization' => Context::getHidden('leap.organization.label')])
            </h4>
        </hgroup>
        <script>
            function greeting() {
                const hour = new Date().getHours();
                let greeting = '';
                if (hour < 12) greeting = '@lang('leap::dashboard.good_morning')'
                else if (hour < 18) greeting = '@lang('leap::dashboard.good_afternoon')'
                else greeting = '@lang('leap::dashboard.good_evening')';
                return greeting + ' {{ Auth::user()->name }}';
            }
        </script>
    </article>
</main>
