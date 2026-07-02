(function () {
    'use strict';

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]').content;
    }

    function jsonFetch(url, options) {
        options = options || {};
        options.headers = Object.assign({
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        }, options.headers || {});

        return fetch(url, options).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok) {
                    throw new Error((data.message) || (data.errors && Object.values(data.errors)[0][0]) || 'Request failed.');
                }
                return data;
            });
        });
    }

    function supported() {
        return typeof window.PublicKeyCredential !== 'undefined'
            && typeof PublicKeyCredential.parseRequestOptionsFromJSON === 'function'
            && typeof PublicKeyCredential.parseCreationOptionsFromJSON === 'function';
    }

    /**
     * Sign in with an existing passkey and redirect on success.
     */
    window.leapPasskeyLogin = function (remember) {
        if (!supported()) {
            alert('This browser does not support passkeys.');
            return;
        }

        jsonFetch('/passkeys/login/options')
            .then(function (data) {
                var publicKey = PublicKeyCredential.parseRequestOptionsFromJSON(data.options);
                return navigator.credentials.get({ publicKey: publicKey });
            })
            .then(function (credential) {
                return jsonFetch('/passkeys/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        credential: credential.toJSON(),
                        remember: !!remember,
                    }),
                });
            })
            .then(function (data) {
                window.location = data.redirect;
            })
            .catch(function (error) {
                if (error.name !== 'NotAllowedError') {
                    alert(error.message || 'Unable to sign in with this passkey.');
                }
            });
    };

    /**
     * Confirm the current (already authenticated) user's identity with an
     * existing passkey and redirect on success.
     */
    window.leapPasskeyConfirm = function () {
        if (!supported()) {
            alert('This browser does not support passkeys.');
            return;
        }

        jsonFetch('/passkeys/confirm/options')
            .then(function (data) {
                var publicKey = PublicKeyCredential.parseRequestOptionsFromJSON(data.options);
                return navigator.credentials.get({ publicKey: publicKey });
            })
            .then(function (credential) {
                return jsonFetch('/passkeys/confirm', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        credential: credential.toJSON(),
                    }),
                });
            })
            .then(function (data) {
                window.location = data.redirect;
            })
            .catch(function (error) {
                if (error.name !== 'NotAllowedError') {
                    alert(error.message || 'Unable to verify with this passkey.');
                }
            });
    };

    /**
     * Register a new passkey for the current user, then reload to refresh the list.
     */
    window.leapPasskeyRegister = function (name) {
        if (!supported()) {
            alert('This browser does not support passkeys.');
            return;
        }

        jsonFetch('/user/passkeys/options')
            .then(function (data) {
                var publicKey = PublicKeyCredential.parseCreationOptionsFromJSON(data.options);
                return navigator.credentials.create({ publicKey: publicKey });
            })
            .then(function (credential) {
                return jsonFetch('/user/passkeys', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name: name,
                        credential: credential.toJSON(),
                    }),
                });
            })
            .then(function () {
                window.location.reload();
            })
            .catch(function (error) {
                if (error.name !== 'NotAllowedError') {
                    alert(error.message || 'Unable to register this passkey.');
                }
            });
    };

    /**
     * Delete a passkey by id, then reload to refresh the list.
     */
    window.leapPasskeyDelete = function (id) {
        jsonFetch('/user/passkeys/' + id, { method: 'DELETE' })
            .then(function () {
                window.location.reload();
            })
            .catch(function (error) {
                alert(error.message || 'Unable to delete this passkey.');
            });
    };
})();
