<?php

namespace NickDeKruijk\Leap\Classes;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Lets an iframe keep its src only when it points at a host on the list.
 *
 * The sanitizer's own host list covers every image and video as well, and a site
 * has images from anywhere. An iframe is the one element where the host is the
 * whole question: it is a page inside the page.
 *
 * @internal Part of RichtextSanitizer; not part of the public API.
 */
final class IframeHostSanitizer implements AttributeSanitizerInterface
{
    /**
     * @param  list<string>  $hosts
     */
    public function __construct(private array $hosts) {}

    public function getSupportedElements(): array
    {
        return ['iframe'];
    }

    public function getSupportedAttributes(): array
    {
        return ['src'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        // A relative src has no host and is a page of the site itself in a frame,
        // which nothing here has a reason to allow.
        $host = parse_url(trim($value), PHP_URL_HOST);

        return is_string($host) && in_array(strtolower($host), array_map(strtolower(...), $this->hosts), true) ? $value : null;
    }
}
