# AI features

Leap can call an AI provider to help fill content:

- **Alt texts** — generate a per-locale `alt` for an image in the file manager.
- **Translation** — translate editor content into the active locale, per field or all at once.
- **Image generation** — generate an image from a prompt, prefilled with the content of the
  section the field belongs to.

All three are **opt-in and disabled by default**, share one provider/credential configuration, and
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
    'image' => [
        'provider' => null, // 'gemini' | 'openai' (Claude and DeepL cannot generate images)
        'model' => null,    // null => gemini-2.5-flash-image / gpt-image-1-mini
        'folder' => '{module}', // where generated images are stored, see below
    ],
],
```

A task is **enabled** when its `provider` is set **and** that provider's `api_key` is non-empty.
Because the default model is keyed to the chosen provider, leaving `model` as `null` always
resolves to a working default — set a literal only to force a specific model.

**Limits.** `leap.ai.timeout` (default `60` seconds) bounds each provider request so a slow API
can't hang the admin, and `leap.ai.rate_limit` (default `30`) caps AI actions per user per minute
— note that image generation takes tens of seconds, so it raises PHP's own execution limit
(default 30 seconds for web requests) to `timeout` + 30; without that the PHP worker is killed
mid-request and the browser gets a bare 502 with nothing in the log. A web server usually gives up
before PHP does — nginx's `fastcgi_read_timeout` defaults to 60 seconds — so raising `timeout` far
above a minute means raising the proxy timeout as well
— every call is a paid request. For the chat providers you can raise a task's reply cap with
`leap.ai.<task>.max_tokens` (default `8192`) if a long page gets truncated.

### Providers

| Provider | Kind | Alt text (vision) | Translation | Images | Default model (chat / image) |
| --- | --- | --- | --- | --- | --- |
| `gemini` | Google Gemini (free tier available) | ✅ | ✅ | ✅ | `gemini-2.5-flash` / `gemini-2.5-flash-image` |
| `claude` | Anthropic Claude | ✅ | ✅ | — | `claude-haiku-4-5` |
| `openai` | OpenAI | ✅ | ✅ | ✅ | `gpt-4o-mini` / `gpt-image-1-mini` |
| `deepl` | DeepL | — (text only) | ✅ | — | — (DeepL has no model choice) |

Each task picks its own provider **and** model, so you can run cheap alt texts on one model and
better translation prose on another (e.g. `claude-sonnet-5`). Alt text requires a vision-capable
provider; DeepL is translation-only. Anthropic has no image-generation API, so `claude` cannot
back the `image` task.

Because the default model is keyed to the **task** as well as the provider, `image` never falls
back to a chat model: `gemini` resolves to `gemini-2.5-flash-image`, `openai` to `gpt-image-1-mini`.

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

## Image generation (editor and file manager)

When `image` is configured, a wand button (✨) appears next to a media field's browse button in the
editor, and in the file manager's header. It opens a dialog with a prompt, an aspect ratio and a
preview.

- **The prompt is prefilled from the section.** For a media field inside a section, the suggestion
  is built from the record's title and that section's own text, at the language tab you are on,
  with markup stripped — so the image is about the copy next to it. It is a starting point: edit it
  before generating. The file manager's button starts from an empty prompt.
- **Nothing is stored until you accept.** Generating produces a preview only; the bytes wait in the
  cache for 15 minutes. *Use image* stores the file and attaches it to the field — a result you
  reject leaves nothing behind. Saving the record is still the editor's own **Save**.
- **The result is always a JPEG at the ratio you picked.** Providers offer a handful of canvas
  sizes, so Leap crops and scales the result itself (`leap.ai.image.max_width`,
  `jpeg_quality`). The aspect ratios offered are `leap.ai.image.aspect_ratios`.
- **Alt text follows automatically** when `leap.ai.image.alt_text` is on and the `alt_text` task is
  configured — the new image is described in the same pass. A failing alt text never loses the
  image you just paid for.
- **Where it is stored:** `leap.ai.image.folder`, where `{module}` is the module's own folder name.
  A Page's images land in `pages/`, a News item's in `news/`, so generated art sorts itself the way
  the admin is organised. Set a literal (`'ai'`) to collect them in one folder, or combine them:
  `'ai/{module}'`. The name comes from the module class, not its translated title, so it does not
  move when the admin language changes. The file manager stores into the folder that is open.
- **Every generated image records what made it** in the media row's `meta['ai']`: model, prompt,
  cost and who generated it when.

Both providers additionally stamp provenance metadata on their output (SynthID for Gemini, C2PA for
OpenAI). Commercial use is allowed; the images stay identifiable as AI-generated.

### Costs

The dialog shows an estimate before generating and the actual amount after. **These are computed
from `leap.ai.pricing`, not reported by the provider** — neither API returns a price, only token
counts, which Leap multiplies by the rates in config. That means:

- the figures are ex VAT, in US dollars, and ignore any free tier;
- **they go stale.** The shipped rates carry the date they were checked; when a provider changes
  its prices the config is what has to be updated, not the code;
- a model with no entry in `leap.ai.pricing` simply shows no price, rather than a wrong `$0.00`;
- when a provider returns no usage at all, the estimate is shown instead.

```php
'pricing' => [
    'gemini-2.5-flash-image' => ['input' => 0.30, 'output' => 30.00, 'estimate' => 0.039],
    'gpt-image-1-mini' => ['input' => 2.00, 'output' => 8.00, 'estimate' => 0.011],
],
```

`input` and `output` are US dollars per million tokens and produce the amount shown afterwards;
`estimate` is the indicative price of a single image, shown up front.

### Other image providers

Gemini and OpenAI cover photographic content. `AiTask::image()` is one `match` arm per provider, so
adding another is a small change — the one worth knowing about is **Recraft**, which produces real
SVG/vector output and brand style presets, something neither shipped provider can do. `image()`
returns the bytes with their mime type for exactly that reason: vector output skips the JPEG
normalisation instead of being squashed into a bitmap.

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

// Image generation (gemini/openai): bytes, their mime type and the token usage.
$image = AiTask::for('image')->image('A red bicycle in the rain', '16:9');
$cost = AiTask::for('image')->cost($image['usage']);
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
[`tests/Feature/FileManagerAiAltTest.php`](../tests/Feature/FileManagerAiAltTest.php),
[`tests/Feature/EditorAiTranslateTest.php`](../tests/Feature/EditorAiTranslateTest.php),
[`tests/Feature/EditorAiImageTest.php`](../tests/Feature/EditorAiImageTest.php) and
[`tests/Feature/FileManagerAiImageTest.php`](../tests/Feature/FileManagerAiImageTest.php) — so the
prompt-building, JSON decoding (including code-fence-wrapped replies), DeepL request shape,
per-locale filling, the cropping to the requested aspect ratio and the cost calculation are all
exercised without spending tokens.
