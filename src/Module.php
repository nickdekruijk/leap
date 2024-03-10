<?php

namespace NickDeKruijk\Leap;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use NickDeKruijk\Leap\Traits\NavigationItem;

class Module extends Component
{
    public $component;

    use NavigationItem;

    public function __construct($options = [])
    {
        // Overide default options
        foreach ($options as $option => $value) {
            $this->$option = $value;
        }
    }
}
