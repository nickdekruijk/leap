<section class="{{ $section['_name'] }}">
    <article class="article">
        @isset($section['head'])
            <h2>{{ $section['head'] }}</h2>
        @endisset

        {!! $section['body'] !!}
    </article>
</section>
