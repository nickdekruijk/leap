/*
 * Cookie consent.
 *
 * The rendered HTML is the same for everyone — nothing about it depends on what a
 * visitor consented to — so it never contains a tracker or an <iframe>. Anything that
 * needs permission is parked in a <template>, which the browser parses but does not
 * run — no script executes, no request is made, not even for an external src. Only
 * once a category is granted is that content cloned into the page, with its <script>
 * elements recreated so they actually run.
 *
 * The path to this file is public API. Nothing in leap serves it: the frontend template
 * bundles it straight out of the package, by path
 *
 *   base_path('vendor/nickdekruijk/leap/resources/js/consent.js')
 *
 * (see the template's layouts/app.blade.php). Moving or renaming it therefore breaks
 * every generated site silently — the bundle drops one file, window.consent is
 * undefined, and the banner's Alpine init() throws where nobody is looking. Treat it
 * like any other public API: it moves on a major only, with a changelog entry.
 *
 * The choice lives in one first-party cookie, stamped with a fingerprint of the cookie
 * registry. Add a service and the fingerprint changes, the stored choice stops matching,
 * and the visitor is asked again — their consent covered what was on the table at the
 * time, and no longer does.
 *
 * window.consent is public API:
 *
 *   consent.has('analytics')   → boolean
 *   consent.grant('embeds')    → grant one category (or all, with no argument)
 *   consent.revoke('embeds')
 *   consent.open()             → reopen the banner
 *
 * and a 'consent:change' event on document, so anything gated can react without polling.
 */
