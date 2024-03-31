<?php

namespace NickDeKruijk\Leap\Navigation;

use NickDeKruijk\Leap\Module;

class Logout extends Module
{
    public $icon = 'fas-sign-out-alt';
    public $priority = 1999;
    public $slug = false;
    public $default_permissions = ['read', 'update'];

    public function getTitle(): string
    {
        return __('logout');
    }

    public function getOutput(): string
    {
        return '<li class="leap-nav-item"><form method="post" action="' . route('leap.logout') . '"><input type="hidden" name="_token" value="' . csrf_token() . '" /><button>' . svg($this->getIcon(), 'leap-svg-icon')->toHtml() . __('logout') . '</button></form></li>';
    }
}
