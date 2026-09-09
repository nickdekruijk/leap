<?php

namespace NickDeKruijk\Leap\Livewire;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Models\Redirect;
use NickDeKruijk\Leap\Resource;

class Redirects extends Resource
{
    public function attributes()
    {
        return [
            Attribute::make('path')->index(1)->searchable()->required()->unique()
                ->label(__('leap::redirects.path'))->placeholder(__('leap::redirects.path_placeholder')),
            Attribute::make('destination')->index(2)->searchable()
                ->label(__('leap::redirects.destination'))->placeholder(__('leap::redirects.destination_placeholder')),
            Attribute::make('status')->select()->values([
                301 => __('leap::redirects.status_301'),
                302 => __('leap::redirects.status_302'),
            ])->default(301)->label(__('leap::redirects.status')),
            Attribute::make('active')->index(3)->switch()->default(true)->label(__('leap::redirects.active')),
            Attribute::make('hits')->indexOnly(4)->label(__('leap::redirects.hits')),
            Attribute::make('last_used_at')->indexOnly(5)->label(__('leap::redirects.last_used_at')),
            Attribute::make('detected')->index(6)->switch()->readonly()->default(false)->label(__('leap::redirects.detected')),
            // The half of a detected address that says where to go and fix the link,
            // rather than only papering over it with a redirect.
            Attribute::make('referer_list')->accessor('referers')->textarea()->label(__('leap::redirects.referers')),
            // Off unless the project asks for them, so usually empty. Together they
            // answer whether a dead address still has people on it or only a crawler.
            Attribute::make('user_agent_list')->accessor('user_agents')->textarea()->label(__('leap::redirects.user_agents')),
            Attribute::make('ip_address_list')->accessor('ip_addresses')->textarea()->label(__('leap::redirects.ip_addresses')),
        ];
    }

    public $model = Redirect::class;

    /**
     * Fixed rather than derived, because the default is a slug of the translated
     * title and that would put the panel's own URL in whatever language the first
     * request happened to be in.
     */
    public $slug = 'redirects';

    public $priority = 890;

    public $icon = 'fas-signs-post';

    public $orderBy = 'path';

    public $showIndexGroups = false;

    public $title = 'leap::redirects.title';

    /**
     * A redirect map arrives as a spreadsheet — an export from Search Console, or a
     * list the previous site's owner had lying about. Typing fifty rows by hand is
     * how a migration ends up with the map half finished.
     */
    public array $allowImport = [
        'columns' => ['path', 'destination', 'status'],
        'attributes' => ['active'],
    ];
}
