<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ Leap::htmlTitle() }}</title>
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family=open-sans:300,300i,400,400i,500,500i,600,600i,700,700i,800,800i">
        {!! \NickDeKruijk\Leap\Controllers\AssetController::cssLink() !!}
        {!! \NickDeKruijk\Leap\Controllers\AssetController::tinymceContentCssLink() !!}
        @if (config('leap.auth_passkeys.enabled'))
            <script src="{{ route('leap.js') }}?{{ \NickDeKruijk\Leap\Controllers\AssetController::jsFilemtime() }}" defer></script>
        @endif
        <script src="https://cdn.jsdelivr.net/npm/@marcreichel/alpine-autosize@latest/dist/alpine-autosize.min.js" defer></script>
        {{-- Whether the open editor has unsaved work, and what the preview tab is showing.
             In a store rather than in an x-data: both facts are read by the index and
             written by the editor, which are two Livewire components, and a plain variable
             declared on a Livewire root element cannot be assigned to at all: Livewire
             routes the assignment through its own property proxy and it throws. --}}
        <script>
            document.addEventListener('alpine:init', () => {
                // Deliberately not a key of the store: Alpine makes a store reactive, and
                // Livewire's $wire is itself a proxy whose $-methods that wrapper does not
                // carry: $refresh comes back undefined through it. A plain variable in
                // this closure stays the object Livewire handed over.
                let editorWire = null;
                let editorId = null;

                Alpine.store('leapEditor', {
                    // Typing the server has not seen yet.
                    touched: false,
                    // What the server said the last time it answered.
                    serverDirty: false,

                    // Never read off $wire as a property: what comes back for one is a
                    // property while Livewire has it and a callable when it does not, and
                    // a callable is neither true nor false. Both halves are kept here, in
                    // plain booleans, and put there by things that cannot be misread: a
                    // trusted event, and a value Livewire hands over itself.
                    get dirty() {
                        return this.touched === true || this.serverDirty === true;
                    },
                    // The editor hands its own $wire over here, so the index can ask the
                    // server about an editor it has no scope on.
                    setWire(wire) {
                        editorWire = wire;
                        editorId = wire?.__instance?.id ?? wire?.$id ?? null;
                    },

                    // Whether it is all right to throw the open editor away, asking first
                    // when there is something to lose. Resolves to true when the caller may
                    // go ahead -- callers act in .then(), never on a bare return, so a
                    // promise nobody waited for cannot let a click through.
                    //
                    // "Something was typed" is not the same as "something is different":
                    // type a letter and delete it again and the browser still knows only
                    // that keys were pressed. The server knows the values, and one round
                    // trip carries the typing along -- wire:model is deferred, so those
                    // values are sent with the next request whatever it is. So a maybe is
                    // turned into an answer before anyone is asked a question.
                    //
                    // Every uncertainty resolves towards asking: an editor we cannot reach,
                    // a request that fails, an answer we cannot read. Losing an afternoon's
                    // typing is worse than one question too many.
                    async confirmLeave(message) {
                        if (this.dirty !== true) return true;

                        if (this.touched === true && typeof editorWire?.stillDirty === 'function') {
                            // The answer comes back from the call itself rather than from a
                            // property read afterwards: one round trip, one value, and
                            // nothing that can quietly read as undefined.
                            const clean = await editorWire.stillDirty()
                                .then(still => still === false)
                                .catch(() => false);

                            if (clean === true) {
                                this.touched = false;
                                this.dirty = false;

                                return true;
                            }
                        }

                        return confirm(message);
                    },
                });

                // Any request the editor makes carries the typing with it, so once one has
                // come back the browser's "something was typed" is stale by definition and
                // the server's verdict takes over. Switch a language tab, pick an image,
                // save: each of them settles the question for free. Only typing with no
                // request after it leaves the browser guessing, and there it guesses towards
                // asking.
                window.Livewire?.hook?.('commit', ({ component, succeed }) => {
                    succeed(() => {
                        if (component.$wire !== editorWire && (!editorId || component.id !== editorId)) {
                            return;
                        }

                        // Only on a value we can actually read. Could we not, the browser's
                        // own guess stays standing rather than being replaced by nothing at
                        // all -- that is the difference between one question too many and a
                        // lost afternoon.
                        const answer = editorWire?.get?.('dirty');

                        if (typeof answer === 'boolean') {
                            Alpine.store('leapEditor').serverDirty = answer;
                            Alpine.store('leapEditor').touched = false;
                        }
                    });
                });
            });
        </script>

    </head>

    <body>
        <div class="leap">
            @auth(config('leap.guard'))
                @if (!NickDeKruijk\Leap\Leap::mustValidateTwoFactor())
                    @livewire('leap.navigation')
                @endif
            @endauth
            @livewire('leap.toasts')
            {{ $slot }}
        </div>
    </body>

    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/sort@3.x.x/dist/cdn.min.js"></script>

    <script>
        Livewire.hook('request', ({
            fail
        }) => {
            fail(({
                status,
                preventDefault
            }) => {
                if (status === 419) {
                    preventDefault();
                    if (confirm('@lang('leap::auth.page_expired')')) {
                        window.location.reload();
                    }
                }
            })
        })
    </script>

</html>
