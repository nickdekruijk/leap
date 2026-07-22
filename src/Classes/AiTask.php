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
            config("leap.ai.$task.model") ?: self::defaultModel($provider, $task),
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
            // Unescaped slashes as well: without it the prompt shows the model <\/p>,
            // and a model told to preserve the markup exactly hands that back verbatim
            .json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $reply = $this->prompt($prompt, [], json: true);

        // Some providers wrap the JSON in a ```json fence; extract the object.
        $decoded = preg_match('/\{.*\}/s', $reply, $match) ? json_decode($match[0], true) : null;

        // Keep the original value for any key the model dropped.
        return array_map('strval', array_merge($values, array_intersect_key(is_array($decoded) ? $decoded : [], $values)));
    }

    /**
     * Generate an image from a text prompt.
     *
     * Returns the bytes together with their mime type rather than bare bytes, so a
     * vector-capable provider can be added later without changing the signature, and
     * the token usage the provider reported so the caller can price the call.
     *
     * @return array{mime: string, data: string, usage: array{input: int, output: int}|null}
     */
    public function image(string $prompt, string $aspect = '16:9'): array
    {
        return match ($this->provider) {
            'gemini' => $this->imageGemini($prompt, $aspect),
            'openai' => $this->imageOpenai($prompt, $aspect),
            default => throw new RuntimeException("Provider '$this->provider' cannot generate images"),
        };
    }

    /**
     * What a call actually cost in US dollars, from the per-million-token rates in
     * leap.ai.pricing. Null when the model has no configured rates or the provider
     * reported no usage — the caller then shows nothing rather than a wrong zero.
     *
     * @param  array{input: int, output: int}|null  $usage
     */
    public function cost(?array $usage): ?float
    {
        $rates = $this->rates();

        if (! $usage || ! isset($rates['input']) && ! isset($rates['output'])) {
            return null;
        }

        return ($usage['input'] ?? 0) / 1000000 * ($rates['input'] ?? 0)
            + ($usage['output'] ?? 0) / 1000000 * ($rates['output'] ?? 0);
    }

    /**
     * The indicative price of one call, shown before the user commits to it. A flat
     * per-call figure rather than a token count to multiply, because the output size
     * is deterministic per model.
     *
     * Where a provider charges by quality the figure is per quality, and an unset or
     * 'auto' quality quotes the dearest of them: the provider is then free to pick
     * whichever it likes, and an estimate that can be exceeded is worse than a
     * generous one. The exact amount follows right after generating anyway.
     */
    public function estimatedCost(): ?float
    {
        $estimate = $this->rates()['estimate'] ?? null;

        if (is_array($estimate)) {
            $estimate = $estimate[config('leap.ai.image.quality')] ?? ($estimate ? max($estimate) : null);
        }

        return $estimate === null ? null : (float) $estimate;
    }

    /**
     * What each model costs, in US dollars per million tokens, with 'estimate' the
     * indicative price of a single image shown before generating.
     *
     * These live here rather than in the published config on purpose: a copy in an
     * application's config file freezes the prices of the day it was published and
     * silently goes stale, while leap.ai.pricing stays available to override any of
     * them. Not billed amounts — check them against the provider's pricing page.
     * Checked 2026-07-21.
     *
     * @var array<string, array<string, float>>
     */
    private const DEFAULT_PRICING = [
        // Gemini has no quality setting: one image is one price.
        'gemini-2.5-flash-image' => ['input' => 0.30, 'output' => 30.00, 'estimate' => 0.039],
        'gemini-3.1-flash-lite-image' => ['input' => 0.30, 'output' => 30.00, 'estimate' => 0.039],
        'gemini-3.1-flash-image' => ['input' => 0.30, 'output' => 60.00, 'estimate' => 0.078],
        'gemini-3-pro-image' => ['input' => 0.30, 'output' => 120.00, 'estimate' => 0.156],
        // OpenAI charges by quality, up to 35x between low and high, so one figure per
        // model would be meaningless. Each is the dearer of the square and the portrait
        // or landscape canvas, so the estimate is never lower than what is charged.
        'gpt-image-1-mini' => [
            'input' => 2.00, 'output' => 8.00,
            'estimate' => ['low' => 0.006, 'medium' => 0.015, 'high' => 0.052],
        ],
        'gpt-image-1.5' => [
            'input' => 5.00, 'output' => 32.00,
            'estimate' => ['low' => 0.013, 'medium' => 0.050, 'high' => 0.200],
        ],
        'gpt-image-2' => [
            'input' => 5.00, 'output' => 30.00,
            'estimate' => ['low' => 0.006, 'medium' => 0.053, 'high' => 0.211],
        ],
        'gpt-image-1' => [ // superseded; per-image prices published, no token rates listed
            'estimate' => ['low' => 0.016, 'medium' => 0.063, 'high' => 0.250],
        ],
    ];

    /**
     * The rates for this task's model: leap.ai.pricing when it names the model,
     * otherwise the shipped default. Read from the array rather than with a config()
     * dot-path because model names contain dots themselves ('gemini-2.5-flash-image'),
     * which dot notation would read as nesting.
     *
     * @return array<string, float>
     */
    private function rates(): array
    {
        return (array) ((config('leap.ai.pricing') ?? [])[$this->model] ?? self::DEFAULT_PRICING[$this->model] ?? []);
    }

    /**
     * The sensible default model per provider, used when a task omits 'model'.
     * Image generation runs on wholly different models than the chat tasks, so
     * the task decides which family the default comes from.
     */
    private static function defaultModel(?string $provider, string $task): ?string
    {
        if ($task === 'image') {
            return match ($provider) {
                'gemini' => 'gemini-2.5-flash-image',
                'openai' => 'gpt-image-1-mini',
                default => null,
            };
        }

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
     * Gemini image generation over generateContent. The aspect ratio is passed along
     * as a hint; the caller crops to the exact ratio anyway, so a model that ignores
     * imageConfig still produces a correctly framed image.
     *
     * @return array{mime: string, data: string, usage: array{input: int, output: int}|null}
     */
    private function imageGemini(string $prompt, string $aspect): array
    {
        $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey()])
            ->connectTimeout(10)->timeout((int) config('leap.ai.timeout', 60))
            ->post("https://generativelanguage.googleapis.com/v1beta/models/$this->model:generateContent", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE'],
                    'imageConfig' => ['aspectRatio' => $aspect],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini request failed: '.$response->status());
        }

        foreach ($response->json('candidates.0.content.parts') ?? [] as $part) {
            // Both spellings occur depending on the API version answering.
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (! empty($inline['data'])) {
                return [
                    'mime' => $inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png',
                    'data' => (string) base64_decode($inline['data'], true),
                    'usage' => $this->usage(
                        $response->json('usageMetadata.promptTokenCount'),
                        $response->json('usageMetadata.candidatesTokenCount'),
                    ),
                ];
            }
        }

        throw new RuntimeException('Gemini returned no image');
    }

    /**
     * OpenAI image generation. Only three canvas sizes exist, so the aspect ratio
     * picks the closest one and the caller crops it to the exact ratio.
     *
     * @return array{mime: string, data: string, usage: array{input: int, output: int}|null}
     */
    private function imageOpenai(string $prompt, string $aspect): array
    {
        $response = Http::withToken($this->apiKey())
            ->connectTimeout(10)->timeout((int) config('leap.ai.timeout', 60))
            ->post('https://api.openai.com/v1/images/generations', array_filter([
                'model' => $this->model,
                'prompt' => $prompt,
                'size' => self::openaiSize($aspect),
                'quality' => config('leap.ai.image.quality') ?: null,
            ]));

        if ($response->failed()) {
            throw new RuntimeException('OpenAI request failed: '.$response->status());
        }

        $data = $response->json('data.0.b64_json');
        if (! is_string($data) || $data === '') {
            throw new RuntimeException('OpenAI returned no image');
        }

        return [
            'mime' => 'image/'.($response->json('output_format') ?: 'png'),
            'data' => (string) base64_decode($data, true),
            'usage' => $this->usage($response->json('usage.input_tokens'), $response->json('usage.output_tokens')),
        ];
    }

    /**
     * The provider's token counts in one shape, or null when it reported none.
     *
     * @return array{input: int, output: int}|null
     */
    private function usage(mixed $input, mixed $output): ?array
    {
        if (! is_numeric($input) && ! is_numeric($output)) {
            return null;
        }

        return ['input' => (int) $input, 'output' => (int) $output];
    }

    /**
     * The OpenAI canvas closest to the requested aspect ratio: landscape, portrait
     * or square. Anything unparseable falls back to square.
     */
    private static function openaiSize(string $aspect): string
    {
        [$width, $height] = array_pad(array_map('floatval', explode(':', $aspect)), 2, 0);

        if ($width <= 0 || $height <= 0 || $width === $height) {
            return '1024x1024';
        }

        return $width > $height ? '1536x1024' : '1024x1536';
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
