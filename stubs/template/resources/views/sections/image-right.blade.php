<section class="{{ $section['_name'] }}">
    <div class="images">
        <img src="{{ $section['image']->media->downloadUrl }}" alt="">
    </div>
    <article class="article">
        @isset($section['head'])
            <h2>{{ $section['head'] }}</h2>
        @endisset
        {!! $section['body'] !!}
    </article>
</section>
