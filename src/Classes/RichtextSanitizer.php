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
 * Off unless leap.tinymce.sanitize is on, and it needs symfony/html-sanitizer
 * and PHP 8.4, for PHP's own HTML5 parser.
 *
 * What is stored is always what the filter wrote, never what came in. Keeping a
 * value as it was sent when "nothing has to go" sounds harmless and is not: a
 * parser sets markup aside that a browser reads on the site after all. A stray
 * <body onload> lands on the real body, a tag left open at the end is finished
 * by the markup that follows it, a loose <td> becomes a cell inside a table.
 * None of that is in the tree, so none of it would count as something to remove.
 * The HTML the filter writes is balanced and escaped, and has none of it.
 */
final class RichtextSanitizer
{
    /**
     * What an iframe may carry besides its src.
     */
    private const IFRAME_ATTRIBUTES = ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'frameborder', 'referrerpolicy', 'loading'];

    private ?HtmlSanitizer $sanitizer = null;

    /**
     * Whether rich text is filtered when it is saved.
     */
    public static function enabled(): bool
    {
        // Not === true: an env value arrives as "1" or "on" as easily as true, and
        // a filter that stays off over that would do so without a word.
        return filter_var(config('leap.tinymce.sanitize'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Why the filter cannot run here, or null when it can.
     */
    public static function unavailable(): ?string
    {
        if (! class_exists(HtmlSanitizer::class)) {
            return 'Filtering rich text needs symfony/html-sanitizer: composer require symfony/html-sanitizer';
        }

        if (! class_exists(NativeParser::class)) {
            return 'Filtering rich text needs symfony/html-sanitizer 7.4 or later: composer require symfony/html-sanitizer:^7.4';
        }

        if (PHP_VERSION_ID < 80400) {
            return 'Filtering rich text needs PHP 8.4 or later, for its HTML5 parser. This is PHP '.PHP_VERSION.'.';
        }

        return null;
    }

    /**
     * The value as it is stored: the HTML the filter wrote.
     *
     * Always that, also when nothing had to go. It is written the filter's way,
     * with other entities and quotes than TinyMCE uses, so the first save of a
     * record changes how its rich text is written, and not what it says.
     */
    public function filter(string $html): string
    {
        return $this->clean($html);
    }

    /**
     * A rich-text value as it comes out of a form or an import: one text, or one
     * per locale for a translatable field. Anything else is handed back as it came.
     */
    public function filterValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->filterValue(...), $value);
        }

        return is_string($value) && $value !== '' ? $this->filter($value) : $value;
    }

    /**
     * The HTML with everything gone that the filter does not allow.
     */
    public function clean(string $html): string
    {
        $clean = $this->sanitizer()->sanitize($html);

        // The filter wrote this HTML itself, with every < and > in a text or an
        // attribute value escaped, so the two patterns below can rely on it.
        //
        // What is inside an iframe is text a browser never shows, and never
        // decodes either: the sanitizer escapes it, the next save reads those
        // entities as text and escapes them again, and so on with every save.
        $clean = (string) preg_replace('~(<iframe(?:\s[^<>]*)?>)[^<]*</iframe>~', '$1</iframe>', $clean);

        // An iframe from a host that is not on the list has lost its src by now,
        // and what is left of it is an empty box on the page.
        $clean = (string) preg_replace('~<iframe(?:\s+(?!src=)[a-z-]+(?:="[^"]*")?)*\s*></iframe>~', '', $clean);

        return $this->readable($clean);
    }

    /**
     * The four characters the sanitizer writes as a code without HTML needing it:
     *
     * @ + = and the backtick, back as themselves.
     *
     * In a page nobody sees the difference. Where a site uses rich text as plain
     * text it shows: strip_tags() for a meta description or a feed leaves
     * "info&#64;example.com" standing, and a search for the address finds nothing.
     * With every value stored the filter's way, that would be every value.
     *
     * None of the four can open or close a tag, an attribute or a value: the
     * sanitizer writes every value between double quotes, and both quotes stay a
     * code, as < > and & do. It also writes every & of a text as &amp; first, so
     * a "&#64;" in what it wrote is always an @ it encoded, never text that reads
     * "&#64;". They are put back in text and in attribute values only. A name is
     * left as the sanitizer wrote it, so that no "@click" can come out of this.
     */
    private function readable(string $clean): string
    {
        $decode = fn (string $text): string => str_replace(['&#64;', '&#43;', '&#61;', '&#96;'], ['@', '+', '=', '`'], $text);

        // The sanitizer puts a space behind every value that holds a backtick, for
        // what Internet Explorer 8 made of one. Stored that way the value grows by
        // a space with every save, so the one it added comes off again.
        $value = fn (array $match): string => $decode(
            str_contains($match[0], '&#96;') && str_ends_with($match[0], ' "') ? substr($match[0], 0, -2).'"' : $match[0]
        );

        // What the sanitizer wrote has no < or > outside a tag and no " outside the
        // quotes around a value, so a tag and the values in it can be told by those.
        return (string) preg_replace_callback('~<[^<>]*>|[^<]+~', function (array $match) use ($decode, $value): string {
            return $match[0][0] === '<'
                ? (string) preg_replace_callback('~"[^"]*"~', $value, $match[0])
                : $decode($match[0]);
        }, $clean);
    }

    /**
     * What the filter takes out of this HTML, and how often: 'script' => 1 for an
     * element, 'img[onerror]' => 2 for an attribute. Empty when nothing has to go.
     *
     * @return array<string, int>
     */
    public function dropped(string $html): array
    {
        return $this->changes($html)['dropped'];
    }

    /**
     * What the filter does to this HTML, for a report. Nothing is decided by it:
     * filter() stores its own HTML either way.
     *
     * - dropped: what goes.
     * - rewritten: attributes that stay with another value, such as
     *   "mailto: a@b.nl" without its space.
     * - unclosed: whether something is left open at the end, a tag or a comment
     *   that the markup after the value would finish on the page.
     *
     * It reports what a parser can see. A loose <td onmouseover> outside a table
     * is set aside by the parser and is not in here, though filter() removes it.
     * HTML comments are not counted either, and do not survive filter().
     *
     * @return array{dropped: array<string, int>, rewritten: array<string, int>, unclosed: bool}
     */
    public function changes(string $html): array
    {
        return $this->compare($html, $this->clean($html)) + ['unclosed' => $this->unclosed($html)];
    }

    /**
     * Whether the HTML leaves something open at its end.
     *
     * Told by what comes after it. A rule put behind the HTML is the last thing on
     * the top level when everything before it was closed, and ends up inside an
     * element, an attribute or a comment when something was not. A paragraph left
     * open is closed by the rule, as it would be by whatever follows on a page.
     */
    private function unclosed(string $html): bool
    {
        if (trim($html) === '') {
            return false;
        }

        $root = (new NativeParser)->parse($html.'<hr>');
        $last = $root instanceof Element ? $root->lastElementChild : null;

        return $last === null || strtolower($last->localName) !== 'hr';
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

        // A stray <body> or <html> tag adds its attributes to the body and html
        // element of the document, which on a page are the real ones. They are not
        // among the children counted below, so they are counted here.
        foreach (['body' => $root, 'html' => $root->ownerDocument?->documentElement] as $tag => $element) {
            foreach ($element instanceof Element ? $element->attributes : [] as $attribute) {
                $counts['attributes'][$tag.'['.strtolower($attribute->name).']'][$attribute->value] = 1;
            }
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
            // Inert in a page, and so not something this can vouch for.
            ->dropElement('template')
            // Obsolete, and its content is text that is never decoded, like that
            // of an iframe: see clean().
            ->dropElement('xmp');

        // Every element with its attributes in one map first, and handed to the
        // sanitizer element by element afterwards. Its allowAttribute() takes an
        // attribute away from every element it is not given, and its '*' only
        // reaches the elements allowed at that moment, so adding to it step by
        // step made the outcome depend on the order of the site's own list.
        $elements = $config->getAllowedElements();
        $elements['iframe'] = array_fill_keys(self::IFRAME_ATTRIBUTES, true);
        $elements['table']['border'] = true;
        // Alignment, widths and classes are how TinyMCE lays a text out.
        $everywhere = ['class' => true, 'style' => true];

        foreach ((array) config('leap.tinymce.sanitize_allow') as $element => $attributes) {
            $attributes = array_fill_keys(array_filter((array) $attributes, is_string(...)), true);

            if ($element === '*') {
                $everywhere += $attributes;
            } elseif (is_string($element)) {
                $elements[strtolower($element)] = ($elements[strtolower($element)] ?? []) + $attributes;
            }
        }

        foreach ($elements as $element => $attributes) {
            $config = $config->allowElement((string) $element, array_map(strval(...), array_keys($attributes + $everywhere)));
        }

        $config = $config->withAttributeSanitizer(new IframeHostSanitizer(array_values(array_filter((array) config('leap.tinymce.sanitize_iframe_hosts'), is_string(...)))));

        // The sanitizer's own check of an address, with a browser's reading of it
        // in front: see AddressSanitizer.
        foreach ($config->getAttributeSanitizers() as $sanitizer) {
            if ($sanitizer instanceof UrlAttributeSanitizer) {
                $config = $config->withoutAttributeSanitizer($sanitizer)->withAttributeSanitizer(new AddressSanitizer($sanitizer));
            }
        }

        return $config;
    }
}
