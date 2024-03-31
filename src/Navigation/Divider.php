<?php

namespace NickDeKruijk\Leap\Navigation;

use NickDeKruijk\Leap\Module;

class Divider extends Module
{
    public $priority = 999;
    public $slug = false;
    public $default_permissions = ['read'];

    public function getOutput(): ?string
    {
        return '<li class="leap-nav-item"><hr></li>';
    }
}
