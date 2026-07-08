<?php

namespace NickDeKruijk\Leap\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class Navigation extends Component
{
    public $currentUrl;

    #[On('update-navigation')]
    public function updateNavigation() {}

    public function render()
    {
        return view('leap::livewire.navigation');
    }

    public function mount()
    {
        $this->currentUrl = url()->current();
    }
}
