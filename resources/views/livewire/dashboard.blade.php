<main class="leap-main leap-dashboard">
    <header class="leap-header">
        <h2>{{ $this->getTitle() }}</h2>
    </header>
    <article>
        <hgroup>
            <h3 x-init="$el.innerHTML = greeting()">&nbsp;</h3>
            <h4>
                Your are logged in as {{ Context::get('leap.role.name') }}
                @if (config('leap.organizations'))
                    for {{ Context::get('leap.organization.label') }}
                @endif
            </h4>
        </hgroup>
        <script>
            function greeting() {
                const hour = new Date().getHours();
                let greeting = '';
                if (hour < 12) greeting = '@lang('good_morning')'
                else if (hour < 18) greeting = '@lang('good_afternoon')'
                else greeting = '@lang('good_evening')';
                return greeting + ' {{ Auth::user()->name }}';
            }
        </script>
    </article>
</main>
