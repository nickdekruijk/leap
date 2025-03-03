<?php

namespace NickDeKruijk\Leap\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class Toasts extends Component
{
    public $toasts = [];

    #[On('toast-error')]
    public function error($message, $focus = null)
    {
        $this->add(
            message: $message,
            type: 'error',
            focus: $focus,
        );
    }

    #[On('toast-alert')]
    public function alert($message)
    {
        $this->add($message, 'alert');
    }

    #[On('toast')]
    public function default($message)
    {
        $this->add($message);
    }

    public function add($message, $type = 'default', $icon = 'fas-exclamation-triangle', $click = null, $focus = null)
    {
        $this->toasts[] = [
            'message' => $message,
            'type' => $type,
            'icon' => $icon,
            'click' => $click,
            'focus' => $focus,
            'expiresAt' => now()->addSeconds(9),
        ];
    }

    public function clearExpired()
    {
        $this->toasts = array_filter($this->toasts, function ($toast) {
            return $toast['expiresAt'] > now();
        });
    }

    public function close($id)
    {
        unset($this->toasts[$id]);
    }

    public function render()
    {
        return view('leap::livewire.toasts');
    }
}
