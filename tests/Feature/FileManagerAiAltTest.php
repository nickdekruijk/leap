<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NickDeKruijk\Leap\Classes\AiTask;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class FileManagerAiAltTest extends TestCase
{
    private function superuser(): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
        // The seeded superuser role (id 1) grants all permissions. Per-module
        // permission gates resolve against the active module context, which is
        // only set in the Livewire boot lifecycle — bypass the gate here so the
        // tests can drive the component methods directly.
        $user->roles()->attach(Role::find(1));
        Gate::before(fn () => true);

        $this->actingAs($user);

        return $user;
    }

    private function pngBytes(int $width = 10, int $height = 10): string
    {
        $gd = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($gd);
        $bytes = ob_get_clean();
        imagedestroy($gd);

        return $bytes;
    }

    public function test_ai_alt_is_disabled_without_a_provider_or_key(): void
    {
        config(['leap.ai.alt_text.provider' => null]);
        $this->assertFalse(AiTask::for('alt_text')->enabled());

        // Provider set but no api key configured.
        config(['leap.ai.alt_text.provider' => 'claude', 'leap.ai.providers.claude.api_key' => null]);
        $this->assertFalse(AiTask::for('alt_text')->enabled());
    }

    public function test_ai_alt_is_enabled_with_provider_and_key(): void
    {
        config(['leap.ai.alt_text.provider' => 'claude', 'leap.ai.providers.claude.api_key' => 'test-key']);
        $this->assertTrue(AiTask::for('alt_text')->enabled());
    }

    public function test_model_falls_back_to_the_provider_default(): void
    {
        config(['leap.ai.alt_text' => ['provider' => 'gemini', 'model' => null]]);
        $this->assertSame('gemini-2.5-flash', AiTask::for('alt_text')->model);

        config(['leap.ai.alt_text' => ['provider' => 'claude', 'model' => null]]);
        $this->assertSame('claude-haiku-4-5', AiTask::for('alt_text')->model);

        config(['leap.ai.alt_text' => ['provider' => 'openai', 'model' => null]]);
        $this->assertSame('gpt-4o-mini', AiTask::for('alt_text')->model);

        config(['leap.ai.alt_text' => ['provider' => 'claude', 'model' => 'claude-sonnet-5']]);
        $this->assertSame('claude-sonnet-5', AiTask::for('alt_text')->model);
    }

    public function test_generate_alt_texts_via_claude_returns_a_filtered_locale_map(): void
    {
        $this->superuser();
        Storage::fake('public');
        Storage::disk('public')->put('cat.png', $this->pngBytes());

        config([
            'leap.locales' => ['nl' => 'Nederlands', 'en' => 'English'],
            'leap.ai.alt_text.provider' => 'claude',
            'leap.ai.providers.claude.api_key' => 'test-key',
        ]);

        // Model returns an extra locale (fr) that must be filtered out.
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '{"nl":"Een kat","en":"A cat","fr":"Un chat"}']],
            ]),
        ]);

        $result = (new FileManager)->generateAltTexts('cat.png');

        $this->assertSame(['nl' => 'Een kat', 'en' => 'A cat'], $result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.anthropic.com/v1/messages')
                && $request['messages'][0]['content'][0]['type'] === 'image';
        });
    }

    public function test_generate_alt_texts_via_gemini_returns_a_filtered_locale_map(): void
    {
        $this->superuser();
        Storage::fake('public');
        Storage::disk('public')->put('cat.png', $this->pngBytes());

        config([
            'leap.locales' => ['nl' => 'Nederlands', 'en' => 'English'],
            'leap.ai.alt_text.provider' => 'gemini',
            'leap.ai.providers.gemini.api_key' => 'test-key',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"nl":"Een kat","en":"A cat"}']]]]],
            ]),
        ]);

        $result = (new FileManager)->generateAltTexts('cat.png');

        $this->assertSame(['nl' => 'Een kat', 'en' => 'A cat'], $result);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
    }

    public function test_generate_alt_texts_via_openai_returns_a_filtered_locale_map(): void
    {
        $this->superuser();
        Storage::fake('public');
        Storage::disk('public')->put('cat.png', $this->pngBytes());

        config([
            'leap.locales' => ['nl' => 'Nederlands', 'en' => 'English'],
            'leap.ai.alt_text.provider' => 'openai',
            'leap.ai.providers.openai.api_key' => 'test-key',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"nl":"Een kat","en":"A cat"}']]],
            ]),
        ]);

        $result = (new FileManager)->generateAltTexts('cat.png');

        $this->assertSame(['nl' => 'Een kat', 'en' => 'A cat'], $result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.openai.com/v1/chat/completions')
                && $request['messages'][0]['content'][1]['type'] === 'image_url';
        });
    }

    public function test_generate_alt_texts_skips_non_bitmap_files(): void
    {
        $this->superuser();
        Storage::fake('public');
        Storage::disk('public')->put('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        config([
            'leap.ai.alt_text.provider' => 'claude',
            'leap.ai.providers.claude.api_key' => 'test-key',
        ]);

        Http::fake();

        $this->assertSame([], (new FileManager)->generateAltTexts('logo.svg'));

        Http::assertNothingSent();
    }

    public function test_generate_alt_texts_returns_empty_and_toasts_on_provider_error(): void
    {
        $user = $this->superuser();
        Storage::fake('public');
        Storage::disk('public')->put('cat.png', $this->pngBytes());

        config([
            'leap.ai.alt_text.provider' => 'claude',
            'leap.ai.providers.claude.api_key' => 'test-key',
        ]);

        Http::fake(['api.anthropic.com/*' => Http::response('', 500)]);

        // Direct call: provider error is swallowed and an empty map returned.
        $this->assertSame([], (new FileManager)->generateAltTexts('cat.png'));

        // Through Livewire: the failure surfaces as an error toast.
        Livewire::actingAs($user)
            ->test(FileManager::class)
            ->call('generateAltTexts', 'cat.png')
            ->assertDispatched('toast-error');
    }
}
