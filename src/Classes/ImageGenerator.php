<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Models\Media;
use ReflectionClass;

/**
 * The pipeline behind the AI image button: prompt in, a stored, normalised JPEG and
 * its Media record out. Shared by the editor (generating from a section's own content)
 * and the file manager (a free-form prompt into the open folder), so the provider call,
 * the normalisation and the naming exist once.
 *
 * Nothing here writes to storage on its own — generate() only produces bytes. They are
 * held by the caller until the editor accepts the result, because a generation costs
 * money and a rejected image should not leave a file behind.
 */
class ImageGenerator
{
    /**
     * Generate an image and return the bytes ready to be stored, together with what
     * the call cost (null when the model has no configured rates).
     *
     * @return array{data: string, extension: string, cost: float|null, model: string|null}
     */
    public static function generate(string $prompt, string $aspect = '16:9'): array
    {
        $task = AiTask::for('image');

        // Generating an image routinely takes 20-40 seconds, well past PHP's default
        // 30 second ceiling for web requests. Without this the worker is killed while
        // still waiting on the provider — a 502 from the web server, with nothing in
        // the log to explain it, because the process never got to write one.
        set_time_limit((int) config('leap.ai.timeout', 60) + 30);

        if ($style = config('leap.ai.image.style')) {
            $prompt = trim($prompt).' '.$style;
        }

        $image = $task->image($prompt, $aspect);

        return [
            ...self::normalize($image['mime'], $image['data'], $aspect),
            'cost' => $task->cost($image['usage']) ?? $task->estimatedCost(),
            'model' => $task->model,
        ];
    }

    /**
     * Bring provider output to one predictable shape: a JPEG at exactly the requested
     * aspect ratio, no wider than leap.ai.image.max_width. Providers only offer a
     * handful of canvas sizes, so cropping here is what makes the ratio picker exact.
     *
     * Vector output is left alone — there is nothing to crop or re-encode, and a
     * future SVG-capable provider should not be squashed into a bitmap.
     *
     * @return array{data: string, extension: string}
     */
    private static function normalize(string $mime, string $data, string $aspect): array
    {
        if (str_contains($mime, 'svg')) {
            return ['data' => $data, 'extension' => 'svg'];
        }

        $image = Media::imageManager()->read($data);

        [$aspectWidth, $aspectHeight] = self::ratio($aspect);
        $width = min((int) config('leap.ai.image.max_width', 1600), $image->width());
        $height = (int) round($width * $aspectHeight / $aspectWidth);

        return [
            'data' => (string) $image->coverDown($width, $height)
                ->toJpeg(quality: (int) config('leap.ai.image.jpeg_quality', 82)),
            'extension' => 'jpg',
        ];
    }

    /**
     * An aspect ratio string as a [width, height] pair, falling back to square.
     *
     * @return array{int|float, int|float}
     */
    private static function ratio(string $aspect): array
    {
        [$width, $height] = array_pad(array_map('floatval', explode(':', $aspect)), 2, 0);

        return $width > 0 && $height > 0 ? [$width, $height] : [1, 1];
    }

    /**
     * Park a generated image until the editor accepts it, and return the token that
     * fetches it back. Nothing is written to the disk in between, so a result that is
     * rejected leaves no file behind.
     *
     * The bytes are base64-encoded on the way in. A cache store is not a binary-safe
     * place: the database driver keeps its value in a utf8mb4 text column, which
     * rejects raw JPEG outright ("Incorrect string value" on insert). Encoding costs
     * a third in size and makes the payload safe on every driver.
     *
     * @param  array{data: string, extension: string, cost: float|null, model: string|null}  $image
     */
    public static function park(array $image, string $prompt): string
    {
        $token = (string) Str::uuid();

        Cache::put('leap-ai-image:'.$token, [
            ...$image,
            'data' => base64_encode($image['data']),
            'prompt' => $prompt,
        ], now()->addMinutes(15));

        return $token;
    }

    /**
     * Fetch a parked image back for accepting, or null when the token is unknown or
     * its 15 minutes are up. Single use: the entry is removed as it is read.
     *
     * @return array{data: string, extension: string, cost: float|null, model: string|null, prompt: string}|null
     */
    public static function unpark(string $token): ?array
    {
        $image = Cache::pull('leap-ai-image:'.$token);

        if (! is_array($image)) {
            return null;
        }

        return [...$image, 'data' => (string) base64_decode($image['data'], true)];
    }

