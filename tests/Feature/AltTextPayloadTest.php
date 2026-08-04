<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Classes\ImageGenerator;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\TestCase;
use Throwable;

/**
 * What the alt text task actually puts on the wire.
 *
 * A photo straight off a camera does not fit: providers cap an image at a few
 * megabytes once base64 encoded, and base64 adds a third to whatever is on disk. The
 * original was sent untouched, so an 8064 pixel drone photo was refused before
 * anything was described — and the failure looked exactly like a missing API key.
 */
class AltTextPayloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config([
            'leap.filemanager.disk' => 'public',
            'leap.locales' => null,
            'leap.ai.alt_text.provider' => 'claude',
            'leap.ai.providers.claude.api_key' => 'test-key',
        ]);
    }

    /**
     * Http::fake() keeps the first stub that matches, so the reply is registered per
     * test rather than in setUp — otherwise no test could ask for a failure.
     */
    private function fakeReply(int $status = 200): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(
                $status === 200 ? ['content' => [['text' => '{"en": "A description"}']]] : null,
                $status,
            ),
        ]);
    }

    private function image(int $width, int $height, string $format = 'jpeg'): Media
    {
        $image = Media::imageManager()->create($width, $height);
        $data = $format === 'png' ? (string) $image->toPng() : (string) $image->toJpeg();
        $path = "photo-$width-$height.$format";

        Storage::disk('public')->put($path, $data);

        return Media::forFile($path, 'public');
    }

    /**
     * The image as the provider received it: mime and pixel size.
     *
     * @return array{mime: string, width: int, height: int, bytes: int}
     */
    private function sent(): array
    {
        $request = Http::recorded()[0][0];
        $source = $request['messages'][0]['content'][0]['source'];
        $decoded = base64_decode($source['data']);
        $image = Media::imageManager()->read($decoded);

        return [
            'mime' => $source['media_type'],
            'width' => $image->width(),
            'height' => $image->height(),
            'bytes' => strlen($source['data']),
        ];
    }

    public function test_a_wide_image_is_scaled_down_to_the_configured_bound(): void
    {
        config(['leap.ai.alt_text.max_width' => 500]);

        $this->fakeReply();

        ImageGenerator::describe($this->image(2000, 1500));

        $sent = $this->sent();
        $this->assertSame(500, $sent['width']);
        $this->assertSame(375, $sent['height']);
    }

    /**
     * The bug a width-only cap would leave in: a portrait photo of 6048 pixels is just
     * as far over the limit as a landscape one, and scaleDown(width:) would pass it
     * through untouched.
     */
    public function test_a_tall_image_is_bounded_by_its_height(): void
    {
        config(['leap.ai.alt_text.max_width' => 500]);

        $this->fakeReply();

        ImageGenerator::describe($this->image(600, 2000));

        $sent = $this->sent();
        $this->assertSame(500, $sent['height']);
        $this->assertSame(150, $sent['width']);
    }

    public function test_a_small_image_is_not_enlarged(): void
    {
        config(['leap.ai.alt_text.max_width' => 500]);

        $this->fakeReply();

        ImageGenerator::describe($this->image(300, 200));

        $sent = $this->sent();
        $this->assertSame(300, $sent['width']);
        $this->assertSame(200, $sent['height']);
    }

    /**
     * Transparency is lost in the copy that travels, which is of no consequence for
     * describing a picture — and the file on disk is not touched either way.
     */
    public function test_the_copy_that_travels_is_always_a_jpeg(): void
    {
        config(['leap.ai.alt_text.max_width' => 500]);

        $this->fakeReply();

        ImageGenerator::describe($this->image(800, 600, 'png'));

        $this->assertSame('image/jpeg', $this->sent()['mime']);
    }

    /**
     * For a provider without this limit, or a site that would rather pay for the
     * detail.
     */
    public function test_null_sends_the_original_untouched(): void
    {
        config(['leap.ai.alt_text.max_width' => null]);

        $this->fakeReply();

        $media = $this->image(800, 600, 'png');
        ImageGenerator::describe($media);

        $sent = $this->sent();
        $this->assertSame('image/png', $sent['mime']);
        $this->assertSame(800, $sent['width']);
    }

    /**
     * The number is not arbitrary: above it the vision models resize server-side, so
     * anything larger is paid for and thrown away.
     */
    public function test_the_default_bound_is_the_size_providers_resize_to(): void
    {
        $this->assertSame(1568, config('leap.ai.alt_text.max_width'));
    }

    /**
     * The point of the whole exercise: a drone photo now fits. 8064 pixels at the size
     * this test can afford to render — the scaling is the same code either way.
     */
    public function test_a_camera_sized_photo_ends_up_well_under_the_five_megabyte_cap(): void
    {
        config(['leap.ai.alt_text.max_width' => 1568]);

        $this->fakeReply();

        ImageGenerator::describe($this->image(4032, 3024));

        $sent = $this->sent();
        $this->assertSame(1568, $sent['width']);
        $this->assertLessThan(5 * 1024 * 1024, $sent['bytes']);
    }

    /**
     * A failing suggestion must not cost the image that was just generated, so it stays
     * caught — but it used to leave no trace at all, and "it failed" is not something
     * anyone can act on.
     */
    public function test_a_provider_error_is_reported_rather_than_swallowed(): void
    {
        config(['leap.ai.alt_text.max_width' => 500, 'leap.ai.image.alt_text' => true]);

        $this->fakeReply(400);

        $handler = new class implements ExceptionHandler
        {
            /** @var array<int, Throwable> */
            public array $reported = [];

            public function report(Throwable $e): void
            {
                $this->reported[] = $e;
            }

            public function shouldReport(Throwable $e): bool
            {
                return true;
            }

            public function render($request, Throwable $e): mixed
            {
                return null;
            }

            public function renderForConsole($output, Throwable $e): void {}
        };

        $this->app->instance(ExceptionHandler::class, $handler);

        // Not a fatal: the image is stored and usable, the alt text can be typed by hand.
        ImageGenerator::describeAndStore($this->image(300, 200));

        $this->assertCount(1, $handler->reported);
        $this->assertStringContainsString('Claude request failed', $handler->reported[0]->getMessage());
    }
}
