<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Tests\TestCase;

/**
 * The navigation bar shrinks once the page scrolls, and starts out compact on
 * mobile. It is pure CSS driven by tokens, so the shipped stubs are what is
 * asserted here; a project opts out by unsetting the *-compact tokens.
 */
class TemplateNavShrinkTest extends TestCase
{
    private function stub(string $relative): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/stubs/template/'.$relative);
    }

    public function test_project_scss_ships_the_navigation_size_tokens(): void
    {
        $project = $this->stub('resources/css/project.scss');

        $this->assertStringContainsString('--nav-height:', $project);
        $this->assertStringContainsString('--nav-height-compact:', $project);
        $this->assertStringContainsString('--logo-font-size:', $project);
        $this->assertStringContainsString('--logo-font-size-compact:', $project);
        $this->assertStringContainsString('--nav-shrink-duration:', $project);
    }

    public function test_the_bar_and_logo_shrink_while_scrolling(): void
    {
        $template = $this->stub('resources/css/template.scss');

        // Alpine already sets .scrolling on the nav; the compact sizes hang off it
        $this->assertStringContainsString('min-height: var(--nav-height-compact, var(--nav-height))', $template);
        $this->assertStringContainsString('font-size: var(--logo-font-size-compact', $template);
        $this->assertStringContainsString('height: var(--logo-height-compact', $template);

        // ...and they animate
        $this->assertStringContainsString('transition: min-height var(--nav-shrink-duration', $template);
        $this->assertStringContainsString('transition: font-size var(--nav-shrink-duration', $template);
    }

    public function test_mobile_starts_out_compact_and_anchors_offset_by_it(): void
    {
        $template = $this->stub('resources/css/template.scss');

        $this->assertStringContainsString('--nav-height: var(--nav-height-compact', $template);
        $this->assertStringContainsString('scroll-margin-top: var(--nav-height-compact', $template);
    }
}
