<?php

namespace NickDeKruijk\Leap\Livewire;

use NickDeKruijk\Leap\Module;

class Resource extends Module
{
    public function render()
    {
        return view('leap::livewire.resource')->layout('leap::layouts.app');
    }
}
