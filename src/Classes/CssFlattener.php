<?php

namespace NickDeKruijk\Leap\Classes;

/**
 * Rewrites native CSS nesting into plain, unnested rules.
 *
 * The panel CSS is written with nesting, which Safari only understands from 16.5
 * (and without a leading "&" only from 17.2). An older browser drops every nested
 * rule, plus the declaration after it, and the panel comes out unstyled. So the
 * nesting is resolved on the server: "&" is replaced by the parent, a nested
 * selector without one is put behind its parent, and a conditional at-rule inside
 * a rule is moved outside it with the parent selector inside.
 *
 * The result means what the nesting meant, not just what it looks like. In nesting
 * "&" stands for :is(parent list): every branch of a selector list weighs as much
 * as its heaviest one, and each "&" matches any of them. So the parent is written
 * out as text only where that is the same thing (one parent, with the "&" where
 * the parent's last compound may simply continue), and as :is() everywhere else.
 * :is() is understood from Safari 14.
 *
 * Declarations keep their place among nested rules: those after a nested rule go
 * into a second rule for the parent, so the cascade is the same as with nesting.
 * Comments are dropped, apart from top level "/*!" ones (licenses). @keyframes and
 *
 * @scope are not conditional group rules and get no parent selector: write them
 * top level.
 */
final class CssFlattener
{
    public static function flatten(string $css): string
    {
        $offset = 0;
        $nodes = self::parse($css, $offset);

        return self::emit($nodes, null);
    }

    /**
     * Parse until the end of the input or the "}" closing the current block.
     *
     * @return list<array{type: string, text?: string, prelude?: string, children?: list<array<string, mixed>>}>
     */
    private static function parse(string $css, int &$i, int $level = 0): array
    {
        $nodes = [];
        $buffer = '';
        $parens = 0;
        $length = strlen($css);

        while ($i < $length) {
            $char = $css[$i];

            if ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                $end = $end === false ? $length : $end + 2;
                if ($level === 0 && trim($buffer) === '' && ($css[$i + 2] ?? '') === '!') {
                    $nodes[] = ['type' => 'raw', 'text' => substr($css, $i, $end - $i)];
                } elseif (self::isNameChar(substr($buffer, -1)) && self::isNameChar($css[$end] ?? '')) {
                    // A comment separates two tokens: without it "1px/**/2px" would
                    // read as one. Between other characters it leaves nothing behind,
                    // so ".a/**/.b" stays one compound.
                    $buffer .= ' ';
                }
                $i = $end;

                continue;
            }

            if ($char === '"' || $char === "'") {
                // An unclosed string ends at the end of its line, as it does in a
                // browser, instead of swallowing the rules after it.
                $start = $i++;
                while ($i < $length && $css[$i] !== $char && $css[$i] !== "\n") {
                    $i += $css[$i] === '\\' ? 2 : 1;
                }
                if ($i < $length && $css[$i] === $char) {
                    $i++;
                }
                $i = min($i, $length);
                $buffer .= substr($css, $start, $i - $start);

                continue;
            }

            if ($char === '\\') {
                $buffer .= substr($css, $i, 2);
                $i += 2;

                continue;
            }

            $i++;

            if ($char === '(' && preg_match('/(?<![\w-])url$/i', $buffer) && ! preg_match('/\G\s*["\']/', $css, $m, 0, $i)) {
                // An unquoted url() is taken as it is: a quote or a brace in it is
                // part of the address.
                $start = $i;
                while ($i < $length && $css[$i] !== ')') {
                    $i += $css[$i] === '\\' ? 2 : 1;
                }
                $i = min($i + 1, $length);
                $buffer .= '('.substr($css, $start, $i - $start);

                continue;
            }

            if ($parens === 0 && $char === '{' && preg_match('/^\s*--[\w-]+\s*:/', $buffer)) {
                // The value of a custom property is opaque: a block in it is part
                // of the value, not a nested rule.
                $start = $i - 1;
                $depth = 1;
                while ($i < $length && $depth > 0) {
                    $depth += match ($css[$i]) {
                        '{' => 1,
                        '}' => -1,
                        default => 0,
                    };
                    $i += $css[$i] === '\\' ? 2 : 1;
                }
                $i = min($i, $length);
                $buffer .= substr($css, $start, $i - $start);

                continue;
            }

            if ($char === '(') {
                $parens++;
            } elseif ($char === ')') {
                $parens = max(0, $parens - 1);
            } elseif ($parens === 0 && $char === '{') {
                $prelude = self::squash($buffer);
                $children = self::parse($css, $i, $level + 1);
                $nodes[] = str_starts_with($prelude, '@')
                    ? ['type' => 'at', 'prelude' => $prelude, 'children' => $children]
                    : ['type' => 'rule', 'prelude' => $prelude, 'children' => $children];
                $buffer = '';

                continue;
            } elseif ($parens === 0 && ($char === ';' || $char === '}')) {
                if ($char === '}' && $level === 0) {
                    // A "}" that closes nothing. Only what led up to it is lost, not
                    // the rest of the file.
                    $buffer = '';

                    continue;
                }
                if (trim($buffer) !== '') {
                    $nodes[] = ['type' => 'decl', 'text' => self::declaration($buffer)];
                }
                $buffer = '';
                if ($char === '}') {
                    return $nodes;
                }

                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $nodes[] = ['type' => 'decl', 'text' => self::declaration($buffer)];
        }

        return $nodes;
    }

    /**
     * A declaration without the whitespace around it. A custom property keeps an
     * empty value as one space: "--x: ;" is a valid, empty value, "--x:;" is not
     * to every browser.
     */
    private static function declaration(string $buffer): string
    {
        $declaration = trim($buffer);

        return preg_match('/^--[\w-]+\s*:$/', $declaration) ? $declaration.' ' : $declaration;
    }

