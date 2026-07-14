/*
 * Cookie consent.
 *
 * Pages are cached server-side, so the HTML is the same for everyone: it never contains
 * a tracker or an <iframe>. Anything that needs permission is parked in a <template>,
 * which the browser parses but does not run — no script executes, no request is made,
 * not even for an external src. Only once a category is granted is that content cloned
 * into the page, with its <script> elements recreated so they actually run.
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

    return api;
})();
