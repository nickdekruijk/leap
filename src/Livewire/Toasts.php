<?php

namespace NickDeKruijk\Leap\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Stringable;

class Toasts extends Component
{
    #[Locked]
    public $toasts = [];

    /*
     * The listeners are called with whatever params __dispatch brings, as named
     * arguments. Defaults and the variadic keep a missing message or an unknown
     * named key from throwing; add() then skips a message that is not a string.
     * Numeric keys or a non-array payload fail while Livewire unpacks them,
     * before a listener runs, so a component cannot catch those.
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
        if ($message instanceof Stringable) {
            $message = (string) $message;
        }

        if (! is_string($message)) {
            return;
        }

        if (! is_string($focus)) {
            $focus = null;
        }

        $this->toasts[] = [
            'message' => $message,
            'type' => $type,
            'icon' => $icon,
            'click' => $click,
            'focus' => $focus,
            'expires' => time() + 5,
        ];
    }

    #[On('toast-clear-expired')]
    #[On('closeEditor')]
    #[On('openEditor')]
    public function clearExpired()
    {
        $this->toasts = array_filter($this->toasts, function ($toast) {
            return $toast['expires'] > time();
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
