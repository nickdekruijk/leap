@php
    $disallowAll = (bool) config('leap.robots.disallow_all');
    $paths = array_values(array_filter((array) config('leap.robots.disallow', []), 'strlen'));

    $rules = $paths
        ? implode("\n", array_map(fn (string $path): string => 'Disallow: '.$path, $paths))
        : 'Disallow:';

    $agents = [
        'GPTBot', 'OAI-SearchBot', 'ChatGPT-User',
        'ClaudeBot', 'Claude-SearchBot', 'Claude-User',
        'PerplexityBot', 'Perplexity-User',
        'Google-Extended', 'Applebot-Extended',
        'meta-externalagent', 'Amazonbot', 'cohere-ai',
        'Bytespider', 'YouBot', 'Diffbot',
    ];

    $aiCrawlers = config('leap.robots.ai_crawlers', 'allow');

    // A route name, an absolute URL, or false. The route belongs to the frontend and
    // not to leap, so a name nothing answers to leaves the line out: no Sitemap is
    // better than one pointing at a 404. A site that may not be crawled is offered
    // no sitemap at all.
    $sitemap = config('leap.robots.sitemap', 'sitemap');
    if ($disallowAll || ! is_string($sitemap) || $sitemap === '') {
        $sitemap = null;
    } elseif (! str_starts_with($sitemap, 'http')) {
        $sitemap = Route::has($sitemap) ? route($sitemap) : null;
    }

    // The file holds directives and nothing else. A comment in it is readable by
    // anyone, and says things about the site better kept out of sight: which software
    // wrote it, or that this is not the production site. Why each group is there is
    // written down here instead.
    //
    // disallow_all: a copy of a site is the same site to a crawler, and the copy is
    // picked as the canonical one often enough to matter, so nothing is to be crawled.
    // That forbids crawling and not indexing: a URL that is linked to somewhere can
    // still be listed. Getting one back out of an index takes an X-Robots-Tag, which a
    // crawler only ever sees on a page it is allowed to fetch.
    //
    // The answer engine crawlers are named only to keep them out. Allowed, they fall
    // under * like everyone else, and a group repeating that would only be noise.
    // 'omit' is what 'allow' is now, kept for configs that set it. Google-Extended and
    // Applebot-Extended are the two that also cover training, not only answering.
@endphp
@if ($disallowAll)
User-agent: *
Disallow: /
@else
User-agent: *
{!! $rules !!}
@if ($aiCrawlers === 'disallow')

@foreach ($agents as $agent)
User-agent: {{ $agent }}
@endforeach
Disallow: /
@endif
@if ($sitemap)

Sitemap: {{ $sitemap }}
@endif
@endif
