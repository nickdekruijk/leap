<?php

namespace NickDeKruijk\Leap\Classes;

use Dom\Element;
use Dom\Node;
use RuntimeException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Parser\NativeParser;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\UrlAttributeSanitizer;

/**
 * Filters the HTML of a rich-text field on the server.
 *
 * TinyMCE cleans what is typed into it, but that happens in the browser, and a
 * request sent straight to the panel never passes through it. The panel and the
 * site share an origin and the site prints rich text as it is stored, so a script
 * saved by an editor runs for an administrator who looks at the page. Filtering
 * where the value is stored is the one place that covers the editor, the preview
 * and the site at once.
 *
 * Off unless leap.tinymce.sanitize is true, and it needs symfony/html-sanitizer
 * and PHP 8.4: the HTML is read with PHP's own HTML5 parser, which reads it the
 * way a browser does. That matters for filter(), which leaves a value alone when
 * nothing has to go, and so has to see everything a browser would see in it.
 */
final class RichtextSanitizer
{
    /**
     * What an iframe may carry besides its src.
     */
    private const IFRAME_ATTRIBUTES = ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'loading', 'class', 'style'];

    private ?HtmlSanitizer $sanitizer = null;

    /**
     * Whether rich text is filtered when it is saved.
     */
    public static function enabled(): bool
    {
        return config('leap.tinymce.sanitize') === true;
    }

    /**
     * Why the filter cannot run here, or null when it can.
     */
    public static function unavailable(): ?string
    {
        if (! class_exists(HtmlSanitizer::class)) {
            return 'Filtering rich text needs symfony/html-sanitizer: composer require symfony/html-sanitizer';
        }

        if (PHP_VERSION_ID < 80400) {
            return 'Filtering rich text needs PHP 8.4 or later, for its HTML5 parser. This is PHP '.PHP_VERSION.'.';
        }

        return null;
    }

    /**
     * The value as it is stored: untouched when the filter changes nothing in it,
     * and the filtered HTML otherwise.
     *
     * The filter writes HTML its own way, with other entities and quotes than
     * TinyMCE, so running every value through it would change a record nobody
     * touched. Only a value that loses something, or in which an address is
     * written differently than a browser reads it, is replaced.
     */
    public function filter(string $html): string
    {
        $clean = $this->clean($html);
        $changes = $this->compare($html, $clean);

        return $changes['dropped'] === [] && $changes['rewritten'] === [] ? $html : $clean;
    }

    /**
     * The HTML with everything gone that the filter does not allow.
     */
    public function clean(string $html): string
    {
        $clean = $this->sanitizer()->sanitize($html);

        // An iframe from a host that is not on the list has lost its src by now,
        // and what is left of it is an empty box on the page. The filter wrote
        // this HTML itself, attribute values escaped, so the pattern can rely on it.
        return (string) preg_replace('~<iframe(?:\s+(?!src=)[a-z-]+(?:="[^"]*")?)*\s*></iframe>~', '', $clean);
    }

    /**
     * What the filter takes out of this HTML, and how often: 'script' => 1 for an
     * element, 'img[onerror]' => 2 for an attribute. Empty when nothing has to go.
     *
     * HTML comments are not counted. They do nothing in a page, so they are no
     * reason to rewrite a value, but they do not survive a value that is rewritten.
     *
     * @return array<string, int>
     */
    public function dropped(string $html): array
    {
        return $this->changes($html)['dropped'];
    }

    /**
     * What the filter does to this HTML: what goes, and which attributes stay but
     * come out with another value, such as "mailto: a@b.nl" without its space.
     *
     * @return array{dropped: array<string, int>, rewritten: array<string, int>}
     */
    public function changes(string $html): array
    {
        return $this->compare($html, $this->clean($html));
    }

    /**
     * What is in the first HTML and not in the second.
     *
     * Both are read with the parser the filter itself uses, so anything the filter
     * found and removed is found here as well. An address is compared the way a
     * browser reads it: "/files/a b.pdf" and "/files/a%20b.pdf" lead to the same
     * file, so the filter writing the second for the first has changed nothing.
     *
     * @return array{dropped: array<string, int>, rewritten: array<string, int>}
     */
    private function compare(string $before, string $after): array
    {
        $was = $this->inventory($before, true);
        $is = $this->inventory($after, false);
        $dropped = [];
        $rewritten = [];

        foreach ($was['elements'] as $tag => $count) {
            if ($count > ($is['elements'][$tag] ?? 0)) {
                $dropped[$tag] = $count - ($is['elements'][$tag] ?? 0);
            }
        }

        foreach ($was['attributes'] as $name => $values) {
            // Fewer of this attribute than before: those are gone.
            $gone = max(0, array_sum($values) - array_sum($is['attributes'][$name] ?? []));
            // Values that are not there any more: gone, or there with another value.
            $changed = 0;
            foreach ($values as $value => $count) {
                $changed += max(0, $count - ($is['attributes'][$name][$value] ?? 0));
            }

            if ($gone > 0) {
                $dropped[$name] = $gone;
            }
            if ($changed > $gone) {
                $rewritten[$name] = $changed - $gone;
            }
        }

        ksort($dropped);
        ksort($rewritten);

        return ['dropped' => $dropped, 'rewritten' => $rewritten];
    }

    /**
     * Every element, and every attribute with its values, counted.
     *
     * @param  bool  $asBrowser  Read an address the way a browser does. For the HTML
     *                           as it came in: what the filter wrote already is.
     * @return array{elements: array<string, int>, attributes: array<string, array<string, int>>}
     */
    private function inventory(string $html, bool $asBrowser): array
    {
        $counts = ['elements' => [], 'attributes' => []];
        $root = trim($html) === '' ? null : (new NativeParser)->parse($html);

        if ($root === null) {
            return $counts;
        }

        $walk = function (Node $node) use (&$walk, &$counts, $asBrowser): void {
            foreach ($node->childNodes as $child) {
                if (! $child instanceof Element) {
                    continue;
                }
                $tag = strtolower($child->localName);
                $counts['elements'][$tag] = ($counts['elements'][$tag] ?? 0) + 1;
                foreach ($child->attributes as $attribute) {
                    $name = strtolower($attribute->name);
                    $value = $asBrowser && in_array($name, AddressSanitizer::ATTRIBUTES, true)
                        ? AddressSanitizer::asBrowserReadsIt($attribute->value)
                        : $attribute->value;
                    $counts['attributes'][$tag.'['.$name.']'][$value] = ($counts['attributes'][$tag.'['.$name.']'][$value] ?? 0) + 1;
                }
                // What a template holds is not among its children, and does not
                // have to be: the template itself is counted, and it always goes.
                $walk($child);
            }
        };
        $walk($root);

        return $counts;
    }

    private function sanitizer(): HtmlSanitizer
    {
        if ($reason = self::unavailable()) {
            throw new RuntimeException($reason);
        }

        return $this->sanitizer ??= new HtmlSanitizer($this->config(), new NativeParser);
    }

    /**
     * What TinyMCE writes with leap's default options, plus what the site adds.
     */
    private function config(): HtmlSanitizerConfig
    {
        $config = (new HtmlSanitizerConfig)
            // The 20,000 bytes the sanitizer stops at by default would cut a long page short.
            ->withMaxInputLength(-1)
            ->allowSafeElements()
            // Leap stores its own links and images without a host: /contact, /storage/photo.jpg
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            // Alignment, widths and classes are how TinyMCE lays a text out.
            ->allowAttribute('class', '*')
            ->allowAttribute('style', '*')
            ->allowAttribute('border', 'table')
            // Inert in a page, and so not something this can vouch for.
            ->dropElement('template')
            ->allowElement('iframe', self::IFRAME_ATTRIBUTES)
            ->withAttributeSanitizer(new IframeHostSanitizer(array_values(array_filter((array) config('leap.tinymce.sanitize_iframe_hosts'), is_string(...)))));

        // The sanitizer's own check of an address, with a browser's reading of it
        // in front: see AddressSanitizer.
        foreach ($config->getAttributeSanitizers() as $sanitizer) {
            if ($sanitizer instanceof UrlAttributeSanitizer) {
                $config = $config->withoutAttributeSanitizer($sanitizer)->withAttributeSanitizer(new AddressSanitizer($sanitizer));
            }
        }

        foreach ((array) config('leap.tinymce.sanitize_allow') as $element => $attributes) {
            $attributes = array_values(array_filter((array) $attributes, is_string(...)));

            if ($element === '*') {
                foreach ($attributes as $attribute) {
                    $config = $config->allowAttribute($attribute, '*');
                }
            } elseif (is_string($element) && isset($config->getAllowedElements()[$element])) {
                foreach ($attributes as $attribute) {
                    $config = $config->allowAttribute($attribute, $element);
                }
            } elseif (is_string($element)) {
                $config = $config->allowElement($element, $attributes);
            }
        }

        return $config;
    }
}
