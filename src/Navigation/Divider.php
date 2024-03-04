<?php

namespace NickDeKruijk\Leap\Navigation;

use NickDeKruijk\Leap\Traits\NavigationItem;

class Divider
{
    use NavigationItem;

    public $priority = 999;
    public $slug = false;

    public function getOutput(): ?string
    {
        return '<li class="leap-nav-item"><hr></li>';
    }
}
