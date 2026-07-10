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
}
