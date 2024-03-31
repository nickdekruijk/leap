<?php

namespace NickDeKruijk\Leap\Livewire;

use NickDeKruijk\Leap\Module;

class Dashboard extends Module
{
    public $component = 'leap.dashboard';
    public $icon = 'fas-gauge-high';
    public $priority = -999;
    public $title = 'Dashboard';
    public $default_permissions = ['read'];

    public function render()
    {
        return view('leap::livewire.dashboard')->layout('leap::layouts.app');
    }
}
