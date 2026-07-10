# AI features

Leap can call an AI provider to help fill content:

- **Alt texts** — generate a per-locale `alt` for an image in the file manager.
- **Translation** — translate editor content into the active locale, per field or all at once.

Both are **opt-in and disabled by default**, share one provider/credential configuration, and
never write to the database on their own — they fill the form for review and you save as usual.

Everything is driven by the reusable [`AiTask`](../src/Classes/AiTask.php) class, so adding a new
AI-assisted action is a matter of configuring a task and calling it.

## Configuration

All AI settings live under `leap.ai` in `config/leap.php`. **Only the API keys are environment
variables** (secrets); the per-task provider and model are structural project choices set as
literals.

```php
'ai' => [
    // Shared provider credentials — the only env vars this feature needs.
    'providers' => [
        'gemini' => ['api_key' => env('GEMINI_API_KEY')],
        'claude' => ['api_key' => env('ANTHROPIC_API_KEY')],
        'openai' => ['api_key' => env('OPENAI_API_KEY')],
        'deepl'  => ['api_key' => env('DEEPL_API_KEY')], // translation only; no vision
    ],

    // Per-task { provider, model }. provider null = task disabled;
    // model null = the good default for the chosen provider.
    'alt_text' => [
        'provider' => null, // 'gemini' | 'claude' | 'openai' (vision required)
        'model' => null,    // null => gemini-2.5-flash / claude-haiku-4-5 / gpt-4o-mini
    ],
    'translate' => [
        'provider' => null, // 'gemini' | 'claude' | 'openai' | 'deepl'
        'model' => null,    // null => provider default; override e.g. 'claude-sonnet-5'
    ],
],
```

A task is **enabled** when its `provider` is set **and** that provider's `api_key` is non-empty.
Because the default model is keyed to the chosen provider, leaving `model` as `null` always
resolves to a working default — set a literal only to force a specific model.

**Limits.** `leap.ai.timeout` (default `60` seconds) bounds each provider request so a slow API
can't hang the admin, and `leap.ai.rate_limit` (default `30`) caps AI actions per user per minute
— every call is a paid request. For the chat providers you can raise a task's reply cap with
`leap.ai.<task>.max_tokens` (default `8192`) if a long page gets truncated.

### Providers

| Provider | Kind | Alt text (vision) | Translation | Default model |
| --- | --- | --- | --- | --- |
| `gemini` | Google Gemini (free tier available) | ✅ | ✅ | `gemini-2.5-flash` |
| `claude` | Anthropic Claude | ✅ | ✅ | `claude-haiku-4-5` |
| `openai` | OpenAI | ✅ | ✅ | `gpt-4o-mini` |
| `deepl` | DeepL | — (text only) | ✅ | — (DeepL has no model choice) |

Each task picks its own provider **and** model, so you can run cheap alt texts on one model and
better translation prose on another (e.g. `claude-sonnet-5`). Alt text requires a vision-capable
provider; DeepL is translation-only.

> AI calls hit a paid third-party API (Gemini has a free tier; DeepL has a free key). Image and
> text content is sent to the configured provider — review the provider's terms before enabling.

## Alt texts (file manager)

When `alt_text` is configured, selecting a **raster** image in the file manager shows an AI button
(✨) in the alt-text popover. It generates a concise, accessibility-oriented alt text for **every
locale** in `leap.locales` in one call, fills the inputs for review, and leaves saving to you.
Nothing is written until you press the save (✓) button. SVGs and non-image files never show the
button (no vision).

See also: alt texts are stored per locale in the media `meta['alt']` column.

## Translation (editor)

When `translate` is configured and a resource has translatable fields (see
[multilingual.md](multilingual.md)), the editor gains two AI actions. **Both translate *into* the
active locale from a chosen source locale** — to fill another language, switch the language tab and
run it again. Results fill the editor fields for review; nothing is saved until you press the
editor's **Save**.

- **Per field** — click a field's locale badge → a small dropdown lists the other locales; pick one
  to translate that field from it into the active locale.
- **All fields** — the **Translate** button in the button bar opens a modal: choose the source
  locale and whether to translate **only empty fields** or **all fields (overwrite)**. This covers
  every translatable field, **including section/repeater sub-fields**.

Details:

- **HTML is preserved.** Rich-text markup — bold, italic, links (the URL is kept), lists, tables,
  images — stays intact; only the visible text is translated.
- **Slugs stay slugs.** A translated slug field is run through `Str::slug()` (e.g. German
  "over-ons" → "uber-uns") instead of being stored as prose. Slug fields are detected via
  `slugFrom()` or a `slugify()` target.
- **Rich-text updates live.** TinyMCE fields reflect the filled value immediately.

## Extending — the `AiTask` class

[`AiTask`](../src/Classes/AiTask.php) is a small, provider-agnostic value object. Build one for a
configured task and call it:

```php
use NickDeKruijk\Leap\Classes\AiTask;

$task = AiTask::for('translate');      // reads config('leap.ai.translate')

if ($task->enabled()) {
    // Vision + text prompt (chat providers), optional images, optional JSON reply:
    $text = $task->prompt('Describe this image', [['mime' => 'image/png', 'data' => $base64]], json: true);

    // Translation (all providers incl. DeepL), keys preserved, HTML kept:
    $map = $task->translate(['title' => 'Hallo', 'body' => '<p>…</p>'], to: 'en', from: 'nl');
}
```

To add a new AI-assisted action, add a task key under `leap.ai` (`{provider, model}`), then call
`AiTask::for('<your_task>')`. `prompt()` covers Gemini/Claude/OpenAI (DeepL is translation-only via
`translate()`).

### DeepL specifics

- **Minimum API-key scope:** `translate:text` (only `POST /v2/translate` is used).
- Free keys (suffixed `:fx`) automatically use the `api-free.deepl.com` host; others use
  `api.deepl.com`.
- `target_lang` uses regional variants where DeepL requires them (`en` → `EN-GB`, `pt` → `PT-PT`);
  `source_lang` is always the plain language (DeepL rejects a regional source).
- HTML and plain-text fields are sent in separate requests so `tag_handling=html` (which HTML-encodes
  entities) never corrupts plain text like `A & B`.

## Verification

Provider calls are covered by tests using `Http::fake()` — see
[`tests/Feature/FileManagerAiAltTest.php`](../tests/Feature/FileManagerAiAltTest.php) and
[`tests/Feature/EditorAiTranslateTest.php`](../tests/Feature/EditorAiTranslateTest.php) — so the
prompt-building, JSON decoding (including code-fence-wrapped replies), DeepL request shape, and
per-locale filling are exercised without spending tokens.
