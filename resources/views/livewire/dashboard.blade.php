<div>
    <h1 class="header">{{ request()->segment(2) ?: 'Dashboard' }}</h1>
    <h2>@lang('welcome') {{ auth(config('leap.guard'))->user()->name }}</h2>
</div>
