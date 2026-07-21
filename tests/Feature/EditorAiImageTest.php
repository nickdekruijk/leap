<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Classes\AiTask;
use NickDeKruijk\Leap\Classes\ImageGenerator;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\Fixtures\ArticleResource;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Generating a section image: the provider call, the normalisation to a JPEG at the
 * chosen aspect ratio, the cost that is shown for it, and the two-step accept that
 * keeps a rejected image off the disk.
 */
class EditorAiImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->actingAs(User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]));
        Gate::before(fn () => true);

        Leap::context()->setModule(ArticleResource::class);

        config([
            'leap.ai.image.provider' => 'gemini',
            'leap.ai.providers.gemini.api_key' => 'test-key',
            'leap.ai.pricing' => [
                'gemini-2.5-flash-image' => ['input' => 0.30, 'output' => 30.00, 'estimate' => 0.039],
            ],
        ]);
    }

    private function editor(array $data = []): Editor
    {
        $editor = new Editor;
        $editor->data = $data;
        $editor->mediaUpdated = [];

        return $editor;
    }

    private function pngBytes(int $width = 64, int $height = 64): string
    {
        $gd = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($gd);
        $bytes = ob_get_clean();
        imagedestroy($gd);

        return $bytes;
    }

    private function fakeGemini(int $promptTokens = 10, int $imageTokens = 1290): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [[
                    'inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($this->pngBytes())],
                ]]]]],
                'usageMetadata' => [
                    'promptTokenCount' => $promptTokens,
                    'candidatesTokenCount' => $imageTokens,
                ],
            ]),
        ]);
    }

    public function test_the_image_task_is_disabled_without_a_provider_or_key(): void
    {
        config(['leap.ai.image.provider' => null]);
        $this->assertFalse(AiTask::for('image')->enabled());

        config(['leap.ai.image.provider' => 'openai', 'leap.ai.providers.openai.api_key' => null]);
        $this->assertFalse(AiTask::for('image')->enabled());

        config(['leap.ai.providers.openai.api_key' => 'k']);
        $this->assertTrue(AiTask::for('image')->enabled());
    }

    public function test_the_image_task_defaults_to_an_image_model_not_a_chat_model(): void
    {
        config(['leap.ai.image' => ['provider' => 'gemini', 'model' => null]]);
        $this->assertSame('gemini-2.5-flash-image', AiTask::for('image')->model);

        config(['leap.ai.image' => ['provider' => 'openai', 'model' => null]]);
        $this->assertSame('gpt-image-1-mini', AiTask::for('image')->model);

        config(['leap.ai.image' => ['provider' => 'openai', 'model' => 'gpt-image-2']]);
        $this->assertSame('gpt-image-2', AiTask::for('image')->model);

        // The chat tasks keep their own defaults.
        config(['leap.ai.alt_text' => ['provider' => 'gemini', 'model' => null]]);
        $this->assertSame('gemini-2.5-flash', AiTask::for('alt_text')->model);
    }

    public function test_generating_returns_a_preview_and_writes_nothing_to_disk(): void
    {
        $this->fakeGemini();

        $result = $this->editor()->generateImage('A red bicycle in the rain', '16:9');

        $this->assertNotEmpty($result['token']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $result['preview']);

        // Reviewed before it is committed: the disk stays untouched until accepted.
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, Media::count());
    }

    /**
     * A cache store is not a binary-safe place to park bytes. The database driver keeps
     * its value in a utf8mb4 text column and rejects raw JPEG outright — "Incorrect
     * string value" on insert — so generating worked on a file cache and blew up on a
     * database one. What goes in has to survive a round trip through text.
     */
    public function test_the_parked_image_is_text_safe_for_any_cache_store(): void
    {
        $this->fakeGemini();

        $token = $this->editor()->generateImage('A red bicycle', '16:9')['token'];
        $parked = Cache::get('leap-ai-image:'.$token);

        $this->assertTrue(
            mb_check_encoding(serialize($parked), 'UTF-8'),
            'The cached payload holds raw bytes; a database cache driver would reject it.',
        );

        // And it still comes back as the bytes that went in.
        $this->assertStringStartsWith("\xFF\xD8\xFF", ImageGenerator::unpark($token)['data']);
    }

    public function test_the_cost_shown_is_computed_from_the_reported_token_usage(): void
    {
        $this->fakeGemini(promptTokens: 10, imageTokens: 1290);

        $result = $this->editor()->generateImage('A red bicycle', '16:9');

        // 10 / 1M * $0.30 + 1290 / 1M * $30.00
        $this->assertEqualsWithDelta(0.038703, $result['cost'], 0.0000001);
    }

    public function test_a_model_without_a_configured_rate_shows_no_cost(): void
    {
        config(['leap.ai.pricing' => []]);
        $this->fakeGemini();

        $this->assertNull($this->editor()->generateImage('A red bicycle', '16:9')['cost']);
    }

    public function test_a_reply_without_usage_falls_back_to_the_estimate(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [[
                    'inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($this->pngBytes())],
                ]]]]],
            ]),
        ]);

        $this->assertSame(0.039, $this->editor()->generateImage('A red bicycle', '16:9')['cost']);
    }

    public function test_accepting_stores_the_image_in_the_modules_folder_and_attaches_it(): void
    {
        $this->fakeGemini();

        $editor = $this->editor(['sections.0.image' => []]);
        $token = $editor->generateImage('A red bicycle in the rain', '16:9')['token'];

        $editor->useGeneratedImage('sections.0.image', $token);

        // {module} resolves to the module's own folder, so a module's images stay together.
        $path = 'article-resources/a-red-bicycle-in-the-rain.jpg';
        Storage::disk('public')->assertExists($path);

        $media = Media::where('file_name', $path)->first();
        $this->assertNotNull($media);
        $this->assertSame([$media->id], $editor->data['sections.0.image']);
        $this->assertSame(['sections.0.image' => 'sections.0.image'], $editor->mediaUpdated);

        // What it is and what it cost, for later audit.
        $this->assertSame('gemini-2.5-flash-image', $media->meta['ai']['model']);
        $this->assertSame('A red bicycle in the rain', $media->meta['ai']['prompt']);
        $this->assertEqualsWithDelta(0.038703, $media->meta['ai']['cost'], 0.0000001);
    }

    public function test_the_stored_image_is_cropped_to_the_chosen_aspect_ratio(): void
    {
        $this->fakeGemini();

        $editor = $this->editor();
        $editor->useGeneratedImage('image', $editor->generateImage('Square source', '16:9')['token']);

        $image = Media::imageManager()->read(Storage::disk('public')->get('article-resources/square-source.jpg'));

        // A square provider image, framed to 16:9 by the crop step.
        $this->assertSame(64, $image->width());
        $this->assertSame(36, $image->height());
    }

    public function test_an_expired_token_reports_back_instead_of_storing_anything(): void
    {
        $this->fakeGemini();

        $editor = $this->editor();
        $token = $editor->generateImage('A red bicycle', '16:9')['token'];
        Cache::forget('leap-ai-image:'.$token);

        $editor->useGeneratedImage('image', $token);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_alt_text_is_generated_for_the_accepted_image_when_configured(): void
    {
        config([
            'leap.locales' => ['nl' => 'Nederlands', 'en' => 'English'],
            'leap.ai.image.alt_text' => true,
            'leap.ai.alt_text.provider' => 'claude',
            'leap.ai.providers.claude.api_key' => 'k',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [[
                    'inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($this->pngBytes())],
                ]]]]],
            ]),
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '{"nl":"Een fiets","en":"A bicycle"}']],
            ]),
        ]);

        $editor = $this->editor();
        $editor->useGeneratedImage('image', $editor->generateImage('A bicycle', '1:1')['token']);

        $media = Media::first();
        $this->assertSame(['nl' => 'Een fiets', 'en' => 'A bicycle'], $media->meta['alt']);
    }

    public function test_a_failing_alt_text_does_not_lose_the_image_that_was_paid_for(): void
    {
        config([
            'leap.ai.image.alt_text' => true,
            'leap.ai.alt_text.provider' => 'claude',
            'leap.ai.providers.claude.api_key' => 'k',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [[
                    'inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($this->pngBytes())],
                ]]]]],
            ]),
            'api.anthropic.com/*' => Http::response('', 500),
        ]);

        $editor = $this->editor();
        $editor->useGeneratedImage('image', $editor->generateImage('A bicycle', '1:1')['token']);

        Storage::disk('public')->assertExists('article-resources/a-bicycle.jpg');
    }

    public function test_a_provider_error_returns_nothing_and_toasts(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('', 500)]);

        $this->assertSame([], $this->editor()->generateImage('A red bicycle', '16:9'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_empty_prompt_never_reaches_the_provider(): void
    {
        Http::fake();

        $this->assertSame([], $this->editor()->generateImage('   ', '16:9'));

        Http::assertNothingSent();
    }

    public function test_the_prompt_is_suggested_from_the_sections_own_content(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        $editor = $this->editor([
            'title' => ['nl' => 'Onze werkplaats', 'en' => 'Our workshop'],
            'sections' => [
                0 => ['_name' => 'text', '_sort' => 1, 'heading' => ['nl' => 'Reparatie', 'en' => 'Repairs']],
                1 => [
                    '_name' => 'text',
                    '_sort' => 2,
                    'heading' => ['nl' => 'Onderhoud', 'en' => 'Maintenance'],
                    'body' => ['nl' => '<p>Wij <strong>repareren</strong> fietsen</p>', 'en' => '<p>We repair bikes</p>'],
                    'image' => [7],
                ],
            ],
        ]);
        $editor->activeLocale = 'nl';

        $prompt = $editor->imagePrompt('sections.1.image');

        // The record title plus that one section's text, at the locale being edited,
        // with markup stripped. The neighbouring section and the media ids stay out.
        $this->assertStringContainsString('Onze werkplaats', $prompt);
        $this->assertStringContainsString('Onderhoud', $prompt);
        $this->assertStringContainsString('Wij repareren fietsen', $prompt);
        $this->assertStringNotContainsString('Reparatie', $prompt);
        $this->assertStringNotContainsString('<strong>', $prompt);
        $this->assertStringNotContainsString('7', $prompt);
    }

    public function test_the_module_folder_does_not_move_with_the_admin_language(): void
    {
        config(['leap.ai.image.folder' => '{module}']);

        app()->setLocale('nl');
        $dutch = ImageGenerator::folderFor(ArticleResource::class);

        app()->setLocale('en');
        $english = ImageGenerator::folderFor(ArticleResource::class);

        $this->assertSame('article-resources', $dutch);
        $this->assertSame($dutch, $english);
    }

    public function test_the_folder_pattern_can_be_a_literal_or_combined(): void
    {
        config(['leap.ai.image.folder' => 'ai']);
        $this->assertSame('ai', ImageGenerator::folderFor(ArticleResource::class));

        config(['leap.ai.image.folder' => 'ai/{module}']);
        $this->assertSame('ai/article-resources', ImageGenerator::folderFor(ArticleResource::class));

        // No module in context (the file manager): the placeholder simply drops out.
        $this->assertSame('ai', ImageGenerator::folderFor(null));
    }

    public function test_openai_maps_the_aspect_ratio_onto_one_of_its_canvas_sizes(): void
    {
        config([
            'leap.ai.image.provider' => 'openai',
            'leap.ai.providers.openai.api_key' => 'k',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['b64_json' => base64_encode($this->pngBytes())]],
                'usage' => ['input_tokens' => 12, 'output_tokens' => 1000],
            ]),
        ]);

        $this->editor()->generateImage('A red bicycle', '3:4');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.openai.com/v1/images/generations')
                && $request['size'] === '1024x1536'
                && $request['model'] === 'gpt-image-1-mini';
        });
    }
}
