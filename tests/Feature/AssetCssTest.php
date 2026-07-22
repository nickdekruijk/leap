<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Cache;
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
        $this->assertSame(AssetController::cssFilemtime(), Cache::get('leap.css.filemtime'));
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
            '/\.leap-index-row:not\(\.leap-index-row-selected\)\s*\{\s*TD\s*\{\s*background-color:\s*transparent;/',
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
}
