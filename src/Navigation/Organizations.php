<?php

namespace NickDeKruijk\Leap\Navigation;

use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Module;

class Organizations extends Module
{
    public $icon = 'fas-building';
    public $priority = 1000;
    public $slug = false;

    /**
     * Output all organizations of the current user
     *
     * @return string|null
     */
    public function getOutput(): ?string
    {
        $output = '';
        if (config('leap.organizations') && count(session('leap.user.organizations')) > 1) {
            $output .= '<li class="leap-nav-item leap-nav-organizations">';
            $output .= '<label>';
            $output .= '<input type="checkbox" class="leap-nav-collapse">';
            $output .= '<a x-on:click="document.getElementById(\'leap-nav-toggle\').checked=true">' . svg($this->getIcon(), 'leap-svg-icon')->toHtml() . session('leap.user.role.organization.name') . '</a>';

            $output .= '<ul>';
            foreach (session('leap.user.organizations') as $organization) {
                $output .= '<li><a wire:navigate href="' . route('leap.home', $organization['slug']) . '">' . $organization['name'] . '</a></li>';
            }
            $output .= '</ul>';
            $output .= '</label>';
            $output .= '</li>';
        }
        return $output;
    }
}
