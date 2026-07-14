{{--
    The cookie registry as a table, for the privacy page.

    The table itself comes from the package (leap::cookie-table) so it stays in step with
    the consent system that sets those cookies — a privacy page that drifts away from what
    the site actually does is worse than none at all. This wrapper only puts it in the
    template's own section layout, which is why it lives here rather than in the package:
    the package has no business knowing about .main-width or .article.
--}}
<section class="default none cookies">
    <div class="main-width">
        <article class="article">
            @isset($section->head)
                <h2>{{ $section->head }}</h2>
            @endisset

            {!! $section->body ?? '' !!}

            @include('leap::cookie-table')
        </article>
    </div>
</section>
