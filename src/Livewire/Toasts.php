<?php

namespace NickDeKruijk\Leap\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Stringable;

class Toasts extends Component
{
    /*
     * Every listener can be triggered from the browser as often as anyone likes,
     * so the state is capped: expired toasts go first, then the oldest beyond
     * this count, and a message is cut at MAX_LENGTH characters. The count is a
     * safety net above one toast per invalid field. An import can send more, one
     * per error per row, but its table still marks every invalid cell.
     */
    protected const MAX_TOASTS = 50;

    protected const MAX_LENGTH = 2000;

    /*
     * expires is in whole seconds on the server, while the 5 second animation
     * starts when the response arrives. A new toast only sweeps toasts that are
     * this much past their expiry, so it never cuts off one that is still fading.
     */
    protected const SWEEP_GRACE = 2;

    #[Locked]
    public $toasts = [];

    /*
     * Ids only go up. Once clearExpired() emptied the list, PHP would start at 0
     * again, and a new toast under the wire:key of an expired one would reuse its
     * element, whose animation already ran, so it would never show.
     */
    #[Locked]
    public $nextId = 0;

    /*
     * The listeners are called with whatever params __dispatch brings: numeric
     * keys as positional arguments, which is what dispatch('toast', $message)
     * sends, string keys as named ones. Defaults and the variadic keep a missing
     * message or an unknown named key from throwing; add() then skips a message
     * it cannot show as text. A numeric key after a string key, or a payload that
     * is not an array, fails while Livewire unpacks it, before a listener runs,
     * so a component cannot catch those.
     */
    #[On('toast-error')]
    public function error($message = null, $focus = null, ...$ignored)
    {
        $this->add(
            message: $message,
            type: 'error',
            focus: $focus,
        );
    }

    #[On('toast-alert')]
    public function alert($message = null, ...$ignored)
    {
        $this->add($message, 'alert');
    }

    #[On('toast')]
    public function default($message = null, ...$ignored)
    {
        $this->add($message);
    }

    /**
     * Protected so a client cannot pick the type, icon or click itself. The
     * listeners above stay public: events always arrive through __dispatch from
     * the browser, so message and focus are outside input and checked here.
     */
    protected function add($message, $type = 'default', $icon = 'fas-exclamation-triangle', $click = null, $focus = null)
    {
        $message = static::text($message);

        if ($message === null) {
            return;
        }

        // An empty focus would give an onclick that finds no element and throws.
        $focus = static::text($focus);
        if ($focus === '') {
            $focus = null;
        }

        $this->sweep(static::SWEEP_GRACE);

        // nextId alone is enough, except for a page that was open while 1.19.4 was
        // deployed: its snapshot has toasts but no nextId yet.
        $id = max($this->nextId, $this->toasts ? max(array_keys($this->toasts)) + 1 : 0);
        $this->nextId = $id + 1;

        $this->toasts[$id] = [
            'message' => mb_strlen($message) > static::MAX_LENGTH ? mb_substr($message, 0, static::MAX_LENGTH - 1).'…' : $message,
            'type' => $type,
            'icon' => $icon,
            'click' => $click,
            'focus' => $focus,
            'expires' => time() + 5,
        ];

        // Keep the keys: they are the ids close() and wire:key use.
        $this->toasts = array_slice($this->toasts, -static::MAX_TOASTS, null, true);
    }

    /**
     * A string, a number or a Stringable as text; null for anything else, such
     * as an array from the browser or a bool, which would show as "1" or nothing.
     */
    protected static function text($value): ?string
    {
        if ($value instanceof Stringable || is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    #[On('toast-clear-expired')]
    #[On('closeEditor')]
    #[On('openEditor')]
    public function clearExpired()
    {
        $this->sweep();
    }

    protected function sweep(int $grace = 0): void
    {
        $this->toasts = array_filter($this->toasts, function ($toast) use ($grace) {
            return time() < $toast['expires'] + $grace;
        });
    }

    public function close($id)
    {
        // Called from the browser: an array as key would make unset() throw.
        if (! is_int($id) && ! is_string($id)) {
            return;
        }

        unset($this->toasts[$id]);
    }

    public function render()
    {
        return view('leap::livewire.toasts');
    }
}