    private static function isNameChar(string $char): bool
    {
        return $char !== '' && (ctype_alnum($char) || $char === '_' || $char === '-' || $char === '%' || ord($char) >= 0x80);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<string>|null  $parents  the selectors of the enclosing rule, null outside any rule
     */
    private static function emit(array $nodes, ?array $parents): string
    {
        $out = '';
        $declarations = [];

        foreach ($nodes as $node) {
            if ($node['type'] === 'decl') {
                $declarations[] = $node['text'];

                continue;
            }

            $out .= self::declarations($declarations, $parents);
            $declarations = [];

            if ($node['type'] === 'raw') {
                $out .= $node['text']."\n";
            } elseif ($node['type'] === 'at') {
                // Only a conditional group rule wraps the parent's selector: the
                // frames of @keyframes or the descriptors of @font-face are not
                // selectors, so they never get one. Neither do the rules in a
                // @scope, which are relative to the scope and not to the parent.
                $conditional = (bool) preg_match('/^@(media|supports|container|layer|starting-style|(-moz-)?document)\b/i', $node['prelude']);
                $inner = self::emit($node['children'], $conditional ? $parents : null);
                if ($inner !== '' || $parents === null) {
                    $out .= $node['prelude'].'{'.$inner.'}'."\n";
                }
            } else {
                $out .= self::emit($node['children'], self::resolve(self::split($node['prelude']), $parents));
            }
        }

        return $out.self::declarations($declarations, $parents);
    }

    /**
     * A run of declarations: a rule for the parent, or bare inside an at-rule
     * like @font-face.
     *
     * @param  list<string>  $declarations
     * @param  list<string>|null  $parents
     */
    private static function declarations(array $declarations, ?array $parents): string
    {
        if (! $declarations) {
            return '';
        }

        return $parents === null
            ? implode(';', $declarations).';'
            : implode(',', $parents).'{'.implode(';', $declarations).'}'."\n";
    }

    /**
     * Give each nested selector its parent.
     *
     * @param  list<string>  $children
     * @param  list<string>|null  $parents
     * @return list<string>
     */
    private static function resolve(array $children, ?array $parents): array
    {
        if ($parents === null) {
            return $children;
        }

        $single = count($parents) === 1 ? $parents[0] : null;
        $is = ':is('.implode(', ', $parents).')';
        $compound = $single !== null && self::isCompound($single);

        $selectors = [];
        foreach ($children as $child) {
            // "&" marked, so one inside [] or a string is not counted
            $marked = self::replaceAmpersand($child, "\1");
            $count = substr_count($marked, "\1");

            if ($count === 0) {
                // ":is(.a .b) .c" and ".a .b .c" are the same thing
                $selectors[] = ($single ?? $is).' '.$child;

                continue;
            }

            // Text is the same as :is() in two cases. One "&" up front: the last
            // compound of the parent just continues (".a .b" + "&:hover"). Or a
            // parent that is one compound, with every "&" at the start of a
            // compound of its own (".x & + &"). Not ".x&": the parent would be
            // glued to the name before it.
            $textual = $single !== null && (
                ($count === 1 && str_starts_with($marked, "\1"))
                || ($compound && ! preg_match('/[^\s>+~(,]\x01/', $marked))
            );

            $selectors[] = self::replaceAmpersand($child, $textual ? $single : $is);
        }

        return $selectors;
    }

    /**
     * Whether a selector is one compound: no combinator outside (), [] or a string.
     */
    private static function isCompound(string $selector): bool
    {
        $depth = 0;
        $quote = null;
        $length = strlen($selector);

        for ($i = 0; $i < $length; $i++) {
            $char = $selector[$i];
            if ($char === '\\') {
                $i++;
            } elseif ($quote !== null) {
                $quote = $char === $quote ? null : $quote;
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                $depth--;
            } elseif ($depth === 0 && ($char === ' ' || $char === '>' || $char === '+' || $char === '~')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Split a selector list on the commas that are not inside (), [] or a string.
     *
     * @return list<string>
     */
    private static function split(string $selectors): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quote = null;
        $length = strlen($selectors);

        for ($i = 0; $i < $length; $i++) {
            $char = $selectors[$i];
            if ($char === '\\') {
                // An escaped character is itself, also a comma
                $current .= $char.($selectors[++$i] ?? '');

                continue;
            }
            if ($quote !== null) {
                $quote = $char === $quote ? null : $quote;
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }
            $current .= $char;
        }
        $parts[] = trim($current);

        return array_values(array_filter($parts, fn ($part) => $part !== ''));
    }

    /**
     * Replace "&" with the parent selector, but not an "&" that is escaped or
     * inside [] or a string.
     */
    private static function replaceAmpersand(string $selector, string $parent): string
    {
        $out = '';
        $brackets = 0;
        $quote = null;
        $length = strlen($selector);

        for ($i = 0; $i < $length; $i++) {
            $char = $selector[$i];
            if ($char === '\\') {
                $out .= $char.($selector[++$i] ?? '');

                continue;
            }
            if ($quote !== null) {
                $quote = $char === $quote ? null : $quote;
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '[') {
                $brackets++;
            } elseif ($char === ']') {
                $brackets--;
            } elseif ($char === '&' && $brackets === 0) {
                $out .= $parent;

                continue;
            }
            $out .= $char;
        }

        return $out;
    }

    /**
     * Collapse the whitespace in a prelude to single spaces, except inside a string.
     */
    private static function squash(string $prelude): string
    {
        return trim((string) preg_replace_callback(
            '/("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')|\s+/s',
            fn (array $match) => ($match[1] ?? '') !== '' ? $match[1] : ' ',
            $prelude
        ));
    }
}
