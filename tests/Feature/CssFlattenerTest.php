<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Classes\CssFlattener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CssFlattenerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function cases(): array
    {
        return [
            'plain rule stays as it is' => [
                '.a { color: red; }',
                ".a{color: red}\n",
            ],
            'ampersand pseudo class' => [
                '.a { color: red; &:hover { color: blue; } }',
                ".a{color: red}\n.a:hover{color: blue}\n",
            ],
            'ampersand compound class' => [
                '.a { &.b { color: blue; } }',
                ".a.b{color: blue}\n",
            ],
            'ampersand twice' => [
                '.a { & + & { margin: 0; } }',
                ".a + .a{margin: 0}\n",
            ],
            'ampersand after another selector' => [
                '.b { .x & .y { color: red; } }',
                ".x .b .y{color: red}\n",
            ],
            'relaxed nesting without ampersand' => [
                'article { h3 { margin: 0; } }',
                "article h3{margin: 0}\n",
            ],
            'leading combinators' => [
                '.a { > * { margin: 0; } ~ .b { top: 0; } + .c { left: 0; } }',
                ".a > *{margin: 0}\n.a ~ .b{top: 0}\n.a + .c{left: 0}\n",
            ],
            // In nesting "&" is :is(parent list): each branch weighs as much as the
            // heaviest one, which a list written out per parent would not.
            'selector lists on both sides' => [
                '.a, .b { .c, &:hover { color: red; } }',
                ":is(.a, .b) .c,:is(.a, .b):hover{color: red}\n",
            ],
            'parent list keeps the specificity of its heaviest branch' => [
                '.a, #b { .c { color: red; } }',
                ":is(.a, #b) .c{color: red}\n",
            ],
            'each ampersand matches any parent of the list' => [
                '.a, .b { & + & { margin: 0; } }',
                ":is(.a, .b) + :is(.a, .b){margin: 0}\n",
            ],
            'parent list of a parent list' => [
                '.a, .b { .c, .d { &:hover { color: red; } } }',
                ":is(:is(.a, .b) .c, :is(.a, .b) .d):hover{color: red}\n",
            ],
            'declarations of a parent list stay a plain list' => [
                '.a, .b { color: red; }',
                ".a,.b{color: red}\n",
            ],
            'ampersand in the middle of a complex parent' => [
                '.a .b { .x & .y { color: red; } }',
                ".x :is(.a .b) .y{color: red}\n",
            ],
            'ampersand twice on a complex parent' => [
                '.a .b { & + & { margin: 0; } }',
                ":is(.a .b) + :is(.a .b){margin: 0}\n",
            ],
            'ampersand glued behind a name' => [
                'a { .x& { color: red; } }',
                ".x:is(a){color: red}\n",
            ],
            'three levels deep' => [
                '.a { .b { .c { color: red; } } }',
                ".a .b .c{color: red}\n",
            ],
            'nested media is moved outside the rule' => [
                '.a { color: red; @media (max-width: 512px) { color: blue; &:hover { color: green; } } }',
                ".a{color: red}\n@media (max-width: 512px){.a{color: blue}\n.a:hover{color: green}\n}\n",
            ],
            'nested container query' => [
                '.a { @container (min-width: 400px) { .b { display: flex; } } }',
                "@container (min-width: 400px){.a .b{display: flex}\n}\n",
            ],
            'top level media keeps its rules' => [
                '@media (max-width: 768px) { :root { --x: 1px; } .a { &:hover { top: 0; } } }',
                "@media (max-width: 768px){:root{--x: 1px}\n.a:hover{top: 0}\n}\n",
            ],
            'declarations after a nested rule keep their place' => [
                '.a { color: red; &:hover { color: blue; } cursor: pointer; }',
                ".a{color: red}\n.a:hover{color: blue}\n.a{cursor: pointer}\n",
            ],
            'keyframes are left alone' => [
                '@keyframes spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }',
                "@keyframes spin{from{transform: rotate(0)}\nto{transform: rotate(360deg)}\n}\n",
            ],
            'font face descriptors are left alone' => [
                "@font-face { font-family: 'X'; src: url(x.woff2); }",
                "@font-face{font-family: 'X';src: url(x.woff2);}\n",
            ],
            'braces, semicolons and ampersands inside strings' => [
                '.a { &::before { content: "{ & ; }"; } }',
                ".a::before{content: \"{ & ; }\"}\n",
            ],
            'semicolon inside url()' => [
                '.a { background: url(data:image/svg+xml;utf8,x); }',
                ".a{background: url(data:image/svg+xml;utf8,x)}\n",
            ],
            'ampersand inside an attribute value is not the parent' => [
                '.a { [href*="&"] { color: red; } }',
                ".a [href*=\"&\"]{color: red}\n",
            ],
            'comma inside a pseudo class does not split the selector' => [
                '.a { :is(.b, .c) &, &:not(.d, .e) { color: red; } }',
                ":is(.b, .c) .a,.a:not(.d, .e){color: red}\n",
            ],
            'comments are dropped, also with a brace in them' => [
                '.a { /* { not a block } */ /* Active tab */ .b & { color: red; } }',
                ".b .a{color: red}\n",
            ],
            'license comment at the top is kept' => [
                "/*! license */\n.a { color: red; }",
                "/*! license */\n.a{color: red}\n",
            ],
            'a closing brace too many loses nothing after it' => [
                '.a { color: red; } } .b { color: blue; } .c { top: 0; }',
                ".a{color: red}\n.b{color: blue}\n.c{top: 0}\n",
            ],
            'comment between two tokens leaves a space' => [
                '.a { margin: 1px/* c */2px; }',
                ".a{margin: 1px 2px}\n",
            ],
            'comment inside a compound leaves nothing' => [
                '.a/**/.b { top: 0; }',
                ".a.b{top: 0}\n",
            ],
            'block in a custom property is its value' => [
                '.a { --json: { "a": 1 }; color: red; }',
                ".a{--json: { \"a\": 1 };color: red}\n",
            ],
            'empty custom property keeps a space' => [
                ':root { --t: ; --u: 1px; }',
                ":root{--t: ;--u: 1px}\n",
            ],
            'moz document inside a rule keeps the parent' => [
                '.a { @-moz-document url-prefix() { color: red; } }',
                "@-moz-document url-prefix(){.a{color: red}\n}\n",
            ],
            'scope inside a rule gets no parent' => [
                '.a { @scope (.b) { img { top: 0; } } }',
                "@scope (.b){img{top: 0}\n}\n",
            ],
            'whitespace inside a string in a selector is kept' => [
                '.a { [data-x="a  b"]   & { color: red; } }',
                "[data-x=\"a  b\"] .a{color: red}\n",
            ],
            'escaped ampersand is part of the name' => [
                '.a { .b\\& { color: red; } }',
                ".a .b\\&{color: red}\n",
            ],
            'escaped comma does not split the selector' => [
                '.a { .b\\,c { color: red; } }',
                ".a .b\\,c{color: red}\n",
            ],
            // The broken declaration stays broken, as in a browser. What counts is
            // that the rules after it are still flattened.
            'unclosed string ends at the end of its line' => [
                ".a { content: \"oops;\n color: red; }\n.b { .c { top: 0; } }",
                ".a{content: \"oops;\n color: red}\n.b .c{top: 0}\n",
            ],
            'quote in an unquoted url' => [
                ".a { background: url(it's.png); } .b { .c { top: 0; } }",
                ".a{background: url(it's.png)}\n.b .c{top: 0}\n",
            ],
            'multi line selector list is squashed' => [
                ".a {\n    .b,\n    .c\n    .d {\n        color: red;\n    }\n}",
                ".a .b,.a .c .d{color: red}\n",
            ],
        ];
    }

    #[DataProvider('cases')]
    public function test_flatten(string $css, string $expected): void
    {
        $this->assertSame($expected, CssFlattener::flatten($css));
    }

    public function test_flattening_plain_css_again_changes_nothing(): void
    {
        $flat = CssFlattener::flatten((string) file_get_contents(__DIR__.'/../../resources/css/leap.css'));

        $this->assertSame($flat, CssFlattener::flatten($flat));
    }
}
