<?php

namespace NickDeKruijk\Leap;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use NickDeKruijk\Leap\Traits\NavigationItem;

class Module extends Component
{
    use NavigationItem;

    public function __construct($options = [])
    {
        Auth::shouldUse(config('leap.guard'));
        // Overide default options
        foreach ($options as $option => $value) {
            $this->$option = $value;
        }
    }
}
