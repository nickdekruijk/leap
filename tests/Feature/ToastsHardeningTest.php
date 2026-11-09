<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
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

    public function test_a_number_as_message_or_focus_still_works(): void
    {
        $component = Livewire::test(Toasts::class)
            ->call('__dispatch', 'toast', ['message' => 5])
            ->call('__dispatch', 'toast-error', ['message' => 1.5, 'focus' => 7])
            ->assertOk();

        $toasts = $component->get('toasts');
        $this->assertSame(['5', '1.5'], array_column($toasts, 'message'));
        $this->assertSame('7', $toasts[1]['focus']);
        $this->assertStringContainsString("document.getElementById('7').focus()", html_entity_decode($component->html(), ENT_QUOTES));
    }

    public function test_a_bool_or_object_as_message_adds_no_toast(): void
    {
        $component = Livewire::test(Toasts::class)
            ->call('__dispatch', 'toast', ['message' => true])
            ->call('__dispatch', 'toast', ['message' => ['nested' => 'x']])
            ->assertOk();

        $this->assertSame([], $component->get('toasts'));
    }

    public function test_a_stringable_from_php_still_adds_a_toast(): void
    {
        $component = Livewire::test(Toasts::class);
        $component->instance()->default(new HtmlString('html'));
        $component->instance()->error(Str::of('str'), Str::of('field'));

        $toasts = $component->instance()->toasts;
        $this->assertSame(['html', 'str'], array_column($toasts, 'message'));
        $this->assertSame('field', $toasts[1]['focus']);
    }

    public function test_a_long_message_is_cut(): void
    {
        $component = Livewire::test(Toasts::class)
            ->call('__dispatch', 'toast', ['message' => str_repeat('a', 100000)]);

        $message = $component->get('toasts')[0]['message'];
        $this->assertSame(2000, mb_strlen($message));
        $this->assertStringEndsWith('a…', $message);

        // Characters, not display width: wide characters count once.
        $component = Livewire::test(Toasts::class)
            ->call('__dispatch', 'toast', ['message' => str_repeat('日', 1500)]);

        $this->assertSame(1500, mb_strlen($component->get('toasts')[0]['message']));
    }

    public function test_expired_toasts_are_cleared_when_a_new_one_arrives(): void
    {
        $component = Livewire::test(Toasts::class)
            ->dispatch('toast', message: 'stale');
        $component->instance()->toasts[0]['expires'] = time() - 3;

        $component->instance()->default('fresh');

        // A new id, not the 0 of the expired toast whose element is still there.
        $this->assertSame([1], array_keys($component->instance()->toasts));
        $this->assertSame(['fresh'], array_column($component->instance()->toasts, 'message'));
    }

    public function test_everything_one_request_sends_is_kept_up_to_the_cap(): void
    {
        // One toast per invalid field or import error must not get lost.
        $component = Livewire::test(Toasts::class);
        for ($i = 1; $i <= 20; $i++) {
            $component->instance()->error("field {$i} is required", "f{$i}");
        }
        $this->assertCount(20, $component->instance()->toasts);

        for ($i = 21; $i <= 60; $i++) {
            $component->instance()->default("m{$i}");
        }

        $toasts = $component->instance()->toasts;
        $this->assertCount(50, $toasts);
        $this->assertSame('field 11 is required', reset($toasts)['message']);
        $this->assertSame('m60', end($toasts)['message']);

        // The ids stay the keys they had, so close() still hits the right one.
        $this->assertSame(range(10, 59), array_keys($toasts));
        $component->instance()->close(59);
        $this->assertSame(range(10, 58), array_keys($component->instance()->toasts));
    }

    public function test_focus_zero_still_gets_its_onclick(): void
    {
        // A list field validated at index 0 sends 0 as focus key.
        $html = Livewire::test(Toasts::class)
            ->call('__dispatch', 'toast-error', ['m', 0])
            ->html();

        $this->assertStringContainsString("document.getElementById('0').focus()", html_entity_decode($html, ENT_QUOTES));
    }

    public function test_a_new_toast_leaves_one_that_may_still_be_fading(): void
    {
        // Server expiry is in whole seconds; the animation runs from the response.
        $component = Livewire::test(Toasts::class)
            ->dispatch('toast', message: 'fading');
        $component->instance()->toasts[0]['expires'] = time() - 1;

        $component->instance()->default('fresh');

        $this->assertSame(['fading', 'fresh'], array_column($component->instance()->toasts, 'message'));

        // Opening or closing the editor still clears it right away.
        $component->instance()->clearExpired();
        $this->assertSame(['fresh'], array_values(array_column($component->instance()->toasts, 'message')));
    }

    public function test_an_empty_focus_gets_no_onclick(): void
    {
        $component = Livewire::test(Toasts::class)
            ->call('__dispatch', 'toast-error', ['message' => 'm', 'focus' => '']);

        $this->assertNull($component->get('toasts')[0]['focus']);
        $this->assertStringNotContainsString('onclick', $component->html());
    }
}
