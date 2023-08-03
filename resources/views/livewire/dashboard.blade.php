<div>
    <h1 class="header">@lang($title)</h1>
    <div class="dashboard">
        <h2><span x-init="$el.innerHTML=greeting()"></span> {{ auth(config('leap.guard'))->user()->name }}</h2>
        <p>Your are logged in as a {{ request()->get('leap_role')->name }}.</p>
    </div>
    <script>
        function greeting() {
            const hour = new Date().getHours();
            if (hour < 12) return '@lang('good_morning')'
            else if (hour < 18) return '@lang('good_afternoon')'
            else return '@lang('good_evening')'
        }
    </script>
</div>
