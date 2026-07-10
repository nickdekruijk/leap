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
            'deepl' => throw new RuntimeException('DeepL supports translation only; use translate()'),
            default => throw new RuntimeException("No AI provider configured for task '$this->task'"),
        };
    }

    /**
     * Translate a map of values into $to (locale code), optionally from $from.
     * Keys are preserved; HTML markup is kept intact. Returns [key => translated].
     *
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    public function translate(array $values, string $to, ?string $from = null): array
    {
        if ($values === []) {
            return [];
        }

        if ($this->provider === 'deepl') {
            return $this->deeplTranslate($values, $to, $from);
        }

        // Chat providers (gemini/claude/openai): one JSON round-trip.
        $prompt = 'Translate the string values of this JSON object'
            .($from ? ' from '.$this->languageName($from) : '')
            .' to '.$this->languageName($to).'. Preserve all HTML markup exactly — every tag, '
            .'attribute, link URL (href), image src, list and table structure must stay identical. '
            .'Translate only the human-readable text between tags (and visible link text); never '
            .'change tag names, attribute values, or URLs. '
            .'Return ONLY a JSON object with the same keys and translated values. JSON: '
            .json_encode($values, JSON_UNESCAPED_UNICODE);

        $reply = $this->prompt($prompt, [], json: true);

        // Some providers wrap the JSON in a ```json fence; extract the object.
        $decoded = preg_match('/\{.*\}/s', $reply, $match) ? json_decode($match[0], true) : null;

        // Keep the original value for any key the model dropped.
        return array_map('strval', array_merge($values, array_intersect_key(is_array($decoded) ? $decoded : [], $values)));
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
            ->connectTimeout(10)->timeout((int) config('leap.ai.timeout', 60))
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
        ])->connectTimeout(10)->timeout((int) config('leap.ai.timeout', 60))
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                // Cap the reply; the default is generous so long translations aren't
                // silently truncated. Override per task with leap.ai.<task>.max_tokens.
                'max_tokens' => (int) config("leap.ai.$this->task.max_tokens", 8192),
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
            ->connectTimeout(10)->timeout((int) config('leap.ai.timeout', 60))
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
     * DeepL translation (text-only, form-encoded, order-based). Keeps HTML via
     * tag_handling=html. Free keys (suffixed ':fx') use the api-free host.
     *
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function deeplTranslate(array $values, string $to, ?string $from): array
    {
        // tag_handling=html HTML-encodes entities, which corrupts plain-text
        // fields ("A & B" → "A &amp; B"), so split by whether the value holds
        // markup and only enable HTML handling for the ones that do.
        $html = [];
        $plain = [];
        foreach ($values as $key => $value) {
            if ($value !== strip_tags($value)) {
                $html[$key] = $value;
            } else {
                $plain[$key] = $value;
            }
        }

        $translated = ($plain ? $this->deeplRequest($plain, $to, $from, false) : [])
            + ($html ? $this->deeplRequest($html, $to, $from, true) : []);

        // Preserve the original key order.
        $out = [];
        foreach ($values as $key => $value) {
            $out[$key] = $translated[$key] ?? $value;
        }

        return $out;
    }

    /**
     * One DeepL request for a batch of values (all HTML or all plain).
     *
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function deeplRequest(array $values, string $to, ?string $from, bool $html): array
    {
        $key = $this->apiKey();
        $host = str_ends_with($key, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com';

        $response = Http::asForm()
            ->withHeaders(['Authorization' => 'DeepL-Auth-Key '.$key])
            ->connectTimeout(10)->timeout((int) config('leap.ai.timeout', 60))
            ->post("$host/v2/translate", array_filter([
                'text' => array_values($values),
                // Target accepts regional variants (EN-GB); source must be the
                // plain language (EN), so DeepL rejects a regional source_lang.
                'target_lang' => $this->deeplLang($to),
                'source_lang' => $from ? strtoupper(explode('-', $from)[0]) : null,
                'tag_handling' => $html ? 'html' : null,
            ]));

        if ($response->failed()) {
            throw new RuntimeException('DeepL request failed: '.$response->status());
        }

        $translations = $response->json('translations');
        if (! is_array($translations) || count($translations) !== count($values)) {
            throw new RuntimeException('DeepL returned an unexpected response');
        }

        // Zip the ordered results back onto the original keys.
        return array_combine(array_keys($values), array_map(fn ($t) => (string) ($t['text'] ?? ''), $translations));
    }

    /**
     * English language name for a locale code, used in chat-provider prompts.
     * Falls back to the raw code (models understand ISO codes too).
     */
    private function languageName(string $code): string
    {
        return [
            'nl' => 'Dutch',
            'en' => 'English',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
            'it' => 'Italian',
            'pt' => 'Portuguese',
        ][strtolower($code)] ?? $code;
    }

    /**
     * DeepL target/source language code. DeepL rejects bare EN/PT as a target,
     * so map those to a regional variant; otherwise uppercase the locale.
     */
    private function deeplLang(string $code): string
    {
        return [
            'en' => 'EN-GB',
            'pt' => 'PT-PT',
        ][strtolower($code)] ?? strtoupper($code);
    }
}
