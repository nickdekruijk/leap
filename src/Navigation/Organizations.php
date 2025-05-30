<?php

namespace NickDeKruijk\Leap\Navigation;

use Illuminate\Support\Facades\Context;
use NickDeKruijk\Leap\Module;

class Organizations extends Module
{
    public $icon = 'fas-building';
    public $priority = -101;
    public $slug = false;

    public $default_permissions = ['read'];
}
