<?php

namespace NickDeKruijk\Leap\Navigation;

use NickDeKruijk\Leap\Traits\NavigationItem;

class Logout
{
    use NavigationItem;

    public $icon = 'fas-sign-out-alt';
    public $priority = 1999;
    public $slug = false;

    public function getTitle(): string
    {
        return __('logout');
    }

    public function getOutput(): string
    {
        return '<form method="post" action="' . route('leap.logout') . '"><input type="hidden" name="_token" value="' . csrf_token() . '" /><button>' . svg($this->getIcon(), 'leap-svg-icon')->toHtml() . __('logout') . '</button></form>';
    }
}
