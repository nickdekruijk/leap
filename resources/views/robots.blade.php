@php
    $disallowAll = (bool) config('leap.robots.disallow_all');
    $paths = array_values(array_filter((array) config('leap.robots.disallow', []), 'strlen'));

    // Every group repeats the same rules, because a crawler obeys the most specific
    // group that names it and reads no other one: a rule written only under * does
    // not reach a bot that finds itself listed further down.
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
@endphp
@if ($disallowAll)
# This is not the production site. A copy of a site is the same site to a crawler,
# and the copy is picked as the canonical one often enough to matter, so nothing here
# is to be crawled.
#
# Note that this forbids crawling and not indexing: a URL that is linked to somewhere
# can still be listed. Getting one back out of an index takes an X-Robots-Tag, which
# a crawler only ever sees on a page it is allowed to fetch.
User-agent: *
Disallow: /
@else
User-agent: *
{!! $rules !!}
@if ($aiCrawlers !== 'omit')

# The crawlers behind the answer engines.
@if ($aiCrawlers === 'allow')
# This group says the same as the one above, so it changes nothing today: it is here
# so that a later "Disallow: /" added in a hurry does not take them out along with
# everything else, and so that allowing them reads as a decision rather than as an
# oversight.
#
# Google-Extended and Applebot-Extended are the two that also cover training, not
# only answering.
@endif
@foreach ($agents as $agent)
User-agent: {{ $agent }}
@endforeach
@if ($aiCrawlers === 'disallow')
Disallow: /
@elseif ($paths)
{!! $rules !!}
@else
Allow: /
@endif
@endif
@if ($sitemap)

Sitemap: {{ $sitemap }}
@endif
@endif
