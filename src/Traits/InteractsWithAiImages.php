<?php

namespace NickDeKruijk\Leap\Traits;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use NickDeKruijk\Leap\Classes\AiTask;
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
     * Whether the AI image generation feature is configured (provider + api key).
     */
    public function aiImageEnabled(): bool
    {
        return AiTask::for('image')->enabled();
    }

    /**
     * The indicative price of one generation, shown before the user commits to it.
     * Null when the configured model has no known rate — better nothing than a
     * wrong amount. See leap.ai.pricing.
     */
    public function aiImageEstimate(): ?float
    {
        return AiTask::for('image')->estimatedCost();
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
     * configured rate).
     *
     * @return array{token?: string, preview?: string, cost?: float|null}
     */
    public function generateImage(string $prompt, string $aspect): array
    {
        Leap::validatePermission($this->aiImagePermission());

        if (trim($prompt) === '' || ! $this->aiRateLimit()) {
            return [];
        }

        try {
            $image = ImageGenerator::generate($prompt, $aspect);
        } catch (\Throwable $e) {
            $this->dispatch('toast-error', __($this->aiLangFile().'.image_failed'))->to(Toasts::class);

            return [];
        }

        return [
            'token' => ImageGenerator::park($image, $prompt),
            'preview' => 'data:image/'.($image['extension'] === 'svg' ? 'svg+xml' : 'jpeg').';base64,'.base64_encode($image['data']),
            'cost' => $image['cost'],
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
