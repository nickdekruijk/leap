<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Models\Media;
use ReflectionClass;
use RuntimeException;

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
     * The presets from leap.ai.image.presets as the tasks they name, in config order and
     * keyed by their own name, limited to the ones that can actually run — a preset
     * naming a model whose provider has no API key is simply not offered.
     *
     * A preset says what to ask the provider for: a model id with an optional ':quality'
     * suffix. How the answer is stored (max_width, jpeg_quality) is post-processing and
     * stays global — the provider never sees it.
     *
     * An empty or absent list falls back to a single preset built from the older
     * leap.ai.image provider/model/quality keys, which is what keeps a config written
     * before presets existed working unchanged — including having the feature off when
     * no provider was ever named.
     *
     * @return array<string, AiTask>
     */
    public static function presets(): array
    {
        $presets = config('leap.ai.image.presets') ?: ['' => null];

        return array_filter(
            array_map(self::preset(...), $presets),
            fn (AiTask $task) => $task->enabled(),
        );
    }

    /**
     * One configured preset value as the task it names: 'model' or 'model:quality'.
     * Null means the older leap.ai.image keys decide.
     */
    private static function preset(?string $value): AiTask
    {
        [$model, $quality] = array_pad(explode(':', (string) $value, 2), 2, null);

        return AiTask::forImage($model ?: null, $quality);
    }

    /**
     * Generate an image and return the bytes ready to be stored, together with what
     * the call cost (null when the model has no configured rates).
     *
     * The preset is looked up by key rather than taken as a model name, so a value
     * coming from the browser can only ever select something the config already
     * offers; an unknown one falls back to the first preset.
     *
     * @param  string  $orientation  'square', 'landscape' or 'portrait'
     * @return array{data: string, extension: string, cost: float|null, model: string|null, quality: string|null}
     */
    public static function generate(string $prompt, string $orientation = 'landscape', ?string $preset = null): array
    {
        $orientation = self::orientation($orientation);

        $presets = self::presets();
        $task = $presets[$preset] ?? reset($presets);

        if (! $task) {
            throw new RuntimeException('No usable image preset is configured');
        }

        // Generating an image routinely takes 20-40 seconds, well past PHP's default
        // 30 second ceiling for web requests. Without this the worker is killed while
        // still waiting on the provider — a 502 from the web server, with nothing in
        // the log to explain it, because the process never got to write one.
        set_time_limit((int) config('leap.ai.timeout', 60) + 30);

        if ($style = config('leap.ai.image.style')) {
            $prompt = trim($prompt).' '.$style;
        }

        $image = $task->image($prompt, $orientation);

        return [
            ...self::normalize($image['mime'], $image['data']),
            'cost' => $task->cost($image['usage']) ?? $task->estimatedCost(),
            'model' => $task->model,
            'quality' => $task->quality,
        ];
    }

    /**
     * Store provider output in one predictable format: a JPEG in the proportions the
     * model produced, no wider than leap.ai.image.max_width.
     *
     * Nothing is cropped. The model was asked for an orientation, not an exact ratio,
     * so its own framing is the composition someone judged in the preview — cutting a
     * strip off to force a ratio would throw away part of the image that was paid for.
     *
     * A null max_width keeps the model's own resolution, for a site that derives its
     * own sizes from the original. Everything else is unchanged: the image is still
     * re-encoded, because providers answer in PNG and that is several times the bytes
     * of the same picture as JPEG.
     *
     * Vector output is left alone — there is nothing to scale or re-encode, and a
     * future SVG-capable provider should not be squashed into a bitmap.
     *
     * @return array{data: string, extension: string}
     */
    private static function normalize(string $mime, string $data): array
    {
        if (str_contains($mime, 'svg')) {
            return ['data' => $data, 'extension' => 'svg'];
        }

        $image = Media::imageManager()->read($data);

        if ($maxWidth = (int) config('leap.ai.image.max_width', 1600)) {
            $image = $image->scaleDown(width: $maxWidth);
        }

        return [
            'data' => (string) $image->toJpeg(quality: (int) config('leap.ai.image.jpeg_quality', 82)),
            'extension' => 'jpg',
        ];
    }

    /**
     * An aspect ratio string ("16:9") as a [width, height] pair, falling back to square.
     *
     * @deprecated 1.1 The dialog asks for an orientation and the model's own framing is
     *             kept, so nothing parses ratios any more. Use orientation() instead.
     *
     * @return array{int|float, int|float}
     */
    public static function ratio(string $aspect): array
    {
        [$width, $height] = array_pad(array_map('floatval', explode(':', $aspect)), 2, 0);

        return $width > 0 && $height > 0 ? [$width, $height] : [1, 1];
    }

    /**
     * The orientation to ask for, as one of 'square', 'landscape' or 'portrait'.
     *
     * An aspect ratio string ("16:9") is accepted as well and reduced to its
     * orientation, so a call written before the picker offered orientations — or a
     * ratio still sitting in a project's own code — keeps working.
     */
    public static function orientation(string $value): string
    {
        if (in_array($value, ['square', 'landscape', 'portrait'], true)) {
            return $value;
        }

        [$width, $height] = array_pad(array_map('floatval', explode(':', $value)), 2, 0);

        return match (true) {
            $width <= 0 || $height <= 0, $width === $height => 'square',
            $width > $height => 'landscape',
            default => 'portrait',
        };
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
     * @param  array{data: string, extension: string, cost: float|null, model: string|null, quality: string|null}  $image
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
     * @return array{data: string, extension: string, cost: float|null, model: string|null, quality: string|null, prompt: string}|null
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
     * What made the image is recorded on the row: which model, at which quality, what
     * it was asked and by whom. The amount is only kept when leap.ai.record_costs is
     * on — that is a separate switch from showing it, so a panel that hides prices can
     * still report on what a month of generating cost.
     *
     * @param  array{model?: string|null, quality?: string|null, cost?: float|null}  $meta  Recorded on the media row
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
                    'quality' => $meta['quality'] ?? null,
                    'prompt' => $prompt,
                    'cost' => config('leap.ai.record_costs', true) ? $meta['cost'] ?? null : null,
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

        $decoded = AiTask::decodeReply($reply);

        return array_map('strval', array_intersect_key($decoded ?? [], $locales));
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
