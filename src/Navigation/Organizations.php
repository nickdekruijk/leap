<?php

namespace NickDeKruijk\Leap\Navigation;

use Illuminate\Support\Facades\Context;
use NickDeKruijk\Leap\Module;

class Organizations extends Module
{
    public $icon = 'fas-building';
    public $priority = 1000;
    public $slug = false;
}
