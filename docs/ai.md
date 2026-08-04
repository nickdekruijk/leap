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

    // The chat tasks take { provider, model }. provider null = task disabled;
    // model null = the good default for the chosen provider.
    'alt_text' => [
        'provider' => null, // 'gemini' | 'claude' | 'openai' (vision required)
        'model' => null,    // null => gemini-2.5-flash / claude-haiku-4-5 / gpt-4o-mini
    ],
    'translate' => [
        'provider' => null, // 'gemini' | 'claude' | 'openai' | 'deepl'
        'model' => null,    // null => provider default; override e.g. 'claude-sonnet-5'
    ],
    // Image generation takes named presets instead: no presets = disabled.
    'image' => [
        'presets' => [
            // 'standard' => 'gpt-image-1-mini',
        ],
        'folder' => '{module}', // where generated images are stored, see below
    ],
],
```

A chat task is **enabled** when its `provider` is set **and** that provider's `api_key` is
non-empty. Because the default model is keyed to the chosen provider, leaving `model` as `null`
always resolves to a working default — set a literal only to force a specific model. Image
generation names its models in [presets](#image-presets) instead, and is enabled when at least one
of them has an api key.

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

Each chat task picks its own provider **and** model, so you can run cheap alt texts on one model
and better translation prose on another (e.g. `claude-sonnet-5`). Alt text requires a
vision-capable provider; DeepL is translation-only. Anthropic has no image-generation API, so
`claude` cannot back the `image` task. Image generation has no `provider` of its own: each preset
names a model, and the model name says which provider runs it.

Because the default model is keyed to the **task** as well as the provider, a chat task never
resolves to an image model or the other way round.

### Models

The model name is passed to the provider untouched, so **any model id the provider accepts works** —
the lists below are the ones Leap knows a price for, not a whitelist.

**Image generation** (`leap.ai.image.presets`). Each of these ships with a rate and a per-image
estimate, so the generate dialog can quote a cost before you commit to it:

| Provider | Models (cheapest first) |
| --- | --- |
| `gemini` | `gemini-2.5-flash-image`, `gemini-3.1-flash-lite-image`, `gemini-3.1-flash-image`, `gemini-3-pro-image` |
| `openai` | `gpt-image-1-mini`, `gpt-image-1.5`, `gpt-image-2`, `gpt-image-1` (superseded) |

**Chat** (`leap.ai.alt_text.model`, `leap.ai.translate.model`) — alt text needs a vision-capable
model, translation does not:

| Provider | Models (default first) |
| --- | --- |
| `gemini` | `gemini-2.5-flash`, `gemini-2.5-pro` |
| `claude` | `claude-haiku-4-5`, `claude-sonnet-5`, `claude-opus-4-8` |
| `openai` | `gpt-4o-mini`, `gpt-4o` |
| `deepl` | — (no model choice) |

No rates ship for chat models, so those calls show no price unless you list one under
`leap.ai.pricing` — see [Costs](#costs) for why the rates live in the package rather than in the
published config.

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
editor, and in the file manager next to *New folder* and *Upload* — the third way to get a file
into the folder you have open. It opens a dialog with a prompt, a shape and a preview.

### Image presets

`leap.ai.image.presets` is the list of model + quality combinations the dialog offers. The key is
the label, the value a model id with an optional `:quality` suffix:

```php
'image' => [
    'presets' => [
        'low' => 'gemini-2.5-flash-image',
        'medium' => 'gpt-image-1-mini:medium',
        'high' => 'gemini-3-pro-image',
    ],
],
```

- **The provider comes from the model name**, not from a setting: `gemini*` runs on Gemini,
  `gpt-*` on OpenAI. So one preset can be a cheap Gemini image and the next an expensive OpenAI
  one, and a preset whose provider has no api key is simply not offered.
- **`:quality` is OpenAI only** (`low`, `medium`, `high`); Gemini has no quality setting and
  ignores it. It changes the price per image by up to 35x, so naming it also makes the estimate
  exact — see [Costs](#costs).
- **No presets means no image generation.** One preset means there is nothing to pick, so the
  dialog leaves the picker out and looks exactly as it did before presets existed. Two or more add
  a **Quality** select above the shape, each option showing its own estimate.
- **The keys are yours.** `low`, `medium`, `high` and `maximum` ship translated; any other key is
  shown as written (`'print ready'` → "Print Ready"), so you can name presets after what they are
  for. To translate one of your own, publish the panel translations and add
  `image_quality_<key>` to `resource.php`.
- **A preset only says what to order.** How the answer is stored
  (`leap.ai.image.max_width`, `jpeg_quality`) is post-processing the provider never sees, so it
  stays one setting for every preset.
- **The picked preset is a key, never a model.** The browser sends the key; an unknown one falls
  back to the first preset, so a request cannot name a model of its own.

> **Deprecated:** `leap.ai.image.provider`, `model` and `quality` still work when a config
> published before presets sets them (they behave as a single unnamed preset), but they are
> scheduled for removal in 2.0 — move them into `presets` when convenient.

- **The prompt is prefilled from the section.** For a media field inside a section, the suggestion
  is built from the record's title and that section's own text, at the language tab you are on,
  with markup stripped — so the image is about the copy next to it. It is a starting point: edit it
  before generating. The file manager's button starts from an empty prompt.
- **Nothing is stored until you accept.** Generating produces a preview only; the bytes wait in the
  cache for 15 minutes. *Use image* stores the file and attaches it to the field — a result you
  reject leaves nothing behind. Saving the record is still the editor's own **Save**.
- **You pick a shape, not an exact ratio.** Landscape, portrait or square — the three canvases the
  providers actually offer (OpenAI 1536×1024, 1024×1536, 1024×1024; Gemini gets 4:3, 3:4 or 1:1 as
  its ratio hint). **What the model produced is what gets stored:** its proportions *and* its
  resolution, so the framing you approved in the preview is the file you get and a high-resolution
  model stays high-resolution. The only change is the encoding — providers answer in PNG, and the
  same picture as JPEG at `leap.ai.image.jpeg_quality` is a fraction of the bytes. Set
  `leap.ai.image.max_width` to a number to cap the width anyway; that is worth doing on a site that
  serves the stored file straight into a page and uses a model that answers at 2K or 4K.
- **Alt text follows automatically** when `leap.ai.image.alt_text` is on and the `alt_text` task is
  configured — the new image is described in the same pass. A failing alt text never loses the
  image you just paid for.
- **Where it is stored:** `leap.ai.image.folder`, where `{module}` is the module's own folder name.
  A Page's images land in `pages/`, a News item's in `news/`, so generated art sorts itself the way
  the admin is organised. Set a literal (`'ai'`) to collect them in one folder, or combine them:
  `'ai/{module}'`. The name comes from the module class, not its translated title, so it does not
  move when the admin language changes. The file manager stores into the folder that is open.
- **Every generated image records what made it** in the media row's `meta['ai']`: model, quality,
  prompt, cost and who generated it when. Selecting it in the file manager marks it with an
  **A.I.** badge next to the filename, and clicking that unfolds the prompt, model and cost — so
  months later it is still clear which images were photographed and which were prompted.

Both providers additionally stamp provenance metadata on their output (SynthID for Gemini, C2PA for
OpenAI). Commercial use is allowed; the images stay identifiable as AI-generated.

### Costs

The dialog shows an estimate before generating and the actual amount after. **These are computed,
not reported by the provider** — neither API returns a price, only token counts, which Leap
multiplies by a rate per model. That means:

- the figures are ex VAT, in US dollars, and ignore any free tier;
- **they go stale** when a provider changes its prices;
- a model with no known rate simply shows no price, rather than a wrong `$0.00`;
- when a provider returns no usage at all, the estimate is shown instead.

The rates ship with the package
([`AiTask::DEFAULT_PRICING`](../src/Classes/AiTask.php), carrying the date they were checked), so
they can be refreshed with an update. They are deliberately **not** in the published config: a copy
there would freeze the prices of the day it was published and quietly outlive them. Override a
model by naming it under `leap.ai.pricing`:

```php
'pricing' => [
    'gemini-2.5-flash-image' => ['input' => 0.30, 'output' => 30.00, 'estimate' => 0.039],
    'gpt-image-1-mini' => ['input' => 2.00, 'output' => 8.00, 'estimate' => ['low' => 0.006, 'medium' => 0.015, 'high' => 0.052]],
],
```

`input` and `output` are US dollars per million tokens and produce the amount shown afterwards;
`estimate` is the indicative price of a single image, shown up front.

**Quality changes the price.** OpenAI charges per image by quality, and the spread is large — a
`gpt-image-2` at `high` costs about 35 times one at `low`. `estimate` is therefore a figure per
quality for those models, picked with the preset's `:quality` suffix. A preset without one leaves
the provider its own `auto`, which is free to choose the dearest, so the estimate quotes the
ceiling: an estimate that can be exceeded is worse than a generous one. Naming the quality makes
the estimate exact. Gemini has no quality setting, so one figure covers it.

**Two switches, not one.** `leap.ai.show_costs` (default `true`) decides whether the panel shows
any of this — with it off there is no estimate on the button, none next to the preset options and
no amount after generating. `leap.ai.record_costs` (default `true`) decides whether the amount is
kept in the media row's `meta['ai']['cost']`. They are separate on purpose: a computed figure you
would rather not put in front of an editor is still worth having when you want to know what a
month of generating cost.

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
per-locale filling, the shape asked of each provider and the cost calculation are all
exercised without spending tokens.
