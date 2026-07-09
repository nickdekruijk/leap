<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A single configured AI task (e.g. 'alt_text', 'translate') bound to the
 * provider and model from config('leap.ai.<task>'). Provider-agnostic: the
 * same class backs image tasks (Gemini/Claude vision) and, later, text-only
 * translation (Gemini/Claude/DeepL).
 */
class AiTask
{
    public function __construct(
        public string $task,
        public ?string $provider,
        public ?string $model,
    ) {}

    /**
     * Build a task from its config entry, resolving the model default.
     */
    public static function for(string $task): self
    {
        $provider = config("leap.ai.$task.provider");

        return new self(
            $task,
            $provider,
            config("leap.ai.$task.model") ?: self::defaultModel($provider),
        );
    }

    /**
     * The task is usable when a provider is configured and it has an API key.
     */
    public function enabled(): bool
    {
        return $this->provider !== null
            && ! empty(config("leap.ai.providers.$this->provider.api_key"));
    }

    /**
     * Send a prompt (with optional images) to the task's provider and return
     * the model's raw text reply.
     *
     * @param  list<array{mime: string, data: string}>  $images  Base64-encoded images
     * @param  bool  $json  Request a JSON-formatted reply
     */
    public function prompt(string $text, array $images = [], bool $json = false): string
    {
        return match ($this->provider) {
            'gemini' => $this->callGemini($text, $images, $json),
            'claude' => $this->callClaude($text, $images, $json),
            'openai' => $this->callOpenai($text, $images, $json),
            'deepl' => $this->callDeepl($text, $images, $json),
            default => throw new RuntimeException("No AI provider configured for task '$this->task'"),
        };
    }

    /**
     * The sensible default model per provider, used when a task omits 'model'.
     */
    private static function defaultModel(?string $provider): ?string
    {
        return match ($provider) {
            'gemini' => 'gemini-2.5-flash',
            'claude' => 'claude-haiku-4-5',
            'openai' => 'gpt-4o-mini',
            default => null,
        };
    }

    private function apiKey(): string
    {
        return (string) config("leap.ai.providers.$this->provider.api_key");
    }

    /**
     * @param  list<array{mime: string, data: string}>  $images
     */
    private function callGemini(string $text, array $images, bool $json): string
    {
        $parts = [];
        foreach ($images as $image) {
            $parts[] = ['inline_data' => ['mime_type' => $image['mime'], 'data' => $image['data']]];
        }
        $parts[] = ['text' => $text];

        $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey()])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/$this->model:generateContent", [
                'contents' => [['parts' => $parts]],
                'generationConfig' => $json ? ['responseMimeType' => 'application/json'] : (object) [],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini request failed: '.$response->status());
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Gemini returned an empty response');
        }

        return $text;
    }

    /**
     * @param  list<array{mime: string, data: string}>  $images
     */
    private function callClaude(string $text, array $images, bool $json): string
    {
        $content = [];
        foreach ($images as $image) {
            $content[] = [
                'type' => 'image',
                'source' => ['type' => 'base64', 'media_type' => $image['mime'], 'data' => $image['data']],
            ];
        }
        $content[] = ['type' => 'text', 'text' => $text];

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey(),
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 1024,
            'messages' => [['role' => 'user', 'content' => $content]],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Claude request failed: '.$response->status());
        }

        $text = $response->json('content.0.text');
        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Claude returned an empty response');
        }

        return $text;
    }

    /**
     * @param  list<array{mime: string, data: string}>  $images
     */
    private function callOpenai(string $text, array $images, bool $json): string
    {
        $content = [['type' => 'text', 'text' => $text]];
        foreach ($images as $image) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => "data:{$image['mime']};base64,{$image['data']}"],
            ];
        }

        $body = [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $content]],
        ];
        if ($json) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withToken($this->apiKey())
            ->post('https://api.openai.com/v1/chat/completions', $body);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI request failed: '.$response->status());
        }

        $text = $response->json('choices.0.message.content');
        if (! is_string($text) || $text === '') {
            throw new RuntimeException('OpenAI returned an empty response');
        }

        return $text;
    }

    /**
     * DeepL (text-only translation) is added with the translate feature.
     *
     * @param  list<array{mime: string, data: string}>  $images
     */
    private function callDeepl(string $text, array $images, bool $json): string
    {
        throw new RuntimeException('DeepL provider is not implemented yet');
    }
}
