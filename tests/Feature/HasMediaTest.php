<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Models\Mediable;
use NickDeKruijk\Leap\Tests\Fixtures\MediaModel;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * HasMedia is what a project's own models use to read what the file manager
 * attached. Everything hangs off mediable_attribute: one model can carry a
 * header image, a gallery and a downloads list at once, and asking for one must
 * never return another's files.
 */
class HasMediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('media_models', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
        });

        Storage::fake('public');
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

    private function attach(MediaModel $model, string $file, string $attribute, int $sort = 0, ?array $meta = null): Media
    {
        Storage::disk('public')->put($file, $this->pngBytes());

        $media = Media::forFile($file);
        if ($meta !== null) {
            $media->meta = array_merge($media->meta ?? [], $meta);
            $media->save();
        }

        Mediable::create([
            'media_id' => $media->id,
            'mediable_type' => $model->getMorphClass(),
            'mediable_id' => $model->id,
            'mediable_attribute' => $attribute,
            'sort' => $sort,
        ]);

        return $media;
    }

    public function test_media_for_returns_only_the_requested_attribute(): void
    {
        $model = MediaModel::create(['title' => 'Post']);
        $this->attach($model, 'header.png', 'header');
        $this->attach($model, 'gallery-1.png', 'gallery');
        $this->attach($model, 'gallery-2.png', 'gallery', 1);

        $this->assertCount(1, $model->mediaFor('header'));
        $this->assertCount(2, $model->mediaFor('gallery'));
    }

    public function test_an_attribute_with_nothing_attached_is_empty_rather_than_null(): void
    {
        $model = MediaModel::create(['title' => 'Post']);

        $this->assertCount(0, $model->mediaFor('header'));
        $this->assertNull($model->mediaFile('header'));
        $this->assertNull($model->mediaAsset('header'));
        $this->assertSame('', $model->mediaAlt('header'));
    }

    public function test_media_file_returns_the_first_file_name(): void
    {
        $model = MediaModel::create(['title' => 'Post']);
        $this->attach($model, 'header.png', 'header');

        $this->assertSame('header.png', $model->mediaFile('header'));
    }

    /**
     * The asset url is prefixed with storage/ by default, which is where the
     * file manager writes and where Laravel's storage:link points.
     */
    public function test_media_asset_builds_a_public_url(): void
    {
        $model = MediaModel::create(['title' => 'Post']);
        $this->attach($model, 'header.png', 'header');

        $this->assertSame(asset('storage/header.png'), $model->mediaAsset('header'));
    }

    /**
     * A space or a comma in a file name has to come out as a URL that reads back
     * as one, or the link is broken in the markup while the file is fine.
     */
    public function test_media_asset_encodes_the_file_name(): void
    {
        $model = MediaModel::create(['title' => 'Post']);
        $this->attach($model, 'kop, met komma.png', 'header');

        $this->assertSame(asset('storage/kop%2C%20met%20komma.png'), $model->mediaAsset('header'));
    }

    /**
     * A project storing its uploads elsewhere overrides the prefix on the model
     * rather than rewriting every template.
     */
    public function test_the_asset_prefix_can_be_overridden_per_model(): void
    {
        $model = new class extends MediaModel
        {
            protected $table = 'media_models';

            public $mediaAssetPrefix = 'uploads/';
        };
        $model->title = 'Post';
        $model->save();

        $this->attach($model, 'header.png', 'header');

        $this->assertSame(asset('uploads/header.png'), $model->fresh()->mediaAsset('header'));
    }

    public function test_media_alt_reads_the_alt_text_stored_by_the_file_manager(): void
    {
        $model = MediaModel::create(['title' => 'Post']);
        $this->attach($model, 'header.png', 'header', meta: ['alt' => 'A header image']);

        $this->assertSame('A header image', $model->mediaAlt('header'));
    }

    /**
     * Alt text is translatable, so a multilingual site asks for the locale it is
     * rendering rather than whatever was typed first.
     */
    public function test_media_alt_is_locale_aware(): void
    {
        config()->set('leap.locales', ['nl' => 'Nederlands', 'en' => 'English']);

        $model = MediaModel::create(['title' => 'Post']);
        $this->attach($model, 'header.png', 'header', meta: [
            'alt' => ['nl' => 'Een koptekstafbeelding', 'en' => 'A header image'],
        ]);

        $this->assertSame('Een koptekstafbeelding', $model->mediaAlt('header', 'nl'));
        $this->assertSame('A header image', $model->mediaAlt('header', 'en'));
    }

    /**
     * The pivot is a shared morph table: two models can point at the same Media
     * row, and deleting one link must not disturb the other.
     */
    public function test_two_models_can_share_one_media_row(): void
    {
        $first = MediaModel::create(['title' => 'First']);
        $second = MediaModel::create(['title' => 'Second']);

        $media = $this->attach($first, 'shared.png', 'header');
        Mediable::create([
            'media_id' => $media->id,
            'mediable_type' => $second->getMorphClass(),
            'mediable_id' => $second->id,
            'mediable_attribute' => 'header',
            'sort' => 0,
        ]);

        $this->assertSame('shared.png', $first->mediaFile('header'));
        $this->assertSame('shared.png', $second->mediaFile('header'));

        Mediable::where('mediable_id', $first->id)->delete();

        $this->assertNull($first->fresh()->mediaFile('header'));
        $this->assertSame('shared.png', $second->fresh()->mediaFile('header'));
    }

    public function test_the_mediable_pivot_uses_the_configured_table_prefix(): void
    {
        $this->assertSame(config('leap.table_prefix').'mediables', (new Mediable)->getTable());
    }

    public function test_the_mediable_pivot_belongs_to_its_media_row(): void
    {
        $model = MediaModel::create(['title' => 'Post']);
        $media = $this->attach($model, 'header.png', 'header');

        $pivot = Mediable::where('media_id', $media->id)->first();

        $this->assertSame($media->id, $pivot->media->id);
        $this->assertSame('header.png', $pivot->media->file_name);
    }

    /**
     * A gallery comes out in the order the editor dragged it into, not the order the
     * database happens to return.
     *
     * The two are the same until two models share a file. Media rows are keyed on the
     * file's sha256, so a photo already used by an older model keeps that model's lower
     * id, and ordering by id alone floats it to the front. Here the second attachment
     * reuses the first file, which is exactly that case: sort says it comes second and
     * the id says it comes first.
     *
     * It matters beyond tidiness because the frontend shows the first image of a model
     * as its card: a gallery that merely looks shuffled puts the wrong picture on every
     * overview that lists it.
     */
    public function test_media_comes_back_in_the_order_it_was_sorted_in(): void
    {
        $model = MediaModel::create(['title' => 'Post']);

        $shared = $this->attach($model, 'shared.png', 'gallery', 2);
        $own = $this->attach($model, 'own.png', 'gallery', 1);

        $this->assertTrue($model->mediaFor('gallery')->first()->is($own),
            'The lower sort comes first, even though the other row has the lower media id.');
        $this->assertTrue($model->mediaFor('gallery')->last()->is($shared));
    }

    /**
     * Equal sorts still have to resolve to one settled order, or the same page renders
     * differently on two requests and a card changes picture on reload. The media id
     * breaks the tie, so the row attached first stays first.
     */
    public function test_an_equal_sort_still_has_a_settled_order(): void
    {
        $model = MediaModel::create(['title' => 'Post']);

        $first = $this->attach($model, 'a.png', 'gallery');
        $this->attach($model, 'b.png', 'gallery');

        $this->assertSame('a.png', $model->mediaFor('gallery')->first()->file_name);
        $this->assertSame('a.png', $model->fresh()->mediaFor('gallery')->first()->file_name);
        $this->assertSame($first->id, $model->fresh()->mediaFor('gallery')->first()->id);
    }
}
