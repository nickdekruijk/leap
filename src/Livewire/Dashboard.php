<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function __construct()
    {
        Auth::shouldUse(config('leap.guard'));
    }

    public function render()
    {
        return view('leap::livewire.dashboard')->layout('leap::layouts.app');
    }
}