    /**
     * Store generated bytes on the file manager disk and return the Media record.
     * The name is derived from the prompt so the file manager stays readable, and an
     * existing name gets the same -2, -3 suffix the crop-as-new flow uses.
     *
     * @param  array{model?: string|null, cost?: float|null}  $meta  Recorded on the media row
     */
    public static function store(string $data, string $extension, string $folder, string $prompt, array $meta = []): Media|false
    {
        $storage = Storage::disk(config('leap.filemanager.disk'));

        $base = Str::slug(Str::words($prompt, 6, '')) ?: 'image';
        $folder = trim($folder, '/');
        $path = ($folder ? $folder.'/' : '').$base.'.'.$extension;

        $counter = 2;
        while ($storage->exists($path)) {
            $path = ($folder ? $folder.'/' : '').$base.'-'.$counter.'.'.$extension;
            $counter++;
        }

        $storage->put($path, $data);

        $media = Media::forFile($path);

        if ($media) {
            $media->meta = array_merge($media->meta ?? [], [
                'ai' => array_filter([
                    'model' => $meta['model'] ?? null,
                    'prompt' => $prompt,
                    'cost' => $meta['cost'] ?? null,
                    'generated_at' => (string) now(),
                    'user_id' => Auth::user()?->id,
                ], fn ($value) => $value !== null),
            ]);
            $media->save();
        }

        return $media;
    }

    /**
     * Where a module's generated images live: leap.ai.image.folder with {module}
     * replaced by the module's own folder name.
     *
     * Deliberately not NavigationItem::getSlug() — that runs the module title through
     * __(), so the folder would be named 'paginas' or 'pages' depending on the admin
     * language the editor happened to be using, scattering one module's images over
     * two folders. The class name is stable.
     */
    public static function folderFor(?string $module): string
    {
        $folder = (string) config('leap.ai.image.folder', '{module}');

        if (! str_contains($folder, '{module}')) {
            return $folder;
        }

        $name = '';

        if ($module) {
            // Read the module's own $slug without constructing it (a Module constructor
            // switches the auth guard); fall back to its pluralised class name.
            $slug = class_exists($module)
                ? (new ReflectionClass($module))->getDefaultProperties()['slug'] ?? null
                : null;

            $name = $slug ?: Str::slug(Str::plural(Str::kebab(class_basename($module))));
        }

        return trim(str_replace('{module}', $name, $folder), '/');
    }

    /**
     * Alt text for an image, per configured locale, from the alt_text task.
     *
     * Shared by the file manager's ✨ button and the automatic pass after generating
     * an image, so both ask in exactly the same words. Returns a [locale => text] map
     * limited to the configured locales, or an empty array when the task is off or
     * the file is not a raster image. Provider errors propagate; the caller decides
     * whether that is fatal.
     *
     * @return array<string, string>
     */
    public static function describe(Media|false|null $media): array
    {
        $task = AiTask::for('alt_text');

        if (! $media || ! $media->isBitmap() || ! $task->enabled()) {
            return [];
        }

        $locales = config('leap.locales') ?? [app()->getLocale() => ''];

        $data = base64_encode(Storage::disk($media->disk ?: config('leap.filemanager.disk'))->get($media->file_name));
        $prompt = 'Write alt text for screen-reader users, one per language. Describe only the main '
            .'subject and its purpose, in the shortest phrase that is still complete. Omit '
            .'decorative background, colours, lighting and styling unless they are essential to the '
            .'meaning. Most images need only a few words; add detail only when a complex image '
            .'(chart, diagram, busy scene) truly requires it. Do not start with "image of" or '
            .'"photo of". Return ONLY a JSON object mapping locale code to alt text. Languages: '
            .collect($locales)->map(fn ($name, $code) => trim("$code $name"))->implode(', ');

        $reply = $task->prompt($prompt, [['mime' => $media->mime_type, 'data' => $data]], json: true);

        // Some providers (e.g. Claude) wrap the JSON in a ```json code fence despite
        // the instruction, so extract the object first.
        $decoded = preg_match('/\{.*\}/s', $reply, $match) ? json_decode($match[0], true) : null;

        return array_map('strval', array_intersect_key(is_array($decoded) ? $decoded : [], $locales));
    }

    /**
     * Generate alt text for a freshly created image and store it on the media row,
     * when leap.ai.image.alt_text is on. Best effort: a failing alt text must not
     * lose the image that was just paid for.
     */
    public static function describeAndStore(Media|false|null $media): void
    {
        if (! $media || ! config('leap.ai.image.alt_text')) {
            return;
        }

        try {
            if ($texts = self::describe($media)) {
                $media->meta = array_merge($media->meta ?? [], ['alt' => $texts]);
                $media->save();
            }
        } catch (\Throwable $e) {
            // The image is stored and usable; the alt text can be added by hand.
        }
    }
}
