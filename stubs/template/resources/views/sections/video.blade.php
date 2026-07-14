{{--
    Video with a play button over a poster. YouTube or Vimeo — a numeric id is Vimeo's,
    anything else YouTube's.

    Nothing third-party is in this page. The player only loads when a visitor clicks, and
    the poster is fetched from the provider once and stored locally: hotlinking it would
    call on YouTube on every single page view, which is exactly what the click-to-load
    player is here to avoid.

    The iframe is built in the click handler itself (scripts.js), not by Alpine: a browser
    only lets a video start with sound if it can tie the play to a click, and an iframe
    conjured up a tick later no longer counts. Vimeo quietly falls back to starting muted,
    YouTube simply sits there.

    An uploaded poster wins over the provider's, so an editor can always override it.
--}}
@php
    $video = new App\Support\Video((string) ($section['video_id'] ?? ''));
    $uploaded = ($section['image'] ?? null)?->first();
    $poster = $uploaded ? null : $video->poster();
@endphp

<section class="video">
    <button
        type="button"
        class="video-poster"
        data-video="{{ $video->embedUrl() }}"
        data-video-title="{{ $section['head'] ?? __('Video') }}"
        aria-label="{{ __('Video afspelen') }}">
        @if ($uploaded)
            <x-responsive-image :media="$uploaded" sizes="100vw" :widths="[900, 1200, 1600, 1920, 2560]" fallback="1600" decorative />
        @elseif ($poster)
            <img src="{{ asset_resized('1600', $poster) }}" alt="" loading="lazy">
        @else
            <span class="image-placeholder" aria-hidden="true"></span>
        @endif
        <span class="video-play" aria-hidden="true"></span>
    </button>

    {{--
        Shown instead of playing when the "embeds" category has not been granted. Loading
        the player sends the visitor's data — their IP among it — to Google or Vimeo, so
        it cannot happen unasked.

        Two ways out, because refusing embeds site-wide should not mean never watching a
        single video: [Load video] plays this one and nothing more — an informed click on
        a button that says what it does is consent for exactly that — while [Always allow]
        grants the category. Hidden by default and revealed by scripts.js, so the HTML
        stays the same for everyone and can be cached.
    --}}
    <div class="video-consent" hidden>
        <p>{{ __('Deze video wordt geladen van :provider. Daarbij worden je gegevens, waaronder je IP-adres, naar de aanbieder gestuurd.', ['provider' => $video->provider()]) }}</p>

        <p>
            <button type="button" class="button video-consent-once">{{ __('Video laden') }}</button>
            <button type="button" class="button outline video-consent-always">{{ __('Altijd toestaan') }}</button>
        </p>
    </div>
</section>
