<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use NickDeKruijk\Leap\Classes\CssFlattener;
use NickDeKruijk\Leap\Controllers\AssetController;
use NickDeKruijk\Leap\Tests\TestCase;

class AssetCssTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('leap.css');
        Cache::forget('leap.css.filemtime');
    }

    public function test_css_route_concatenates_configured_files_without_a_compiler(): void
    {
        $response = $this->get(route('leap.css'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/css; charset=UTF-8');

        // Content from each configured file must be present verbatim, proving the
        // files are concatenated as plain CSS rather than run through a compiler.
        $css = $response->getContent();
        $this->assertStringContainsString('.leap-nav-aside', $css);
        $this->assertStringContainsString('.leap-editor-open', $css);
        $this->assertStringContainsString('.leap-filemanager', $css);

        // No leftover Sass syntax should ever reach the response.
        $this->assertStringNotContainsString('$leap-', $css);
    }

    public function test_css_response_is_cached_until_a_file_changes(): void
    {
        $this->get(route('leap.css'))->assertOk();

        $this->assertNotNull(Cache::get('leap.css'));
        $this->assertSame(AssetController::cssVersion(), Cache::get('leap.css.filemtime'));
    }

    public function test_filemanager_row_background_override_excludes_selected_rows(): void
    {
        // filemanager.css blanks a row's TD background so the table's own bg shows
        // through, but must exclude .leap-index-row-selected — otherwise it ties in
        // specificity with leap.css's ".leap-index-row-selected TD" rule and (since
        // filemanager.css loads last) wins on source order, cancelling the selected
        // row's teal background while its "color: white" still applies, leaving
        // near-invisible white-on-transparent text.
        $css = $this->get(route('leap.css'))->getContent();

        $this->assertMatchesRegularExpression(
            '/\.leap-index-row:not\(\.leap-index-row-selected\) TD\{background-color:\s*transparent\b/',
            $css
        );
    }

    /**
     * CSS nesting only permits conditional at-rules (@media, @supports, @container, …)
     * inside a style rule. A nested @keyframes is invalid and dropped, so its name is
     * never defined and every animation referring to it silently does nothing — which
     * is exactly what happened to the AI spinner and the upload fade-out: both sat
     * inside a selector and neither ever ran. Keyframes belong at the top level.
     */
    public function test_keyframes_are_never_nested_inside_a_selector(): void
    {
        $css = $this->get(route('leap.css'))->getContent();

        foreach (explode('@keyframes', $css) as $index => $part) {
            if ($index === 0) {
                continue;
            }
            // Everything before this @keyframes must have balanced braces; an excess
            // of opening braces means it sits inside a style rule.
            $before = substr($css, 0, strpos($css, '@keyframes'.$part));
            $this->assertSame(
                substr_count($before, '{'),
                substr_count($before, '}'),
                'A @keyframes block is nested inside a selector, where browsers drop it.'
            );
        }
    }

    /**
     * Safari before 16.5 knows no CSS nesting and before 17.2 none without a leading
     * "&": it drops every nested rule and the panel comes out unstyled. The response
     * must therefore hold plain rules only, whatever the source files look like.
     */
    public function test_css_response_contains_no_nesting(): void
    {
        // Without comments and strings, which may hold an "&" or a brace of their own
        $css = (string) preg_replace(['#/\*.*?\*/#s', '/"[^"]*"|\'[^\']*\'/'], '', $this->get(route('leap.css'))->getContent());

        $this->assertStringNotContainsString('&', $css);

        // Inside a style rule there is never another block: only an at-rule may
        // hold one.
        $stack = [];
        $prelude = '';
        foreach (preg_split('/([{};])/', $css, -1, PREG_SPLIT_DELIM_CAPTURE) as $part) {
            if ($part === '{') {
                $this->assertNotSame('rule', end($stack), 'A block is nested inside a style rule: '.$prelude);
                $stack[] = str_starts_with($prelude, '@') ? 'at' : 'rule';
            } elseif ($part === '}') {
                array_pop($stack);
            } elseif ($part !== ';') {
                $prelude = trim($part);
            }
        }
        $this->assertSame([], $stack);
    }

    /**
     * The css files alone do not say what the browser gets: the flattener decides
     * that too. A fix there has to replace the cached and the browser's copy, even
     * when no css file changed.
     */
    public function test_css_version_follows_the_flattener(): void
    {
        $flattener = (new \ReflectionClass(CssFlattener::class))->getFileName();

        $this->assertSame(AssetController::cssFilemtime().'-'.filemtime($flattener), AssetController::cssVersion());
        $this->assertStringContainsString('?'.AssetController::cssVersion().'"', AssetController::cssLink());
    }
}
