<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use NickDeKruijk\Leap\Classes\AiTask;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

class EditorAiTranslateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        // Authenticate so Leap::validatePermission('update') passes; the gate
        // bypass grants the ability without wiring up roles.
        $this->actingAs(User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]));
        Gate::before(fn () => true);
    }

    public function test_translate_is_disabled_without_a_provider_or_key(): void
    {
        config(['leap.ai.translate.provider' => null]);
        $this->assertFalse(AiTask::for('translate')->enabled());

        config(['leap.ai.translate.provider' => 'claude', 'leap.ai.providers.claude.api_key' => null]);
        $this->assertFalse(AiTask::for('translate')->enabled());

        config(['leap.ai.translate.provider' => 'claude', 'leap.ai.providers.claude.api_key' => 'k']);
        $this->assertTrue(AiTask::for('translate')->enabled());
    }

    public function test_translate_via_claude_preserves_keys_and_html(): void
    {
        config(['leap.ai.translate.provider' => 'claude', 'leap.ai.providers.claude.api_key' => 'k']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '```json'."\n"
                    .'{"title":"Hello","body":"<p><strong>Bold</strong> <a href=\"/x\">link</a></p>"}'."\n".'```']],
            ]),
        ]);

        $out = AiTask::for('translate')->translate([
            'title' => 'Hallo',
            'body' => '<p><strong>Vet</strong> <a href="/x">link</a></p>',
        ], 'en', 'nl');

        $this->assertSame([
            'title' => 'Hello',
            'body' => '<p><strong>Bold</strong> <a href="/x">link</a></p>',
        ], $out);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com/v1/messages'));
    }

    public function test_translate_keeps_the_original_for_a_dropped_key(): void
    {
        config(['leap.ai.translate.provider' => 'openai', 'leap.ai.providers.openai.api_key' => 'k']);

        // Model returns only one of the two keys.
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"title":"Hello"}']]],
            ]),
        ]);

        $out = AiTask::for('translate')->translate(['title' => 'Hallo', 'body' => 'Tekst'], 'en', 'nl');

        $this->assertSame(['title' => 'Hello', 'body' => 'Tekst'], $out);
    }

    public function test_translate_via_deepl_maps_by_order_without_tag_handling_for_plain_text(): void
    {
        config(['leap.ai.translate.provider' => 'deepl', 'leap.ai.providers.deepl.api_key' => 'abc:fx']);

        Http::fake([
            'api-free.deepl.com/*' => Http::response([
                'translations' => [['text' => 'Hello'], ['text' => 'World']],
            ]),
        ]);

        // Plain-text values: no HTML markup.
        $out = AiTask::for('translate')->translate(['a' => 'Hallo', 'b' => 'Wereld'], 'en', 'nl');

        $this->assertSame(['a' => 'Hello', 'b' => 'World'], $out);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api-free.deepl.com/v2/translate')
                && $request['target_lang'] === 'EN-GB'    // target keeps the regional variant
                && $request['source_lang'] === 'NL'        // source is plain (never EN-GB)
                && ! isset($request['tag_handling'])       // plain text → no HTML handling
                && $request['text'] === ['Hallo', 'Wereld'];
        });
    }

    public function test_translate_via_deepl_uses_html_handling_for_markup(): void
    {
        config(['leap.ai.translate.provider' => 'deepl', 'leap.ai.providers.deepl.api_key' => 'abc:fx']);

        Http::fake([
            'api-free.deepl.com/*' => Http::response([
                'translations' => [['text' => '<p>Hello</p>']],
            ]),
        ]);

        $out = AiTask::for('translate')->translate(['body' => '<p>Hallo</p>'], 'en', 'nl');

        $this->assertSame(['body' => '<p>Hello</p>'], $out);

        Http::assertSent(fn ($request) => $request['tag_handling'] === 'html' && $request['text'] === ['<p>Hallo</p>']);
    }

    public function test_editor_translate_field_fills_the_active_locale(): void
    {
        Gate::before(fn () => true);
        config(['leap.ai.translate.provider' => 'claude', 'leap.ai.providers.claude.api_key' => 'k']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '{"title":"Hallo"}']],
            ]),
        ]);

        $editor = new Editor;
        $editor->activeLocale = 'nl';
        $editor->data = ['title' => ['nl' => '', 'en' => 'Hello']];

        $editor->translateField('data.title.nl', 'en');

        $this->assertSame('Hallo', $editor->data['title']['nl']);
        // The other locale is untouched.
        $this->assertSame('Hello', $editor->data['title']['en']);
    }

    public function test_editor_translate_field_resolves_a_section_path(): void
    {
        Gate::before(fn () => true);
        config(['leap.ai.translate.provider' => 'claude', 'leap.ai.providers.claude.api_key' => 'k']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '{"blocks.2.heading":"Hoi"}']],
            ]),
        ]);

        $editor = new Editor;
        $editor->activeLocale = 'nl';
        $editor->data = ['blocks' => [2 => ['heading' => ['nl' => '', 'en' => 'Hi']]]];

        $editor->translateField('data.blocks.2.heading.nl', 'en');

        $this->assertSame('Hoi', $editor->data['blocks'][2]['heading']['nl']);
    }

    public function test_editor_translate_field_skips_an_empty_source(): void
    {
        Gate::before(fn () => true);
        config(['leap.ai.translate.provider' => 'claude', 'leap.ai.providers.claude.api_key' => 'k']);
        Http::fake();

        $editor = new Editor;
        $editor->activeLocale = 'nl';
        $editor->data = ['title' => ['nl' => '', 'en' => '']];

        $editor->translateField('data.title.nl', 'en');

        $this->assertSame('', $editor->data['title']['nl']);
        Http::assertNothingSent();
    }
}
