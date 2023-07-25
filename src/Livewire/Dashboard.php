<?php

namespace NickDeKruijk\Leap\Livewire;

use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        return view('leap::livewire.dashboard')->layout('leap::layouts.app');
    }
}
