<?php

namespace NickDeKruijk\Leap\Classes;

use Dom\Element;
use Dom\Node;
use RuntimeException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Parser\NativeParser;

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
     * The value as it is stored: untouched when nothing in it has to go, and the
     * filtered HTML otherwise.
     *
     * The filter writes HTML its own way, with other entities and quotes than
     * TinyMCE, so running every value through it would change a record nobody
     * touched. Only a value that loses something is replaced.
     */
    public function filter(string $html): string
    {
        $clean = $this->clean($html);

        return $this->difference($html, $clean) === [] ? $html : $clean;
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
        return $this->difference($html, $this->clean($html));
    }

    /**
     * What is in the first HTML and not in the second, counted the same way.
     *
     * Both are read with the parser the filter itself uses, so anything the filter
     * found and removed is found here as well.
     *
     * @return array<string, int>
     */
    private function difference(string $before, string $after): array
    {
        $after = $this->inventory($after);
        $dropped = [];

        foreach ($this->inventory($before) as $key => $count) {
            if ($count > ($after[$key] ?? 0)) {
                // 'a[href]=javascript:x' is reported as 'a[href]'
                $label = str_contains($key, ']=') ? strstr($key, ']=', true).']' : $key;
                $dropped[$label] = ($dropped[$label] ?? 0) + $count - ($after[$key] ?? 0);
            }
        }

        ksort($dropped);

        return $dropped;
    }

    /**
     * Every element and every attribute with its value, counted.
     *
     * @return array<string, int>
     */
    private function inventory(string $html): array
    {
        $counts = [];
        $root = trim($html) === '' ? null : (new NativeParser)->parse($html);

        if ($root === null) {
            return $counts;
        }

        $walk = function (Node $node) use (&$walk, &$counts): void {
            foreach ($node->childNodes as $child) {
                if (! $child instanceof Element) {
                    continue;
                }
                $tag = strtolower($child->localName);
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
                foreach ($child->attributes as $attribute) {
                    $key = $tag.'['.strtolower($attribute->name).']='.$attribute->value;
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
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
