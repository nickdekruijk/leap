<?php

namespace NickDeKruijk\Leap\Navigation;

use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Traits\NavigationItem;

class Organizations
{
    use NavigationItem;

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
        if (config('leap.organizations')) {
            $output .= '<li class="leap-nav-item leap-nav-organizations">';
            $output .= '<a>' . svg($this->getIcon(), 'leap-svg-icon')->toHtml() . session('leap.role.organization.name') . '</a>';

            $output .= '<ul>';
            foreach (Leap::userOrganizations() as $organization) {
                $output .= '<li><a href="' . route('leap.home', $organization->slug) . '">' . $organization->name . '</a></li>';
            }
            $output .= '</ul>';
            $output .= '</li>';
        }
        return $output;
    }
}
