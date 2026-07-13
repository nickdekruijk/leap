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

        $this->assertStringContainsString('scroll-margin-top: var(--nav-height-compact', $template);

        // The compact sizes must be set on the elements, not by redefining the
        // tokens on :root — project.scss is concatenated after this file, so its
        // own :root would win (a media query adds no specificity, and the mobile
        // bar would silently stay tall).
        $mobile = substr($template, strpos($template, '@media (max-width: $bp-mobile)'));

        $this->assertStringContainsString('.nav .nav-container {', $mobile);
        $this->assertStringNotContainsString('--nav-height: var(--nav-height-compact', $mobile);
    }

    public function test_a_cramped_bar_shrinks_the_logo_instead_of_wrapping_the_menu(): void
    {
        $template = $this->stub('resources/css/template.scss');

        // Menu items must never break in half...
        $this->assertStringContainsString('white-space: nowrap', $template);

        // ...so the logo is what gives way: it may shrink (flex 0 1 auto) while the
        // menu keeps its size, and it is capped with max-height rather than a fixed
        // height, so a squeeze scales it down proportionally instead of squashing.
        $this->assertStringContainsString('flex: 0 1 auto', $template);
        $this->assertStringContainsString('min-width: min(100%, var(--logo-min-width, 0px))', $template);
        $this->assertStringContainsString('max-height: var(--logo-height, none)', $template);
        $this->assertStringContainsString('flex-shrink: 0', $template);
    }

    public function test_the_logo_stays_visible_above_the_open_mobile_menu(): void
    {
        $template = $this->stub('resources/css/template.scss');
        $mobile = substr($template, strpos($template, '@media (max-width: $bp-mobile)'));

        // .nav-main-container is a fixed panel pinned to the top of the viewport,
        // so it covers the bar. The toggle lifts itself above it; without the same
        // treatment the logo disappears behind the open menu.
        $this->assertStringContainsString('z-index: 1100', $mobile);

        $logoRule = substr($mobile, strpos($mobile, '.nav .nav-logo {'));
        $logoRule = substr($logoRule, 0, strpos($logoRule, "\n    }"));

        $this->assertStringContainsString('position: relative', $logoRule);
        $this->assertStringContainsString('z-index: 1100', $logoRule);
    }
}
