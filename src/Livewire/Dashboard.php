<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public $component = 'leap.dashboard';
    public $icon = 'fas-gauge-high';
    public $priority = -999;
    public $slug = '';
    public $title = 'dashboard';

    public function __construct($options = [])
    {
        // Use the proper authentication guard
        Auth::shouldUse(config('leap.guard'));

        // Overide default options
        foreach ($options as $option => $value) {
            $this->$option = $value;
        }
    }

    public function render()
    {
        return view('leap::livewire.dashboard')->layout('leap::layouts.app');
    }
}
