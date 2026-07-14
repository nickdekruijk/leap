const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Navigation + search state (Alpine)
document.addEventListener('alpine:init', function () {
    const mobile = window.matchMedia('(max-width: 768px)');

    Alpine.data('navigation', function () {
        return {
            navExpanded: false,
            scrolling: false,
            searchOpen: false,

            // Submenus fold open and shut on desktop, but inside the hamburger panel
            // they are simply listed under their parent. Alpine sets display:none
            // inline on a hidden submenu, which CSS cannot override, so the panel has
            // to know it is on a phone rather than fight it with !important.
            // Kept in sync with $bp-mobile in template.scss.
            isMobile: mobile.matches,

            init() {
                mobile.addEventListener('change', (e) => (this.isMobile = e.matches));

                this.$watch('searchOpen', (value) => {
                    if (value) {
                        this.$nextTick(() => document.getElementById('search-input')?.focus());
                    }
                });

                window.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.navExpanded = false;
                        this.searchOpen = false;
                        return;
                    }
                    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                        e.preventDefault();
                        this.searchOpen = !this.searchOpen;
                        return;
                    }
                    const tag = document.activeElement?.tagName;
                    if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) && !document.activeElement?.isContentEditable) {
                        e.preventDefault();
                        this.searchOpen = true;
                    }
                });
            },
        };
    });
});

// Carousel(s)
document.querySelectorAll('.slider').forEach(function (slider) {
    new Slider({
        selector: '#' + slider.id,
        slideSelector: '.slide',
        interval: reduceMotion ? 0 : 5000,
    });
});

// Video sections: swap the poster for the player when it is clicked.
//
// The iframe is built here, inside the click handler, rather than by Alpine. A browser
// only lets a video start with sound if it can tie the play to a user gesture, and an
// iframe conjured up a tick later — as x-if does — no longer counts. Vimeo quietly falls
// back to starting muted, so it looked fine; YouTube just sat on its first frame.
document.querySelectorAll('.video-poster').forEach(function (poster) {
    const section = poster.closest('.video');
    const notice = section.querySelector('.video-consent');

    const play = function () {
        const iframe = document.createElement('iframe');

        iframe.src = poster.dataset.video;
        iframe.title = poster.dataset.videoTitle || '';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
        iframe.allowFullscreen = true;

        if (notice) {
            notice.hidden = true;
        }

        poster.replaceWith(iframe);
    };

    poster.addEventListener('click', function () {
        // Loading the player sends the visitor's data to Google or Vimeo, so it waits for
        // permission. With consent switched off, has() answers with the configured
        // default and this is simply never in the way.
        if (!notice || window.consent.has('embeds')) {
            play();

            return;
        }

        notice.hidden = false;
    });

    if (!notice) {
        return;
    }

    // Consent for this one video: an informed click on a button that says what it does.
    // Refusing embeds in general should not mean never watching anything.
    notice.querySelector('.video-consent-once')?.addEventListener('click', play);

    notice.querySelector('.video-consent-always')?.addEventListener('click', function () {
        window.consent.grant('embeds');
        play();
    });
});

// Horizontal-scroll card sections. The scroller turns CSS scroll-snap off while
// dragging and back on for button navigation itself (disableSnapOnDrag, default).
new HorizontalScroller({
    selector: '.items-horizontal .items-container',
    buttonRight: true,
    buttonLeft: true,
    draggable: true,
});

// Light parallax on slider media (skipped when the user prefers reduced motion)
if (!reduceMotion) {
    let pending = false;

    const update = function () {
        pending = false;
        document.querySelectorAll('.slider .slide img, .slider .slide video').forEach(function (media) {
            const rect = media.getBoundingClientRect();
            const offset = rect.top + rect.height / 2 - window.innerHeight / 2;
            media.style.objectPosition = 'center calc(50% + ' + offset * -0.05 + 'px)';
        });
    };

    window.addEventListener('scroll', function () {
        if (!pending) {
            pending = true;
            requestAnimationFrame(update);
        }
    }, { passive: true });

    document.addEventListener('DOMContentLoaded', update);
}
