<div>
    <h1 class="header">{{ $this->getTitle() }}</h1>
    <div class="dashboard">
        <h2 x-init="$el.innerHTML = greeting()">&nbsp;</h2>
        <p>Your are logged in as a {{ session('leap.role')->name }}.</p>
    </div>
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
