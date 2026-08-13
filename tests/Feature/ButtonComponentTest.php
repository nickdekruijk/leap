<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * The link flavour of x-leap::button used to drop everything it was handed and always
 * navigate through Livewire, which is right inside the panel and wrong for a link that
 * leaves it: the frontend preview would have been swapped into the panel it was opened
 * from, and could not have asked for a tab of its own.
 */
class ButtonComponentTest extends TestCase
{
    public function test_a_link_button_still_navigates_by_default(): void
    {
        $html = Blade::render('<x-leap::button href="/somewhere" label="leap::resource.save" />');

        $this->assertStringContainsString('href="/somewhere"', $html);
        $this->assertStringContainsString('wire:navigate', $html);
    }

    public function test_a_link_button_forwards_its_attributes(): void
    {
        $html = Blade::render('<x-leap::button href="/somewhere" target="leap-preview" rel="noopener" label="leap::resource.save" />');

        $this->assertStringContainsString('target="leap-preview"', $html);
        $this->assertStringContainsString('rel="noopener"', $html);
    }

    public function test_navigation_can_be_switched_off_for_a_link_that_leaves_the_panel(): void
    {
        $html = Blade::render('<x-leap::button href="/somewhere" :navigate="false" label="leap::resource.save" />');

        $this->assertStringNotContainsString('wire:navigate', $html);
    }

    public function test_a_plain_button_is_untouched(): void
    {
        $html = Blade::render('<x-leap::button wire:click="save" label="leap::resource.save" class="primary" />');

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('wire:click="save"', $html);
        $this->assertStringContainsString('leap-button primary', $html);
    }
}
