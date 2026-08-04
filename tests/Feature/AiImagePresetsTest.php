<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NickDeKruijk\Leap\Classes\ImageGenerator;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\ArticleResource;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Image presets: the named model + quality combinations the generate dialog offers.
 * A preset carries its own provider (through the model name) and may override how the
 * result is stored, and the older provider/model/quality keys keep working for a config
 * written before presets existed.
 */
class AiImagePresetsTest extends TestCase
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
            'leap.ai.image' => ['presets' => [], 'folder' => '{module}', 'alt_text' => false],
            'leap.ai.providers.gemini.api_key' => 'gemini-key',
            'leap.ai.providers.openai.api_key' => 'openai-key',
        ]);
    }

    private function editor(): Editor
    {
        $editor = new Editor;
        $editor->data = [];
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

    private function fakeBothProviders(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [[
                    'inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($this->pngBytes())],
                ]]]]],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 1290],
            ]),
            'api.openai.com/*' => Http::response([
                'data' => [['b64_json' => base64_encode($this->pngBytes())]],
                'output_format' => 'png',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 1000],
            ]),
        ]);
    }

    public function test_a_preset_carries_its_model_and_quality_to_the_provider(): void
    {
        config(['leap.ai.image.presets' => ['medium' => 'gpt-image-1-mini:medium']]);
        $this->fakeBothProviders();

        $this->editor()->generateImage('A red bicycle', '16:9', 'medium');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/images/generations'
                && $request['model'] === 'gpt-image-1-mini'
                && $request['quality'] === 'medium';
        });
    }

    public function test_a_preset_without_a_quality_leaves_the_choice_to_the_provider(): void
    {
        config(['leap.ai.image.presets' => ['standard' => 'gpt-image-1-mini']]);
        $this->fakeBothProviders();

        $this->editor()->generateImage('A red bicycle', '16:9', 'standard');

        Http::assertSent(fn ($request) => ! isset($request['quality']));
    }

    /**
     * The provider is not configured next to the preset — it follows from the model
     * name, which is what lets one preset run on Gemini and the next on OpenAI.
     */
    public function test_the_provider_follows_from_the_model_name(): void
    {
        config(['leap.ai.image.presets' => [
            'cheap' => 'gemini-2.5-flash-image',
            'dear' => 'gpt-image-2:high',
        ]]);
        $this->fakeBothProviders();

        $editor = $this->editor();
        $editor->generateImage('A red bicycle', '16:9', 'cheap');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));

        $editor->generateImage('A blue bicycle', '16:9', 'dear');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.openai.com'));
    }

    public function test_a_preset_whose_provider_has_no_key_is_not_offered(): void
    {
        config([
            'leap.ai.image.presets' => ['cheap' => 'gemini-2.5-flash-image', 'dear' => 'gpt-image-2'],
            'leap.ai.providers.openai.api_key' => null,
        ]);

        $this->assertSame(['cheap'], array_keys(ImageGenerator::presets()));
        $this->assertTrue($this->editor()->aiImageEnabled());

        config(['leap.ai.providers.gemini.api_key' => null]);
        $this->assertSame([], ImageGenerator::presets());
        $this->assertFalse($this->editor()->aiImageEnabled());
    }

    public function test_a_fresh_config_without_presets_leaves_the_feature_off(): void
    {
        // No presets and no legacy provider: nothing to generate with, keys or not.
        $this->assertFalse($this->editor()->aiImageEnabled());
    }

    /**
     * The preset arrives from the browser, so it is looked up by key rather than taken
     * as a model name: a request naming something else can only ever fall back to what
     * the config already offers.
     */
    public function test_an_unknown_preset_falls_back_to_the_first_instead_of_its_own_model(): void
    {
        config(['leap.ai.image.presets' => ['cheap' => 'gemini-2.5-flash-image']]);
        $this->fakeBothProviders();

        $this->editor()->generateImage('A red bicycle', '16:9', 'gpt-image-2');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-2.5-flash-image'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.openai.com'));
    }

    /**
     * A config published before presets existed has none, and its provider/model/quality
     * keys have to keep deciding — including the estimate that follows the quality.
     */
    public function test_the_older_provider_model_and_quality_keys_still_decide(): void
    {
        config([
            'leap.ai.image.provider' => 'openai',
            'leap.ai.image.model' => 'gpt-image-2',
            'leap.ai.image.quality' => 'low',
        ]);
        $this->fakeBothProviders();

        $editor = $this->editor();
        $this->assertTrue($editor->aiImageEnabled());
        $this->assertSame(0.006, $editor->aiImageEstimate());

        $editor->generateImage('A red bicycle', '16:9');

        Http::assertSent(fn ($request) => $request['model'] === 'gpt-image-2' && $request['quality'] === 'low');
    }

    /**
     * The shape is what the provider is asked for, in the vocabulary each one speaks:
     * a canvas size for OpenAI, a ratio hint for Gemini.
     */
    public function test_the_orientation_is_translated_per_provider(): void
    {
        config(['leap.ai.image.presets' => [
            'gemini' => 'gemini-2.5-flash-image',
            'openai' => 'gpt-image-1-mini',
        ]]);
        $this->fakeBothProviders();

        $editor = $this->editor();

        $editor->generateImage('A tall tree', 'portrait', 'openai');
        Http::assertSent(fn ($request) => $request['size'] === '1024x1536');

        $editor->generateImage('A wide field', 'landscape', 'openai');
        Http::assertSent(fn ($request) => $request['size'] === '1536x1024');

        $editor->generateImage('A tall tree', 'portrait', 'gemini');
        Http::assertSent(fn ($request) => ($request['generationConfig']['imageConfig']['aspectRatio'] ?? null) === '3:4');

        $editor->generateImage('A square window', 'square', 'gemini');
        Http::assertSent(fn ($request) => ($request['generationConfig']['imageConfig']['aspectRatio'] ?? null) === '1:1');
    }

    /**
     * A preset says what to order from the provider; cropping and encoding the answer
     * is post-processing the provider never sees, so it stays one global setting.
     */
    public function test_how_the_result_is_stored_is_the_same_for_every_preset(): void
    {
        config([
            'leap.ai.image.presets' => [
                'cheap' => 'gemini-2.5-flash-image',
                'dear' => 'gpt-image-2:high',
            ],
            'leap.ai.image.max_width' => 20,
        ]);
        $this->fakeBothProviders();

        $editor = $this->editor();
        $editor->useGeneratedImage('image', $editor->generateImage('Cheap one', '1:1', 'cheap')['token']);
        $editor->useGeneratedImage('image', $editor->generateImage('Dear one', '1:1', 'dear')['token']);

        $width = fn (string $file) => Media::imageManager()
            ->read(Storage::disk('public')->get('article-resources/'.$file))->width();

        $this->assertSame(20, $width('cheap-one.jpg'));
        $this->assertSame(20, $width('dear-one.jpg'));
    }

    public function test_every_preset_is_offered_with_its_own_estimate(): void
    {
        config(['leap.ai.image.presets' => [
            'low' => 'gemini-2.5-flash-image',
            'high' => 'gpt-image-2:high',
            'unknown' => 'gpt-image-9-unreleased',
        ]]);

        $this->assertSame([
            'low' => ['label' => 'Low', 'estimate' => 0.039],
            'high' => ['label' => 'High', 'estimate' => 0.211],
            // A model with no known rate shows no price rather than a wrong one, and a
            // key without a translation is shown as it is written.
            'unknown' => ['label' => 'Unknown', 'estimate' => null],
        ], $this->editor()->aiImagePresets());
    }

    /**
     * The picker only earns its place when there is something to pick, so the dialog
     * renders it from two presets on and leaves it out below that.
     */
    public function test_the_dialog_shows_a_picker_only_from_two_presets_on(): void
    {
        $user = User::first();
        $user->roles()->attach(Role::find(1));

        config(['leap.ai.image.presets' => ['low' => 'gemini-2.5-flash-image']]);
        Livewire::test(FileManager::class)
            ->assertOk()
            ->assertDontSee('image_quality')
            ->assertDontSee(__('leap::resource.image_quality'));

        config(['leap.ai.image.presets' => [
            'low' => 'gemini-2.5-flash-image',
            'high' => 'gpt-image-2:high',
        ]]);
        Livewire::test(FileManager::class)
            ->assertOk()
            ->assertSee(__('leap::resource.image_quality'))
            // Each option carries the price of picking it.
            ->assertSeeInOrder(['Low', '0.039', 'High', '0.211'], escape: false);
    }

    public function test_showing_and_recording_costs_are_separate_switches(): void
    {
        config(['leap.ai.image.presets' => ['cheap' => 'gemini-2.5-flash-image']]);
        $this->fakeBothProviders();

        // Hidden in the panel, still recorded on the row.
        config(['leap.ai.show_costs' => false]);
        $editor = $this->editor();

        $this->assertNull($editor->aiImagePresets()['cheap']['estimate']);
        $this->assertNull($editor->aiImageEstimate());

        $generated = $editor->generateImage('A red bicycle', '16:9');
        $this->assertNull($generated['cost']);

        $editor->useGeneratedImage('image', $generated['token']);
        $media = Media::where('file_name', 'article-resources/a-red-bicycle.jpg')->first();
        $this->assertEqualsWithDelta(0.038703, $media->meta['ai']['cost'], 0.0000001);
        $this->assertSame('gemini-2.5-flash-image', $media->meta['ai']['model']);

        // And the other way around: shown, but not kept.
        config(['leap.ai.show_costs' => true, 'leap.ai.record_costs' => false]);
        $editor = $this->editor();

        $this->assertSame(0.039, $editor->aiImageEstimate());

        $generated = $editor->generateImage('A blue bicycle', '16:9');
        $this->assertEqualsWithDelta(0.038703, $generated['cost'], 0.0000001);

        $editor->useGeneratedImage('image', $generated['token']);
        $media = Media::where('file_name', 'article-resources/a-blue-bicycle.jpg')->first();
        $this->assertArrayNotHasKey('cost', $media->meta['ai']);
    }
}
