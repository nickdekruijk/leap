<main class="leap-main leap-dashboard">
    <header class="leap-header">
        <h2>{{ $this->getTitle() }}</h2>
    </header>
    <article>
        <hgroup>
            <h3 x-init="$el.textContent = greeting()">&nbsp;</h3>
            <h4>
                @lang('leap::dashboard.logged_in_as', ['role' => \NickDeKruijk\Leap\Leap::context()->roleName()])
            </h4>
        </hgroup>
        <script>
            function greeting() {
                const hour = new Date().getHours();
                let greeting = '';
                if (hour < 12) greeting = {{ Js::from(__('leap::dashboard.good_morning')) }}
                else if (hour < 18) greeting = {{ Js::from(__('leap::dashboard.good_afternoon')) }}
                else greeting = {{ Js::from(__('leap::dashboard.good_evening')) }};
                return greeting + ' ' + {{ Js::from((string) Auth::user()->name) }};
            }
        </script>
    </article>
</main>
