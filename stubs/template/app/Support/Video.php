<?php

namespace App\Support;

use NickDeKruijk\Leap\Classes\Video as LeapVideo;

/**
 * The video in a "video" section: YouTube or Vimeo, its poster, its embed URL.
 *
 * The behaviour lives in the package (NickDeKruijk\Leap\Classes\Video) so bugfixes arrive
 * via composer update — and there is a fair amount of hard-won knowledge in there: that
 * YouTube only has a maxresdefault poster for videos uploaded in HD, that Vimeo will only
 * tell you where its poster is through oEmbed, and that Safari refuses to autoplay a
 * cross-origin YouTube frame with sound no matter what you try. None of that is worth
 * rediscovering per project.
 *
 * This thin wrapper keeps the stable App\Support\Video name the section uses. Override a
 * method here if a project needs to differ.
 */
class Video extends LeapVideo {}
