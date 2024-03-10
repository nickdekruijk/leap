<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\On;

class Navigation extends Component
{
    #[On('update-navigation')]
    public function updateNavigation()
    {
    }

    public function render()
    {
        return view('leap::livewire.navigation');
    }
}
