<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Livewire\Livewire;
use NickDeKruijk\Leap\Livewire\Toasts;
use NickDeKruijk\Leap\Tests\TestCase;
use NickDeKruijk\Leap\Traits\ToastsValidationErrors;

/**
 * How a failed save reaches the person who made it. Each message is dispatched
 * with the field it belongs to, which is what lets the toast focus that input —
 * a message without its key leaves the editor hunting for the offending field.
 */
class ToastsValidationErrorsTest extends TestCase
{
    public function test_every_failing_field_gets_its_own_toast(): void
    {
        Livewire::test(ToastingComponent::class)
            ->call('validateData', ['name' => '', 'email' => 'not-an-email'])
            ->assertDispatchedTo(Toasts::class, 'toast-error')
            ->assertDispatchedTo(Toasts::class, 'toast-error');
    }

    /**
     * Only the first message per field is shown: a field failing three rules at
     * once would otherwise bury the screen in near-identical toasts.
     */
    public function test_a_field_failing_several_rules_produces_one_toast(): void
    {
        $component = Livewire::test(ToastingComponent::class)
            ->call('validateData', ['name' => '', 'email' => 'valid@example.com']);

        $dispatched = array_filter(
            $component->effects['dispatches'] ?? [],
            fn (array $event): bool => $event['name'] === 'toast-error',
        );

        $this->assertCount(1, $dispatched);
    }

    /**
     * The field key travels with the message, so the toast can highlight the
     * input the message is about.
     */
    public function test_the_toast_carries_the_field_it_belongs_to(): void
    {
        $component = Livewire::test(ToastingComponent::class)
            ->call('validateData', ['name' => '', 'email' => 'valid@example.com']);

        $event = collect($component->effects['dispatches'] ?? [])
            ->firstWhere('name', 'toast-error');

        $this->assertSame('name', $event['params'][1]);
        $this->assertNotEmpty($event['params'][0]);
    }

    public function test_a_passing_validator_dispatches_nothing(): void
    {
        Livewire::test(ToastingComponent::class)
            ->call('validateData', ['name' => 'Nick', 'email' => 'nick@example.com'])
            ->assertNotDispatched('toast-error');
    }
}

class ToastingComponent extends Component
{
    use ToastsValidationErrors;

    public function validateData(array $data): void
    {
        $validator = Validator::make($data, [
            'name' => 'required|min:2',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            $this->toastValidationErrors($validator);
        }
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
