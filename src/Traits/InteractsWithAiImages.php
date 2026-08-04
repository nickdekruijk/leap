<?php

namespace NickDeKruijk\Leap\Traits;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Classes\ImageGenerator;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Toasts;
use NickDeKruijk\Leap\Models\Media;

/**
 * The shared AI image pipeline for Livewire components: generate for review
 * (park in cache, preview as data URI), then accept (store, describe). The
 * Editor and the FileManager differ only in permission, target folder, language
 * namespace and what they do with the accepted Media — those are the hooks.
 */
trait InteractsWithAiImages
{
    /**
     * The permission ability the AI image actions require.
     */
    abstract protected function aiImagePermission(): string;

    /**
     * The storage folder an accepted image is stored in.
     */
    abstract protected function aiImageFolder(): string;

    /**
     * The language file holding the component's AI messages
     * (ai_rate_limited, image_failed, image_expired).
     */
    abstract protected function aiLangFile(): string;

    /**
     * Whether the AI image generation feature is configured: at least one preset
     * whose provider has an api key.
     */
    public function aiImageEnabled(): bool
    {
        return ImageGenerator::presets() !== [];
    }

    /**
     * The presets offered in the generate dialog, as the view needs them: a label to
     * show and the indicative price of picking it. One preset means there is nothing
     * to choose and the dialog leaves the picker out altogether.
     *
     * The estimate is null when the model has no known rate, and for every preset when
     * leap.ai.show_costs is off — enforced here rather than in the view, so a price
     * cannot slip through a template that forgot to check.
     *
     * @return array<string, array{label: string, estimate: float|null}>
     */
    public function aiImagePresets(): array
    {
        $show = config('leap.ai.show_costs', true);
        $presets = [];

        foreach (ImageGenerator::presets() as $key => $task) {
            $presets[$key] = [
                'label' => $this->aiImagePresetLabel($key),
                'estimate' => $show ? $task->estimatedCost() : null,
            ];
        }

        return $presets;
    }

    /**
     * The label for a preset key: the translation for the common low/medium/high
     * names, and otherwise the key itself made readable, so a project may name its
     * presets whatever it likes without having to add translations for them.
     */
    protected function aiImagePresetLabel(string $key): string
    {
        $line = 'leap::resource.image_quality_'.$key;

        return Lang::has($line) ? __($line) : Str::headline($key);
    }

    /**
     * The indicative price of one generation, shown before the user commits to it.
     * Null when the model has no known rate (better nothing than a wrong amount) or
     * when leap.ai.show_costs is off. See leap.ai.pricing.
     */
    public function aiImageEstimate(): ?float
    {
        $presets = $this->aiImagePresets();

        return ($preset = reset($presets)) ? $preset['estimate'] : null;
    }

    /**
     * Rate-limit an AI action (paid third-party call) per user; toast + abort when
     * exceeded. Returns false when the caller should stop.
     */
    protected function aiRateLimit(): bool
    {
        try {
            $this->rateLimit((int) config('leap.ai.rate_limit', 30), method: 'ai');

            return true;
        } catch (TooManyRequestsException $e) {
            $this->dispatch('toast-error', __($this->aiLangFile().'.ai_rate_limited', ['seconds' => $e->secondsUntilAvailable]))->to(Toasts::class);

            return false;
        }
    }

    /**
     * Generate an image for review. Nothing is stored yet: the bytes are parked in
     * the cache under a one-off token and returned as a data URI for the preview,
     * so an image that is not accepted never leaves a file behind. Returns the
     * token, the preview and what the call cost (null when the model has no
     * configured rate, or when leap.ai.show_costs is off).
     *
     * The preset is the key of one of aiImagePresets(); an unknown one falls back to
     * the first, so the browser can never name a model of its own.
     *
     * @return array{token?: string, preview?: string, cost?: float|null}
     */
    public function generateImage(string $prompt, string $orientation, ?string $preset = null): array
    {
        Leap::validatePermission($this->aiImagePermission());

        if (trim($prompt) === '' || ! $this->aiRateLimit()) {
            return [];
        }

        try {
            $image = ImageGenerator::generate($prompt, $orientation, $preset);
        } catch (\Throwable $e) {
            $this->dispatch('toast-error', __($this->aiLangFile().'.image_failed'))->to(Toasts::class);

            return [];
        }

        return [
            'token' => ImageGenerator::park($image, $prompt),
            'preview' => 'data:image/'.($image['extension'] === 'svg' ? 'svg+xml' : 'jpeg').';base64,'.base64_encode($image['data']),
            'cost' => config('leap.ai.show_costs', true) ? $image['cost'] : null,
        ];
    }

    /**
     * Accept a generated image: unpark it, store it in the component's folder and
     * generate alt texts. Returns the Media, or null (with a toast) when the token
     * expired or storing failed — the caller decides what to do with the result.
     */
    protected function acceptGeneratedImage(string $token): ?Media
    {
        Leap::validatePermission($this->aiImagePermission());

        $image = ImageGenerator::unpark($token);

        if (! $image) {
            $this->dispatch('toast-error', __($this->aiLangFile().'.image_expired'))->to(Toasts::class);

            return null;
        }

        $media = ImageGenerator::store(
            $image['data'],
            $image['extension'],
            $this->aiImageFolder(),
            $image['prompt'],
            $image,
        );

        if (! $media) {
            $this->dispatch('toast-error', __($this->aiLangFile().'.image_failed'))->to(Toasts::class);

            return null;
        }

        ImageGenerator::describeAndStore($media);

        return $media;
    }
}
