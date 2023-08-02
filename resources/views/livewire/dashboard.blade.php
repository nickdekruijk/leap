<div>
    <h1 class="header">@lang($title)</h1>
    <h2>@lang('welcome') {{ auth(config('leap.guard'))->user()->name }}</h2>
</div>
