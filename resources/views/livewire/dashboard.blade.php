<div class="dashboard">
    <header><h2>{{ $this->getTitle() }}</h2></header>
    <hgroup>
        <h3 x-init="$el.innerHTML = greeting()">&nbsp;</h3>
        <h4>
            Your are logged in as {{ session('leap.role')->name }}{{ config('leap.organizations') ? ' for '.session('leap.role.organization.name') : '' }}.
        </h4>
    </hgroup>
    <script>
        function greeting() {
            const hour = new Date().getHours();
            let greeting = '';
            if (hour < 12) greeting = '@lang('good_morning')'
            else if (hour < 18) greeting = '@lang('good_afternoon')'
            else greeting = '@lang('good_evening')';
            return greeting + ' {{ auth(config('leap.guard'))->user()->name }}';
        }
    </script>
</div>
