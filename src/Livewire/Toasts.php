<?php

namespace NickDeKruijk\Leap\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class Toasts extends Component
{
    public $toasts = [];

    #[On('toast-error')]
    public function error($message)
    {
        $this->add('error', $message, 'fas-exclamation-triangle');
    }

    #[On('toast-alert')]
    public function alert($message)
    {
        $this->add('alert', $message, 'fas-exclamation-triangle');
    }

    #[On('toast')]
    public function default($message)
    {
        $this->add('default', $message, 'far-check-circle');
    }

    public function add($type, $message, $icon)
    {
        $this->toasts[] = [
            'type' => $type,
            'icon' => $icon,
            'message' => $message,
        ];
    }

    public function click($id)
    {
        unset($this->toasts[$id]);
    }

    public function render()
    {
        return view('leap::livewire.toasts');
    }
}
