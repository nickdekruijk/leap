<?php

namespace App\Traits;

use NickDeKruijk\Leap\Traits\HasSlug as LeapHasSlug;

/**
 * Per-locale, sibling-unique slug generation for the frontend template.
 *
 * The behaviour lives in the package (NickDeKruijk\Leap\Traits\HasSlug) so
 * bugfixes arrive via composer update; this thin wrapper keeps the stable
 * App\Traits\HasSlug name the models use. Add your own overrides here if needed.
 */
trait HasSlug
{
    use LeapHasSlug;
}
