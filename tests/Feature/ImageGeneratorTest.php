<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Classes\ImageGenerator;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * The parts of the AI image pipeline that do not call a provider: parsing the
 * aspect ratio, parking bytes between generating and accepting, and storing an
 * accepted image. Generation itself is a paid network call and is exercised
 * through the editor and file manager tests with a faked task.
 */
class ImageGeneratorTest extends TestCase
{
    public function test_an_aspect_ratio_is_parsed_into_a_pair(): void
    {
        $this->assertSame([16.0, 9.0], ImageGenerator::ratio('16:9'));
        $this->assertSame([4.0, 3.0], ImageGenerator::ratio('4:3'));
    }

    /**
     * Anything unparseable falls back to square rather than dividing by zero
     * further down, where the failure would surface as a broken image instead of
     * a sensible default.
     */
    public function test_a_nonsense_ratio_falls_back_to_square(): void
    {
        $this->assertSame([1, 1], ImageGenerator::ratio(''));
        $this->assertSame([1, 1], ImageGenerator::ratio('16:0'));
        $this->assertSame([1, 1], ImageGenerator::ratio('wide'));
        $this->assertSame([1, 1], ImageGenerator::ratio('-16:9'));
    }

    /**
     * Parking base64-encodes the bytes, because a cache store backed by the
     * database keeps its value in a utf8mb4 text column that rejects raw JPEG.
     * Round-tripping must return exactly what went in.
     */
    public function test_a_parked_image_round_trips_byte_for_byte(): void
    {
        $bytes = random_bytes(512);

        $token = ImageGenerator::park([
            'data' => $bytes,
            'extension' => 'jpg',
            'cost' => 0.04,
            'model' => 'test-model',
        ], 'a red bicycle');

        $parked = ImageGenerator::unpark($token);

        $this->assertSame($bytes, $parked['data']);
        $this->assertSame('jpg', $parked['extension']);
        $this->assertSame(0.04, $parked['cost']);
        $this->assertSame('test-model', $parked['model']);
        $this->assertSame('a red bicycle', $parked['prompt']);
    }

    /**
     * Single use: accepting the same token twice would store the image twice and
     * charge the generation once, so the entry is removed as it is read.
     */
    public function test_a_parked_image_can_only_be_taken_once(): void
    {
        $token = ImageGenerator::park([
            'data' => 'bytes',
            'extension' => 'jpg',
            'cost' => null,
            'model' => null,
        ], 'prompt');

        $this->assertNotNull(ImageGenerator::unpark($token));
        $this->assertNull(ImageGenerator::unpark($token));
    }

    public function test_an_unknown_token_returns_null_rather_than_failing(): void
    {
        $this->assertNull(ImageGenerator::unpark('not-a-token'));
    }

    public function test_storing_names_the_file_after_the_prompt(): void
    {
        Storage::fake('public');

        $media = ImageGenerator::store($this->pngBytes(), 'jpg', '', 'A red bicycle leaning on a wall somewhere', [
            'model' => 'test-model',
        ]);

        $this->assertInstanceOf(Media::class, $media);
        // Six words of the prompt, slugified, so the file manager stays readable.
        $this->assertSame('a-red-bicycle-leaning-on-a.jpg', $media->file_name);
        Storage::disk(config('leap.filemanager.disk'))->assertExists('a-red-bicycle-leaning-on-a.jpg');
    }

    /**
     * A second image from the same prompt must not overwrite the first — it gets
     * the same -2 suffix the crop-as-new flow uses.
     */
    public function test_a_second_image_from_the_same_prompt_does_not_overwrite(): void
    {
        Storage::fake('public');

        $first = ImageGenerator::store($this->pngBytes(), 'jpg', '', 'A red bicycle');
        $second = ImageGenerator::store($this->pngBytes(), 'jpg', '', 'A red bicycle');

        $this->assertSame('a-red-bicycle.jpg', $first->file_name);
        $this->assertNotSame($first->file_name, $second->file_name);
        Storage::disk(config('leap.filemanager.disk'))->assertExists($first->file_name);
        Storage::disk(config('leap.filemanager.disk'))->assertExists($second->file_name);
    }

    public function test_a_prompt_that_slugifies_to_nothing_still_produces_a_file(): void
    {
        Storage::fake('public');

        $media = ImageGenerator::store($this->pngBytes(), 'jpg', '', '!!! ???');

        $this->assertSame('image.jpg', $media->file_name);
    }

    public function test_storing_into_a_folder_keeps_the_path(): void
    {
        Storage::fake('public');

        $media = ImageGenerator::store($this->pngBytes(), 'jpg', 'ai/', 'A red bicycle');

        $this->assertSame('ai/a-red-bicycle.jpg', $media->file_name);
    }

    private function pngBytes(): string
    {
        $gd = imagecreatetruecolor(20, 20);
        ob_start();
        imagepng($gd);
        $bytes = ob_get_clean();
        imagedestroy($gd);

        return $bytes;
    }
}
