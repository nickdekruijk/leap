<?php

namespace NickDeKruijk\Leap\Classes;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Reads the address in a href or src the way a browser does, before the
 * sanitizer's own URL check decides about it.
 *
 * That check is strict about whitespace: "http://example.com " with a space
 * behind it is refused, and so is "mailto:a@b.nl?subject=Hello there". A browser
 * follows both, so a link that worked lost its href the first time the text was
 * filtered. This works on the value of the attribute as the parser read it, never
 * on the HTML, so the filter and the browser still read the same document.
 *
 * Nothing here decides that an address is allowed. It only takes away what a
 * browser ignores, and writes what a browser would encode, and then hands the
 * address to the check it wraps.
 *
 * @internal Part of RichtextSanitizer; not part of the public API.
 */
final class AddressSanitizer implements AttributeSanitizerInterface
{
    /**
     * The attributes that hold one address. A srcset holds a list with a syntax
     * of its own, and is left to the check as it is.
     */
    public const ATTRIBUTES = ['href', 'src'];

    private const NBSP = "\u{00A0}";

    public function __construct(private AttributeSanitizerInterface $urls) {}

    public function getSupportedElements(): ?array
    {
        return $this->urls->getSupportedElements();
    }

    public function getSupportedAttributes(): ?array
    {
        return $this->urls->getSupportedAttributes();
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        if (! in_array($attribute, self::ATTRIBUTES, true)) {
            return $this->urls->sanitizeAttribute($element, $attribute, $value, $config);
        }

        $value = self::asBrowserReadsIt(self::withoutSpaceAfterScheme($value));
        $start = self::pathStart($value);

        // The wrapped check refuses a no-break space wherever it is, encoded or
        // not. Behind the host it is a character in a file name like any other, so
        // there something else stands in for it while the check looks: a string
        // that is not in the address, and that the check passes on as it is. Each
        // one goes back the way it was written, %C2%A0 or %c2%a0.
        do {
            $marker = '__leap-'.bin2hex(random_bytes(6)).'__';
        } while (str_contains($value, $marker));

        $written = [];
        $tail = (string) preg_replace_callback('/%C2%A0/i', function (array $match) use (&$written, $marker): string {
            $written[] = $match[0];

            return $marker;
        }, substr($value, $start));

        $checked = $this->urls->sanitizeAttribute($element, $attribute, substr($value, 0, $start).$tail, $config);

        if ($checked === null) {
            return null;
        }

        return (string) preg_replace_callback('/'.preg_quote($marker, '/').'/', function () use (&$written): string {
            return array_shift($written) ?? '%C2%A0';
        }, $checked);
    }

    /**
     * The address as a browser reads it, which leads to the same place.
     *
     * A browser drops spaces and control characters around an address, and encodes
     * a space or a no-break space in what comes after the host. Nothing else: the
     * scheme and the host are left exactly as they are, since that is where an
     * address is decided, and a tab or a line break inside an address is left for
     * the check to refuse.
     *
     * RichtextSanitizer compares addresses through this too: one that only differs
     * from the filtered one by what is done here was not changed by the filter.
     */
    public static function asBrowserReadsIt(string $value): string
    {
        $value = trim($value, "\x00..\x20");
        $start = self::pathStart($value);

        return substr($value, 0, $start).str_replace([' ', self::NBSP], ['%20', '%C2%A0'], substr($value, $start));
    }

    /**
     * "mailto: info@example.com", with the space people type after the colon. A
     * browser keeps that space in the address, so this is a repair and not a
     * reading: the filter reports it as rewritten.
     */
    private static function withoutSpaceAfterScheme(string $value): string
    {
        return (string) preg_replace('#^([\x00-\x20]*(?:mailto|tel):) +#i', '$1', $value);
    }

    /**
     * Where the part of an address begins in which a space may be encoded: behind
     * the host of an address that has one, at the start of a relative address, and
     * behind the colon of a mailto or tel address. Any other scheme is left whole.
     */
    private static function pathStart(string $value): int
    {
        if (preg_match('#^(?:[a-z][a-z0-9+.\-]*:)?//#i', $value, $match)) {
            return strlen($match[0]) + strcspn($value, '/?#', strlen($match[0]));
        }

        if (preg_match('#^(?:mailto|tel):#i', $value, $match)) {
            return strlen($match[0]);
        }

        return preg_match('#^[a-z][a-z0-9+.\-]*:#i', $value) ? strlen($value) : 0;
    }
}