window.consent = (function () {
    const COOKIE = 'consent';
    const config = window.leapConsent || { enabled: false, default: false, categories: [] };

    let state = null;

    const read = function () {
        const match = document.cookie.match(/(?:^|;\s*)consent=([^;]*)/);
        if (!match) {
            return null;
        }

        try {
            const stored = JSON.parse(decodeURIComponent(match[1]));
            // Consent covers the registry it was given for. Not this one? Ask again.
            return stored.v === config.version ? stored : null;
        } catch {
            return null;
        }
    };

    const write = function () {
        const value = encodeURIComponent(JSON.stringify(state));
        const months = 6; // long enough not to nag, short enough to stay a real choice
        const expires = new Date(Date.now() + months * 30 * 864e5).toUTCString();
        const secure = location.protocol === 'https:' ? '; Secure' : '';

        document.cookie = `${COOKIE}=${value}; path=/; expires=${expires}; SameSite=Lax${secure}`;
    };

    const activate = function () {
        document.querySelectorAll('template[data-consent]').forEach(function (template) {
            if (!api.has(template.dataset.consent) || template.dataset.activated) {
                return;
            }

            // A cloned <script> node never runs — the browser only executes scripts it
            // parsed itself or that are freshly created. So each one is rebuilt.
            const content = template.content.cloneNode(true);

            content.querySelectorAll('script').forEach(function (old) {
                const script = document.createElement('script');
                Array.from(old.attributes).forEach((a) => script.setAttribute(a.name, a.value));
                script.textContent = old.textContent;
                old.replaceWith(script);
            });

            template.dataset.activated = 'true';
            template.parentNode.insertBefore(content, template);
        });
    };

    const announce = function () {
        activate();
        document.dispatchEvent(new CustomEvent('consent:change', { detail: { ...state } }));
    };

    const api = {
        /**
         * Was this category allowed? With consent switched off nobody was asked, so the
         * configured default answers for every category — which keeps every caller on a
         * single code path, with no need to know whether a banner exists at all.
         */
        has(category) {
            if (!config.enabled) {
                return config.default;
            }

            return state ? state[category] === true : false;
        },

        /**
         * Has the visitor been asked yet? Drives whether the banner shows itself.
         */
        answered() {
            return state !== null;
        },

        grant(category) {
            state = state || { v: config.version };
            (category ? [category] : config.categories).forEach((c) => (state[c] = true));
            state.t = Math.floor(Date.now() / 1000);
            write();
            announce();
        },

        revoke(category) {
            state = state || { v: config.version };
            (category ? [category] : config.categories).forEach((c) => (state[c] = false));
            state.t = Math.floor(Date.now() / 1000);
            write();
            announce();
        },

        /**
         * Refusing has to be exactly one click, like accepting. Anything else makes the
         * consent something other than freely given, and therefore worthless.
         */
        refuseAll() {
            this.revoke();
        },

        acceptAll() {
            this.grant();
        },

        open() {
            document.dispatchEvent(new CustomEvent('consent:open'));
        },
    };

    state = read();
    activate();

    /*
     * The banner's behaviour, as an Alpine component rather than an inline x-data object.
     *
     * It used to be written in the markup, and it reached for window.consent and
     * document.addEventListener from there. Alpine's CSP build — the one that parses
     * expressions itself instead of handing them to the Function constructor, so a site
     * can drop 'unsafe-eval' from its Content-Security-Policy — allows neither: method
     * shorthand in an object literal is not in its grammar, and every value that sits on
     * globalThis is refused outright.
     *
     * Registered here rather than in the template because this is where the answer
     * already lives. The component closes over `api` and `config` directly, so it never
     * touches a global at all — the restriction is met rather than worked around, and
     * there is one place that knows how consent is stored instead of two.
     *
     * The markup keeps every other directive it had: x-show="open", x-show="!settings",
     * x-model="choice['analytics']", x-on:click="accept()". All of those the CSP parser
     * understands.
     */
    const registerComponents = function (Alpine) {
        Alpine.data('leapConsent', function () {
            return {
                open: false,
                settings: false,
                choice: {},

                init() {
                    this.open = !api.answered();

                    // Reopened from the cookie table, or by anything else that asks. A
                    // granular banner opens on its categories, since someone changing a
                    // choice they already made is asking for exactly those.
                    document.addEventListener('consent:open', () => {
                        this.open = true;
                        this.settings = config.granular === true;
                    });
                },

                accept() {
                    api.acceptAll();
                    this.open = false;
                },

                refuse() {
                    api.refuseAll();
                    this.open = false;
                },

                save() {
                    (config.categories || []).forEach((category) => {
                        this.choice[category] ? api.grant(category) : api.revoke(category);
                    });

                    this.open = false;
                },
            };
        });

        /*
         * The "change your choice" button in the cookie table. It stands on a page of its
         * own, outside the banner, so it needs a scope of its own — it used to borrow
         * whatever x-data the host layout happened to put on <body>, and to call
         * window.consent.open() through it.
         */
        Alpine.data('leapConsentReopen', function () {
            return {
                reopen() {
                    api.open();
                },
            };
        });
    };

    /*
     * Registered whichever way round the two files happen to load.
     *
     * Alpine dispatches alpine:init once, from start(), and a listener added after that
     * never hears it — so a bundle that puts this file after Alpine would register
     * nothing, the banner's x-data would name a component that does not exist, and the
     * banner would never appear. Which is to say: it would never ask, and the site would
     * look like one that had already been answered. Nothing on the page says so.
     *
     * window.Alpine only exists once Alpine's own script has run, and that script starts
     * it immediately — so finding it here means the event has been and gone. Register
     * straight away, then put the elements that named these components through init
     * again, since they were walked while the components were still unknown.
     *
     * The documented order — this file first — is still the better one: it costs nothing
     * and there is no second walk. This is here so that getting it wrong is a matter of
     * a few wasted milliseconds rather than a consent banner nobody sees.
     */
    if (window.Alpine) {
        registerComponents(window.Alpine);

        document.querySelectorAll('[x-data^="leapConsent"]').forEach(function (el) {
            window.Alpine.destroyTree(el);
            window.Alpine.initTree(el);
        });
    } else {
        document.addEventListener('alpine:init', function () {
            registerComponents(window.Alpine);
        });
    }

    return api;
})();
