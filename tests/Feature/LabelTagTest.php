<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * A <label> without a for attribute adopts its first labelable descendant, and a
 * <button> is labelable. Hovering anywhere on the label then lights up that button as
 * if the pointer were on it — so a media field, whose label holds a browse button and
 * an AI generate button next to each other, showed the browse button reacting to a
 * hover over the generate button. A field whose slot holds only actions renders as a
 * plain element instead.
 */
class LabelTagTest extends TestCase
{
    private function render(string $template): string
    {
        // @error() reads the bag the session middleware normally shares.
        View::share('errors', new ViewErrorBag);

        return Blade::render($template, [
            'attribute' => Attribute::make('image'),
            'name' => 'image',
            'label' => 'Image',
        ]);
    }

    public function test_a_field_label_is_a_label_element_by_default(): void
    {
        $html = $this->render('<x-leap::label>field</x-leap::label>');

        $this->assertStringContainsString('<label class="leap-label"', $html);
    }

    public function test_a_slot_of_only_buttons_renders_without_a_label_element(): void
    {
        $html = $this->render('<x-leap::label tag="div"><button type="button">browse</button></x-leap::label>');

        $this->assertStringContainsString('<div class="leap-label"', $html);
        $this->assertStringNotContainsString('<label class="leap-label"', $html);
        // The closing tag has to follow the opening one.
        $this->assertStringContainsString('</div>', $html);
        $this->assertStringNotContainsString('</label>', $html);
    }
}
