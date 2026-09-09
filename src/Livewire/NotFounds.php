<?php

namespace NickDeKruijk\Leap\Livewire;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Models\NotFound;
use NickDeKruijk\Leap\Resource;

/**
 * The worklist: addresses that were asked for and are not there.
 *
 * Filling in the new address is what finishes a row. The rule is created and this one
 * is gone, so the list only ever holds what still needs an answer.
 */
class NotFounds extends Resource
{
    public function attributes()
    {
        return [
            Attribute::make('path')->index(1)->searchable()->readonly()->label(__('leap::redirects.path')),
            // Required, because the button says "create a redirect" and there is nothing
            // else here to save: pressing it with this empty would quietly do nothing.
            Attribute::make('destination')->index(2)->searchable()->required()
                ->label(__('leap::redirects.destination'))->placeholder(__('leap::redirects.destination_placeholder')),
            Attribute::make('hits')->index(3)->readonly()->label(__('leap::redirects.hits')),
            Attribute::make('last_used_at')->index(4)->readonly()->label(__('leap::redirects.last_used_at')),
            // The half that says where to go and fix the link, rather than only
            // papering over it with a redirect.
            Attribute::make('referer_list')->accessor('referers')->textarea()->label(__('leap::redirects.referers')),
            // Off unless the project asks for them, so usually empty. Together they
            // answer whether a dead address still has people on it or only a crawler.
            Attribute::make('user_agent_list')->accessor('user_agents')->textarea()->label(__('leap::redirects.user_agents')),
            Attribute::make('ip_address_list')->accessor('ip_addresses')->textarea()->label(__('leap::redirects.ip_addresses')),
        ];
    }

    public $model = NotFound::class;

    /**
     * Saving here creates the rule and takes the row away, which is not what "Save"
     * leads anyone to expect.
     */
    public $saveLabel = 'leap::redirects.make_redirect';

    /**
     * A second row for the same address is a duplicate the unique path would refuse.
     */
    public $allowClone = false;

    /**
     * No create: a missing address arrives by being asked for, and one typed in by hand
     * is a redirect, which is the other screen.
     */
    protected $default_permissions = [
        'read' => true,
        'update' => true,
        'delete' => true,
    ];

    /**
     * Fixed rather than derived, because the default is a slug of the translated title.
     */
    public $slug = 'not-founds';

    public $priority = 891;

    public $icon = 'fas-link-slash';

    /**
     * Most asked for first: that is the one worth answering, and a one-off probe sinks
     * to the bottom on its own.
     */
    public $orderBy = 'hits';

    public $orderDesc = true;

    public $showIndexGroups = false;

    public $title = 'leap::redirects.not_founds';
}
