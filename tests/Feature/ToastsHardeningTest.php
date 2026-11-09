<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use NickDeKruijk\Leap\Livewire\Toasts;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Events reach Toasts through __dispatch from the browser, so whatever a
 * listener receives is outside input. A visitor can only touch their own toast
 * state, but a bot must not get a 500 and a focus value must not run as script.
 */
class ToastsHardeningTest extends TestCase
{
    public function test_add_cannot_be_called_from_the_client(): void
    {
        $component = Livewire::test(Toasts::class);

        try {
            $component->call('add', 'x', 'error', 'fas-unknown', null, 'f');
            $this->fail('add() was callable from the client.');
        } catch (MethodNotFoundException) {
            // expected
        }

        $this->assertSame([], $component->get('toasts'));
    }

    public function test_an_array_message_from_dispatch_adds_no_toast(): void
    {
        $component = Livewire::test(Toasts::class)
            ->call('__dispatch', 'toast-error', ['message' => ['x', 'y']])
            ->assertOk();

        $this->assertSame([], $component->get('toasts'));
    }

    public function test_a_dispatch_without_message_adds_no_toast(): void
    {
        foreach (['toast', 'toast-alert', 'toast-error'] as $event) {
            $component = Livewire::test(Toasts::class)
                ->call('__dispatch', $event, [])
                ->assertOk();

            $this->assertSame([], $component->get('toasts'), $event);
        }
    }

    public function test_an_unknown_param_in_a_dispatch_is_ignored(): void
    {
        foreach (['toast', 'toast-alert', 'toast-error'] as $event) {
            $component = Livewire::test(Toasts::class)
                ->call('__dispatch', $event, ['message' => 'm', 'bogus' => 'x'])
                ->assertOk();

            $this->assertSame(['m'], array_column($component->get('toasts'), 'message'), $event);
        }
    }

    public function test_close_ignores_an_id_that_is_not_a_key(): void
    {
        $component = Livewire::test(Toasts::class)
            ->dispatch('toast', message: 'keep')
            ->call('close', ['x'])
            ->assertOk();

        $this->assertSame(['keep'], array_column($component->get('toasts'), 'message'));
    }

    public function test_a_non_string_focus_is_dropped(): void
    {
        $component = Livewire::test(Toasts::class)
            ->call('__dispatch', 'toast-error', ['message' => 'm', 'focus' => ['x']])
            ->assertOk();

        $toasts = $component->get('toasts');
        $this->assertCount(1, $toasts);
        $this->assertNull($toasts[0]['focus']);
        $this->assertStringNotContainsString('onclick', $component->html());
    }

    public function test_focus_from_dispatch_is_rendered_as_a_js_string_in_onclick(): void
    {
        $html = Livewire::test(Toasts::class)
            ->call('__dispatch', 'toast-error', ['message' => 'm', 'focus' => "a');alert(1);//"])
            ->html();

        // Js::from() writes the quote as a JSON escape (backslash u0027), so the
        // value stays one JS string. It is Htmlable, so Blade prints it as is: a
        // bare ' inside the double-quoted attribute is harmless.
        $bs = chr(92);
        $script = "document.getElementById('a{$bs}u0027);alert(1);{$bs}/{$bs}/').focus()";

        $this->assertStringContainsString('onclick="'.$script.'"', $html);
        $this->assertStringNotContainsString("'a');alert(1)", html_entity_decode($html, ENT_QUOTES));
    }

    public function test_a_double_quote_in_focus_does_not_leave_the_attribute(): void
    {
        $html = Livewire::test(Toasts::class)
            ->call('__dispatch', 'toast-error', ['message' => 'm', 'focus' => 'a" onmouseover="alert(1)'])
            ->html();

        $this->assertStringNotContainsString('onmouseover="', $html);
        $this->assertStringContainsString(chr(92).'u0022', $html);
    }
}
