<?php

namespace NickDeKruijk\Leap\Livewire;

use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use NickDeKruijk\Leap\Classes\AiTask;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\ImageGenerator;
use NickDeKruijk\Leap\Classes\Section;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Models\Mediable;
use NickDeKruijk\Leap\Traits\CanLog;
use NickDeKruijk\Leap\Traits\InteractsWithAiImages;
use NickDeKruijk\Leap\Traits\ToastsValidationErrors;

class Editor extends Component
{
    use CanLog;
    use InteractsWithAiImages;
    use ToastsValidationErrors;
    use WithRateLimiting;

    const int CREATE_NEW = -1;

    protected function aiImagePermission(): string
    {
        return 'update';
    }

    protected function aiImageFolder(): string
    {
        return ImageGenerator::folderFor(Leap::context()->module());
    }

    protected function aiLangFile(): string
    {
        return 'leap::resource';
    }

    /**
     * The id of the row currently being edited, also toggles editor
     */
    #[Locked]
    public ?int $editing;

    /**
     * The model data which can be updated by the editor
     */
    public array $data;

    /**
     * The attribute placeholders will be overruled by these and will be the default value when the input is empty
     */
    public array $placeholder = [];

    /**
     * The active locale for editing translatable fields (multilingual editor).
     * Empty unless config('leap.locales') is set and the module has translatable attributes.
     */
    public string $activeLocale = '';

    /**
     * Pending "update the slug to match the changed title?" suggestions, keyed by
     * [slug target attribute][locale] (locale '' for a monolingual editor). Set when a
     * title changes but the slug is not eligible to follow silently; the editor offers it
     * inline for the bewerker to accept or dismiss.
     *
     * @var array<string, array<string, string>>
     */
    public array $slugSuggestion = [];

    /**
     * Whether a slug has been deliberately set by hand (differs from its title's slug),
     * keyed by [slug target attribute][locale]. A customized slug never follows a title
     * change silently — it only ever gets a suggestion.
     *
     * @var array<string, array<string, bool>>
     */
    public array $slugCustomized = [];

    /**
     * Whether the record being edited is still within the silent-follow window
     * (config leap.slug_follow_minutes after creation). While fresh, an unedited slug
     * follows its title automatically; afterwards a change only offers a suggestion.
     */
    public bool $slugFresh = false;

    /**
     * The name of the parent Livewire component
     *
     * The editor uses this to determine the model and attributes. This will be encrypted to prevent leaking sensitive class name
     */
    #[Locked]
    public string $parentModuleEncrypted;

    /**
     * Keep track of updated media attributes
     */
    public array $mediaUpdated = [];

    /**
     * A random number to append to some input elements to keep them unique after each sorting action
     *
     * Mainly used as a workaround for tinymce editor issues after section sorting and deleting but causes flashing of the sections, looking for a more solid solution
     */
    public int $randomSortSeed;

    /**
     * Cached parent module instance to avoid repeated decryption and instantiation
     */
    private ?Component $parentModuleInstance = null;

    /**
     * Generate a new random sort seed number
     *
     * @return void
     */
    public function setRandomSortSeed()
    {
        $this->randomSortSeed = rand();
    }

    /**
     * Returns the parent Livewire component
     */
    private function parentModule(): Component
    {
        return $this->parentModuleInstance ??= new (Leap::context()->module());
    }

    /**
     * The locales to show as tabs in the editor, or empty when the editor is
     * monolingual (no leap.locales configured, or the module has no translatable attributes).
     *
     * @return array<string, string>
     */
    public function editorLocales(): array
    {
        return $this->parentModule()->translatable() ? (config('leap.locales') ?: []) : [];
    }

    /**
     * The default (first) locale, used as the base for validation and slug generation.
     */
    public function defaultLocale(): string
    {
        return array_key_first(config('leap.locales') ?? []) ?? app()->getLocale();
    }

    /**
     * Wrap a legacy monolingual value (null or a plain, non-JSON string left over
     * from before the field was translatable) into a per-locale array keyed by the
     * default locale, so it survives editing instead of being silently overwritten.
     *
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     */
    protected function normalizeTranslations(array $translations, mixed $raw): array
    {
        if (! empty($translations)) {
            return $translations;
        }
        if (is_string($raw) && trim($raw) !== '' && ! is_array(json_decode($raw, true))) {
            return [$this->defaultLocale() => $raw];
        }

        return $translations;
    }

    /**
     * Whether the AI translation feature is configured (provider + api key).
     */
    public function aiTranslateEnabled(): bool
    {
        return AiTask::for('translate')->enabled();
    }

    /**
     * Translate a single field into the active locale from $from and fill the
     * in-memory editor data for review (nothing is saved). $dataName is the
     * field's binding incl. the active locale, e.g. "data.title.nl" or
     * "data.blocks.2.heading.nl".
     */
    public function translateField(string $dataName, string $from): void
    {
        Leap::validatePermission('update');
        if (! $this->aiRateLimit()) {
            return;
        }

        $base = Str::beforeLast(Str::after($dataName, 'data.'), '.'.$this->activeLocale);
        $source = (string) data_get($this->data, "$base.$from");
        if (trim(strip_tags($source)) === '') {
            return;
        }

        try {
            $out = AiTask::for('translate')->translate([$base => $source], $this->activeLocale, $from);
            data_set($this->data, "$base.".$this->activeLocale, $this->slugifyValue($base, $out[$base] ?? $source));
            $this->dispatch('leap-fields-translated');
        } catch (\Throwable $e) {
            $this->dispatch('toast-error', __('leap::resource.translate_failed'))->to(Toasts::class);
        }
    }

    /**
     * Translate every translatable field (including section sub-fields) into the
     * active locale from $from, filling the in-memory editor data for review.
     * When $onlyEmpty, targets that already have content are left untouched.
     */
    public function translateAll(string $from, bool $onlyEmpty): void
    {
        Leap::validatePermission('update');
        if (! $this->aiRateLimit()) {
            return;
        }

        $values = [];
        foreach ($this->translatableDataPaths() as $base) {
            $target = (string) data_get($this->data, "$base.".$this->activeLocale);
            if ($onlyEmpty && trim(strip_tags($target)) !== '') {
                continue;
            }
            $source = (string) data_get($this->data, "$base.$from");
            if (trim(strip_tags($source)) !== '') {
                $values[$base] = $source;
            }
        }

        if ($values === []) {
            return;
        }

        try {
            foreach (AiTask::for('translate')->translate($values, $this->activeLocale, $from) as $base => $translated) {
                data_set($this->data, "$base.".$this->activeLocale, $this->slugifyValue($base, $translated));
            }

            $this->dispatch('leap-fields-translated');
        } catch (\Throwable $e) {
            $this->dispatch('toast-error', __('leap::resource.translate_failed'))->to(Toasts::class);
        }
    }

    /**
     * All translatable leaf paths relative to $this->data, without the locale
     * segment: top-level fields plus section/repeater sub-fields per index.
     *
     * @return list<string>
     */
    protected function translatableDataPaths(): array
    {
        $paths = [];
        foreach ($this->attributes() as $attribute) {
            if ($this->parentModule()->hasTranslation($attribute)) {
                $paths[] = $attribute->name;
            }

            if ($attribute->type === 'sections' && is_array($this->data[$attribute->name] ?? null)) {
                foreach ($this->data[$attribute->name] as $index => $section) {
                    if (! is_array($section) || empty($section['_name'])) {
                        continue;
                    }
                    $subs = collect($attribute->sections)->where('name', $section['_name'])->first()?->attributes;
                    foreach (collect($subs)->where('translatable', true) as $sub) {
                        $paths[] = "$attribute->name.$index.$sub->name";
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * Slugify a translated value when its field is a slug field, so a
     * translated slug stays URL-safe (e.g. "Über uns" → "uber-uns") instead of
     * being stored as prose. $base is a data path without the locale segment.
     */
    protected function slugifyValue(string $base, string $value): string
    {
        $field = Str::afterLast($base, '.');
        try {
            $isSlug = $this->attributes()->contains(fn ($attribute) => (
                // The slug field declares its source with slugFrom('title')…
                ($attribute->name === $field && $attribute->slugFrom)
                // …or another field declares slugify('slug') targeting this field.
                || $attribute->slugify === $field
            ));
        } catch (\Throwable $e) {
            return $value;
        }

        return $isSlug ? Str::slug($value) : $value;
    }

    /**
     * Return the model attributes to show in the editor
     */
    public function attributes(): Collection
    {
        // Get the attributes from the parent module
        $parentAttributes = $this->parentModule()->attributes();

        // Filter out the indexOnly attributes
        $attributes = collect($parentAttributes)->where('indexOnly', false);

        // Multilingual: bind translatable fields to the active locale (data.{name}.{locale})
        if ($this->editorLocales()) {
            foreach ($attributes as $attribute) {
                if ($this->parentModule()->hasTranslation($attribute)) {
                    $attribute->dataName = 'data.'.$attribute->name.'.'.($this->activeLocale ?: $this->defaultLocale());
                    $attribute->translatable = true;
                    $attribute->currentLocale = $this->activeLocale ?: $this->defaultLocale();
                }
            }
        }

        // slugFrom() lives on the slug field; make its source field push live so
        // the slug placeholder updates as you type (slugify() sets this itself).
        foreach ($attributes as $attribute) {
            if ($attribute->slugFrom && ($source = $attributes->firstWhere('name', $attribute->slugFrom))) {
                $source->wire = 'live';
            }
        }

        return $attributes;
    }

    /**
     * Return a model instance
     *
     * @param [type] $id
     */
    private function getModel($id = null): Model
    {
        // Get the model instance
        $model = $this->parentModule()->getModel();

        if ($id > 0) {
            // id is passed, return the model with data
            $model = $model->findOrFail($id);

            return $model;
        } else {
            // New model, set default attribute values if provided
            foreach ($this->attributes()->where('default') as $default) {
                $model->{$default->name} = $default->default;
            }

            return $model;
        }
    }

    /**
     * Return the relationship pivot data for the given attribute
     */
    public function pivotData(Attribute $attribute): array
    {
        return $this->getModel($this->editing)->{$attribute->name}->pluck('id')->toArray();
    }

    public function pivotIsDirty(?Attribute $attribute = null): array
    {
        $dirty = [];
        if ($attribute) {
            $attributes = [$attribute];
        } else {
            $attributes = $this->attributes()->where('type', 'pivot');
        }
        foreach ($attributes as $attribute) {
            if ($this->data[$attribute->name] != $this->pivotData($attribute)) {
                $dirty[$attribute->name] = $attribute->name;
            }
        }

        return $dirty;
    }

    /**
     * Show the editor for the given id
     *
     * @param  int  $id  the id of the Model to update
     * @return void
     */
    #[On('openEditor')]
    public function openEditor(int $id)
    {
        // Check if the user has read permission to this module
        Leap::validatePermission('read');

        $this->log('read', ['id' => $id]);

        // Set the editing id and open the editor
        $this->editing = $id;

        // We only want the attributes that are shown in the editor
        $attributes = $this->attributes()->pluck('name')->toArray();

        // Get the model data
        $model = $this->getModel($id);
        $this->data = $model->only($attributes);

        // Multilingual: initialise the active locale and load translatable fields as per-locale arrays
        if ($this->editorLocales()) {
            $this->activeLocale = $this->activeLocale ?: $this->defaultLocale();
            foreach ($this->attributes() as $attribute) {
                if ($this->parentModule()->hasTranslation($attribute)) {
                    $this->data[$attribute->name] = $this->normalizeTranslations(
                        $model->getTranslations($attribute->name),
                        $model->getRawOriginal($attribute->name),
                    );
                }
            }
        }

        // Get raw value from the model without any casting or mutators when editing
        foreach ($this->attributes()->where('raw') as $attribute) {
            $this->data[$attribute->name] = $model->getRawOriginal($attribute->name);
        }

        // Reformat date attributes
        foreach ($this->attributes()->where('type', 'date') as $attribute) {
            $this->data[$attribute->name] = $this->data[$attribute->name]?->isoFormat('YYYY-MM-DD');
        }
        // Reformat datetime attributes
        foreach ($this->attributes()->where('type', 'datetime-local') as $attribute) {
            $this->data[$attribute->name] = $this->data[$attribute->name]?->isoFormat('YYYY-MM-DD HH:mm:ss');
        }

        // Set the placeholders for slug attributes (use the active locale when translatable)
        $this->refreshSlugPlaceholders();

        // Initialise slug-follow state (which slugs are hand-edited, and whether the record
        // is fresh enough for a slug to follow its title silently)
        $this->initSlugState($model);

        // Obfuscate passwords
        foreach ($this->attributes()->where('type', 'password') as $attribute) {
            if ($this->data[$attribute->name]) {
                $this->data[$attribute->name] = null;
                if ($attribute->confirmed) {
                    $this->data[$attribute->confirmed] = null;
                }
            }
        }

        // Get the media attributes data
        $allMedia = $model
            ->morphToMany(Media::class, 'mediable', config('leap.table_prefix').'mediables')
            ->withPivot('sort', 'mediable_attribute')
            ->orderBy(config('leap.table_prefix').'mediables.sort');
        foreach ($allMedia->get() as $media) {
            $this->data[$media->pivot->mediable_attribute][] = $media->id;
        }

        // Get pivot data
        foreach ($this->attributes()->where('type', 'pivot') as $attribute) {
            $this->data[$attribute->name] = $model->{$attribute->name}->pluck('id')->toArray();
        }

        $this->checkSectionValues();

        // Clear existing validation errors
        $this->resetValidation();
    }

    /**
     * Make sure all section values exist to prevent livewire errors
     *
     * @return void
     */
    public function checkSectionValues()
    {
        foreach ($this->attributes()->where('type', 'sections') as $sectionAttribute) {
            // Initialize the :add key so wire:model.live resolves correctly on first interaction
            $this->data[$sectionAttribute->name.':add'] ??= null;

            if ($this->data[$sectionAttribute->name]) {
                foreach ($this->data[$sectionAttribute->name] as $index => $section) {
                    // Make sure _name is set
                    if (empty($section['_name'])) {
                        $section['_name'] = 'Invalid section';
                        $this->data[$sectionAttribute->name][$index]['_name'] = 'Invalid section';
                    }

                    // Get all section attributes
                    $sectionAttributes = collect($sectionAttribute->sections)
                        ->where('name', $section['_name'])
                        ->first()
                        ?->attributes;

                    // Make sure all section tinymce input values exist
                    $tinymceAttributes = collect($sectionAttributes)->where('input', 'tinymce');
                    foreach ($tinymceAttributes as $input) {
                        $this->data[$sectionAttribute->name][$index][$input->name] = $this->data[$sectionAttribute->name][$index][$input->name] ?? '';
                    }

                    // Wrap legacy monolingual values in translatable section sub-fields
                    // ([field] stored as a plain string before it became translatable)
                    // into a per-locale array, so they survive editing instead of being
                    // silently overwritten. Idempotent: already-wrapped arrays are skipped.
                    if ($this->editorLocales()) {
                        foreach (collect($sectionAttributes)->where('translatable', true) as $sub) {
                            $value = $this->data[$sectionAttribute->name][$index][$sub->name] ?? null;
                            if (is_string($value) && trim($value) !== '') {
                                $this->data[$sectionAttribute->name][$index][$sub->name] = [$this->defaultLocale() => $value];
                            }
                        }
                    }

                    // Set section titles
                    $sectionData = &$this->data[$sectionAttribute->name][$index];
                    $sectionData['_title'] = collect($sectionAttributes)
                        ->where('sectionTitle', true)
                        ->map(function ($title) use ($sectionData) {
                            // Translatable section fields are stored per locale; use the active one for the label
                            $value = Leap::localize($sectionData[$title->name] ?? '', $this->activeLocale) ?? '';

                            return strip_tags($value);
                        })
                        ->filter()
                        ->implode(' - ');
                }
            }
        }
    }

    /**
     * Hide the editor
     *
     * @return void
     */
    #[On('closeEditor')]
    #[On('openImport')]
    public function close()
    {
        $this->editing = null;
    }

    /**
     * Point a unique rule at the language it is validating.
     *
     * A translatable attribute is validated once per locale, but the rule names the
     * column plainly: "unique:pages,slug" asks the database where slug = 'over-ons',
     * and slug holds {"nl": "over-ons", "en": "about-us"}. A json object never equals
     * a string, so the rule matched nothing and every duplicate passed. Worse than a
     * missing check: HasSlug then quietly appended a -2 on save, so the editor was
     * neither warned nor given the slug they typed.
     *
     * Only the column is rewritten; the table, the ignored id and the id column all
     * keep their place.
     */
    private function localeUniqueRule(mixed $rule, string $locale): mixed
    {
        if (! is_string($rule) || ! str_starts_with($rule, 'unique:')) {
            return $rule;
        }

        $parts = explode(',', substr($rule, strlen('unique:')));

        // unique:<table>,<column>,... -- leave a rule that never named a column, and
        // one already addressing a json key, alone.
        if (($parts[1] ?? '') === '' || str_contains($parts[1], '->')) {
            return $rule;
        }

        $parts[1] .= '->'.$locale;

        return 'unique:'.implode(',', $parts);
    }

    /**
     * Custom validation messages. Used by both live validation (validateOnly) and the
     * save-time validator in isValid(), so they read the same. required_without_all backs
     * "a required translatable field must be filled in at least one locale" (see rules());
     * its default message lists the other locale fields, so name the field instead.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = ['required_without_all' => __('leap::resource.required_in_one_locale')];

        // rules() forbids the reserved "/" slug on a page that has a parent. The default not_in
        // message ("the selected value is invalid") explains nothing, so say what the rule means
        // — per slug field, and with a wildcard for the per-locale keys (data.slug.en).
        foreach (array_values($this->slugMap()) as $target) {
            $messages['data.'.$target.'.not_in'] = __('leap::resource.slug_root_only');
            $messages['data.'.$target.'.*.not_in'] = __('leap::resource.slug_root_only');
        }

        return $messages;
    }

    /**
     * Human labels for the validated fields, so a message names the field rather than its
     * dotted data path. Translatable fields validate per locale (data.{name}.{locale}), so
     * each locale gets its own entry, suffixed with the locale name.
     *
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        $locales = $this->editorLocales();
        $attributes = [];

        foreach ($this->attributes() as $attribute) {
            if ($locales && $this->parentModule()->hasTranslation($attribute)) {
                foreach ($locales as $locale => $name) {
                    $attributes['data.'.$attribute->name.'.'.$locale] = $attribute->label.' ('.$name.')';
                }
            } else {
                $attributes['data.'.$attribute->name] = $attribute->label;
            }
        }

        return $attributes;
    }

    /**
     * The column that scopes slug uniqueness to siblings, straight from the model (HasSlug
     * auto-detects a "parent" column and models may override it), or null when the model
     * does not use HasSlug — then slugs stay globally unique.
     */
    protected function slugSiblingColumn(): ?string
    {
        $model = $this->getModel();

        return method_exists($model, 'slugSiblingColumn') ? $model->slugSiblingColumn() : null;
    }

    /**
     * Scope a unique rule to the record's siblings by appending an extra where pair, the same
     * form ->unique(ignoreSoftDeletes: true) already uses for ",deleted_at,NULL". The value
     * comes from the data being edited, so changing the parent re-scopes the rule; a root
     * record scopes on the literal "NULL", which Laravel validates as whereNull.
     */
    protected function siblingScopedRule(mixed $rule, string $siblingColumn): mixed
    {
        if (! is_string($rule) || ! str_starts_with($rule, 'unique:')) {
            return $rule;
        }

        $value = $this->data[$siblingColumn] ?? null;

        return $rule.','.$siblingColumn.','.($value === null || $value === '' ? 'NULL' : $value);
    }

    /**
     * Return the validation rules from the attributes
     *
     * @param  int|null  $id  the id of the model to update or null if creating, usedto replace {id} in rules (usualy the unique rule)
     */
    public function rules(?int $id = null): array
    {
        // The Attribute class sets some placeholders in the validation rules that needs to be replaced with actual values, this array defines those replacements
        $replace = [
            '{id}' => $id,
            '{table}' => $this->getModel()->getTable(),
        ];

        $rules = [];

        // A slug is only unique among its siblings (see HasSlug), so its unique rule has to
        // be scoped the same way — otherwise validation rejects a slug the model would allow
        // under another parent.
        $siblingColumn = $this->slugSiblingColumn();
        $slugTargets = array_values($this->slugMap());

        foreach ($this->attributes() as $attribute) {
            // Walk thru the validation rules of each attribute
            if ($attribute->validate) {
                // Replace placeholders
                foreach ($replace as $old => $new) {
                    foreach ($attribute->validate as $key => $value) {
                        if (! is_object($value)) {
                            $attribute->validate[$key] = str_replace($old, $new ?: '', $value);
                        }
                    }
                }

                // Work on a copy from here: appending the sibling scope is not idempotent the
                // way the placeholder replacement above is, and $attribute->validate would
                // collect a second scope on the next rules() call.
                $validate = $attribute->validate;
                if ($siblingColumn && in_array($attribute->name, $slugTargets, true)) {
                    $validate = array_map(fn ($rule) => $this->siblingScopedRule($rule, $siblingColumn), $validate);

                    // "/" is the reserved homepage slug (see HasSlug) and only means anything at
                    // the root: deeper in the tree it collides with its parent's own URL and the
                    // page becomes unreachable, so refuse it there.
                    if (! empty($this->data[$siblingColumn])) {
                        $validate[] = 'not_in:/';
                    }
                }
                // Add the validation rule — per locale for translatable fields. A "required"
                // translatable field must be filled in at least one locale, not specifically
                // the default one: the default-locale rule becomes required_without_all across
                // the other locales, and the others stay optional. So a page written in only a
                // secondary language (e.g. Dutch on a site whose first locale is English) still
                // validates, with a single error when no locale is filled at all.
                if ($this->editorLocales() && $this->parentModule()->hasTranslation($attribute)) {
                    foreach (array_keys($this->editorLocales()) as $locale) {
                        $localeRules = array_map(fn ($rule) => $this->localeUniqueRule($rule, $locale), $validate);

                        if ($locale === $this->defaultLocale()) {
                            $others = array_values(array_map(
                                fn (string $other): string => 'data.'.$attribute->name.'.'.$other,
                                array_diff(array_keys($this->editorLocales()), [$locale]),
                            ));
                            $rules['data.'.$attribute->name.'.'.$locale] = array_map(
                                fn ($rule) => $rule === 'required' && $others
                                    ? 'required_without_all:'.implode(',', $others)
                                    : $rule,
                                $localeRules,
                            );
                        } else {
                            $rules['data.'.$attribute->name.'.'.$locale] = array_map(
                                fn ($rule) => $rule === 'required' ? 'nullable' : $rule,
                                $localeRules,
                            );
                        }
                    }
                } else {
                    $rules['data.'.$attribute->name] = $validate;
                }
            }
        }

        return $rules;
    }

    /**
     * Move a section to a different position in the array
     *
     * @return void
     */
    public function sortSection(string $attribute, ?int $index, int $position)
    {
        // Make sure index is not null to prevent error when json content doesn't have index keys
        $index ??= 0;

        // Sort all current data first
        $this->data[$attribute] = collect($this->data[$attribute])->sortBy('_sort')->toArray();

        // Pick the item to move and remove it from the array
        $itemToMove = [$index => $this->data[$attribute][$index]];
        unset($this->data[$attribute][$index]);

        // Add the item to the new position
        $this->data[$attribute] =
            array_slice($this->data[$attribute], 0, $position, true)
            + $itemToMove
            + array_slice($this->data[$attribute], $position, null, true);

        // Add _sort to all sections
        $sort = 0;
        foreach ($this->data[$attribute] as $index => $section) {
            $this->data[$attribute][$index]['_sort'] = $sort;
            $sort++;
        }

        $this->setRandomSortSeed();
    }

    /**
     * Open or close a section
     *
     * @return void
     */
    public function toggleSection(string $field, string $index)
    {
        $this->data[$field][$index]['_closed'] = ! ($this->data[$field][$index]['_closed'] ?? false);
    }

    /**
     * Open or close all sections
     *
     * @param  bool  $closed  true to close all sections, false to open all sections
     * @return void
     */
    public function toggleAllSections(string $field, bool $closed)
    {
        foreach ($this->data[$field] as $index => $section) {
            $this->data[$field][$index]['_closed'] = $closed;
        }
    }

    /**
     * Determine if a field has open sections
     */
    public function hasOpenSection(string $field): bool
    {
        foreach ($this->data[$field] ?? [] as $index => $section) {
            if (empty($section['_closed'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a field has closed sections
     */
    public function hasClosedSection(string $field): bool
    {
        foreach ($this->data[$field] ?? [] as $index => $section) {
            if (! empty($section['_closed'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove a section
     *
     * @return void
     */
    public function removeSection(string $field, string $index)
    {
        unset($this->data[$field][$index]);

        // Delete section media from data
        $prefix = $field.'.'.$index.'.';
        foreach ($this->data as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                unset($this->data[$key]);
                $this->mediaUpdated[$key] = $key;
            }
        }

        $this->setRandomSortSeed();
    }

    /**
     * Add a new section
     *
     * @return void
     */
    public function addSection(string $field, string $name)
    {
        $field = substr($field, 0, -4);
        $attribute = $this->attributes()->where('name', substr($field, 5))->first();

        // Determine the highest sort value currently in use
        $sort = 0;
        foreach ($this->data[$attribute->name] ?? [] as $section) {
            if (isset($section['_sort']) && $section['_sort'] > $sort) {
                $sort = $section['_sort'];
            }
        }

        // Initialize the new section data values with higher sort
        $data = [
            '_name' => $name,
            '_sort' => $sort + 1,
        ];

        // Add default values
        foreach (collect(collect($attribute->sections)->where('name', $name)->first()->attributes)->where('default') as $default) {
            $data[$default->name] = $default->default;
        }

        // Add the new section to the data
        $this->data[$attribute->name][] = $data;

        $this->checkSectionValues();

        // Reset the :add field
        $this->data[substr($field, 5).':add'] = null;
    }

    /**
     * @param  Section|null  $section  The section this field belongs to, needed to resolve
     *                                 a showIf() trigger to a sibling field
     */
    public function sectionAttribute(Attribute $sectionAttribute, string $name, int $index, $sectionName, ?Section $section = null): Attribute
    {
        $newAttribute = clone $sectionAttribute;

        // Carried on the clone so the label can hide itself. The wrapper this replaces
        // sat between the fieldset and its fields, which is where the form's own layout
        // expects them — a hidden field left a gap where its row used to be.
        if ($section && $sectionAttribute->showIf) {
            $newAttribute->showIfExpression = $this->showIf($section, $sectionAttribute, $name, $index);
        }

        // Translatable section fields are edited per locale: data.{name}.{index}.{field}.{locale}
        $translatable = $sectionAttribute->translatable && $this->editorLocales();
        $locale = $this->activeLocale ?: $this->defaultLocale();
        $newAttribute->dataName = 'data.'.$name.'.'.$index.'.'.$sectionAttribute->name.($translatable ? '.'.$locale : '');
        $newAttribute->name = $name.'.'.$index.'.'.$sectionAttribute->name;
        $newAttribute->sectionName = $sectionName;
        $newAttribute->currentLocale = $translatable ? $locale : null;

        return $newAttribute;
    }

    /**
     * The x-show expression for a section attribute that only appears while another field
     * of the same section is filled (showIf).
     *
     * The trigger has to be read at the locale the editor is showing when it happens to
     * be translatable. Such a field is stored per locale — {"nl": "", "en": ""} — and in
     * JavaScript an object is always truthy, so pointing at the field itself made the
     * dependent one appear the moment the trigger was touched in any language and never
     * go away again, not even after clearing it.
     *
     * @param  Section  $section  The section the trigger lives in, to find it by name
     */
    public function showIf(Section $section, Attribute $sectionAttribute, string $name, int $index): string
    {
        $path = "\$wire.data['{$name}'][{$index}]['{$sectionAttribute->showIf}']";

        $trigger = collect($section->attributes)->firstWhere('name', $sectionAttribute->showIf);

        if (! ($trigger?->translatable) || ! $this->editorLocales()) {
            return $path;
        }

        // Optional chaining: the key is absent until the field is first written to.
        return $path."?.['".($this->activeLocale ?: $this->defaultLocale())."']";
    }

    public function updated($field, $value)
    {
        // Check if :add field is used, if so add section
        if (str_ends_with($field, ':add')) {
            return $this->addSection($field, $value);
        }

        $name = substr($field, 5);
        $attribute = $this->attributes()->firstWhere('name', $name)
            // Translatable fields bind to data.{name}.{locale}; strip the locale to find the attribute
            ?? ($this->editorLocales() ? $this->attributes()->firstWhere('name', Str::beforeLast($name, '.')) : null);

        // Update slug placeholder if this field feeds a slug target. For a translatable
        // source Livewire may hand us the whole per-locale array rather than the active
        // locale's string, so narrow it down before slugifying (as refreshSlugPlaceholders
        // does) — otherwise Str::slug() throws "Array to string conversion".
        $slugMap = $this->slugMap();
        if ($attribute && isset($slugMap[$attribute->name])) {
            $slugValue = is_array($value) ? ($value[$this->activeLocale] ?? '') : $value;
            $this->placeholder[$slugMap[$attribute->name]] = Str::slug($slugValue);
        }

        // Let a slug follow its title (silently while fresh, or as an inline suggestion)
        // and track hand edits to the slug itself.
        $this->syncSlugOnUpdate($name);

        // Only validate if there are actual rules
        if ($this->rules()) {
            $this->validateOnly($field);
        }
    }

    /**
     * Map of slug relationships as [sourceAttribute => slugTargetAttribute].
     *
     * Gathered from both slugify() (declared on the source field, pointing to the
     * slug field) and slugFrom() (declared on the slug field, pointing back to the
     * source field), so both conventions drive the same placeholder logic.
     *
     * @return array<string, string>
     */
    protected function slugMap(): array
    {
        $map = [];
        foreach ($this->attributes() as $attribute) {
            if ($attribute->slugify) {
                $map[$attribute->name] = $attribute->slugify;
            }
            if ($attribute->slugFrom) {
                $map[$attribute->slugFrom] = $attribute->name;
            }
        }

        return $map;
    }

    /**
     * Recompute placeholders for all slug targets from their source values,
     * honouring the active locale for translatable source fields.
     */
    protected function refreshSlugPlaceholders(): void
    {
        foreach ($this->slugMap() as $source => $target) {
            $value = $this->data[$source] ?? '';
            if (is_array($value)) {
                $value = $value[$this->activeLocale] ?? '';
            }
            $this->placeholder[$target] = Str::slug($value);
        }
    }

    /**
     * The locales the editor edits, or [''] for a monolingual editor (a single, locale-less
     * slot). Lets the slug helpers treat both the same way.
     *
     * @return array<int, string>
     */
    protected function slugLocales(): array
    {
        return array_keys($this->editorLocales()) ?: [''];
    }

    /**
     * Read a (title or slug) field's value for a locale as a string. Locale '' is the
     * monolingual, non-nested slot.
     */
    protected function slugFieldValue(string $name, string $locale): string
    {
        $value = $locale === '' ? ($this->data[$name] ?? '') : ($this->data[$name][$locale] ?? '');

        return is_array($value) ? '' : (string) $value;
    }

    /**
     * Write a slug field's value for a locale, respecting the monolingual ('') slot.
     */
    protected function setSlugFieldValue(string $name, string $locale, string $value): void
    {
        if ($locale === '') {
            $this->data[$name] = $value;
        } else {
            $this->data[$name][$locale] = $value;
        }
    }

    /**
     * Initialise the slug-follow state for the record just loaded into the editor: whether
     * each slug is a deliberate hand edit (differs from its title's slug) and whether the
     * record is still within the silent-follow window (leap.slug_follow_minutes after
     * creation; a brand-new record with no created_at counts as fresh, 0 disables it).
     */
    protected function initSlugState(Model $model): void
    {
        $this->slugSuggestion = [];
        $this->slugCustomized = [];

        $minutes = (int) config('leap.slug_follow_minutes', 60);
        $this->slugFresh = $minutes > 0
            && (! $model->created_at || $model->created_at->gt(now()->subMinutes($minutes)));

        foreach ($this->slugMap() as $source => $target) {
            foreach ($this->slugLocales() as $locale) {
                $slug = $this->slugFieldValue($target, $locale);
                $title = $this->slugFieldValue($source, $locale);
                $this->slugCustomized[$target][$locale] = $slug !== '' && $slug !== Str::slug($title);
            }
        }
    }

    /**
     * React to a field change: when a title (slug source) changes, either let the slug follow
     * silently (unedited and still fresh) or offer an inline suggestion; when the slug itself
     * changes, record whether it is now a deliberate hand edit.
     *
     * @param  string  $name  the data key that changed, e.g. "title" or "title.nl"
     */
    protected function syncSlugOnUpdate(string $name): void
    {
        $map = $this->slugMap();
        if (! $map) {
            return;
        }

        // Split "title.nl" into base + locale (monolingual stays "title" with locale '').
        $locale = '';
        $base = $name;
        if ($this->editorLocales() && str_contains($name, '.')) {
            $locale = Str::afterLast($name, '.');
            $base = Str::beforeLast($name, '.');
        }

        // The slug itself changed: is it still just the title's slug, or a hand edit?
        if ($source = array_search($base, $map, true)) {
            $slug = $this->slugFieldValue($base, $locale);
            $title = $this->slugFieldValue($source, $locale);
            $this->slugCustomized[$base][$locale] = $slug !== '' && $slug !== Str::slug($title);
            unset($this->slugSuggestion[$base][$locale]);

            return;
        }

        // A title (slug source) changed: reconcile its slug target.
        if (isset($map[$base])) {
            $target = $map[$base];
            $new = Str::slug($this->slugFieldValue($base, $locale));
            $current = $this->slugFieldValue($target, $locale);

            // Empty slug: leave it (new record / deliberately blank; placeholder previews it,
            // HasSlug derives on save).
            if ($current === '') {
                return;
            }

            if (empty($this->slugCustomized[$target][$locale]) && $this->slugFresh) {
                // Unedited and still fresh: follow the title silently.
                $this->setSlugFieldValue($target, $locale, $new);
                unset($this->slugSuggestion[$target][$locale]);
            } elseif ($new !== '' && $new !== $current) {
                // Edited by hand, or past the window: offer it, never overwrite.
                $this->slugSuggestion[$target][$locale] = $new;
            } else {
                unset($this->slugSuggestion[$target][$locale]);
            }
        }
    }

    /**
     * The pending slug suggestion for a target/locale, or null. The label component calls this
     * (guarded by method_exists) to render the inline prompt only when there is one.
     */
    public function slugSuggestionFor(string $target, string $locale = ''): ?string
    {
        return $this->slugSuggestion[$target][$locale] ?? null;
    }

    /**
     * Accept a pending slug suggestion: fill the slug with the title's slug and let it follow
     * silently again (it now matches the title, so it is no longer a hand edit).
     */
    public function applySlugSuggestion(string $target, string $locale = ''): void
    {
        $suggestion = $this->slugSuggestion[$target][$locale] ?? null;
        if ($suggestion === null) {
            return;
        }

        $this->setSlugFieldValue($target, $locale, $suggestion);
        unset($this->slugSuggestion[$target][$locale]);
        $this->slugCustomized[$target][$locale] = false;
    }

    /**
     * Dismiss a pending slug suggestion, keeping the deliberate slug as it is.
     */
    public function dismissSlugSuggestion(string $target, string $locale = ''): void
    {
        unset($this->slugSuggestion[$target][$locale]);
    }

    /**
     * When the active locale tab changes, refresh slug placeholders to that locale.
     */
    public function updatedActiveLocale()
    {
        $this->refreshSlugPlaceholders();

        // Section titles (shown when a section is collapsed) are built from
        // sectionTitle fields, which follow the active locale too
        $this->checkSectionValues();
    }

    /**
     * Check if the data is valid, if not show validation error and toasts
     *
     * @param  int|null  $id  the id of the model to update or null if creating, passed to the rules method to replace {id} in rules (usualy the unique rule)
     */
    public function isValid(?int $id = null): bool
    {
        // Replace empty values with placeholders if present in temporary variable
        $data = $this->data;
        foreach ($this->placeholder as $name => $placeholder) {
            if (empty($data[$name])) {
                $data[$name] = $placeholder;
            }
        }

        $validator = Validator::make(['data' => $data], $this->rules($id), $this->messages(), $this->validationAttributes());
        if ($validator->fails()) {
            $this->toastValidationErrors($validator);
            // Show validation errors
            $validator->validate();

            return false;
        } else {
            // Validation passed so use data from temporary variable
            $this->data = $data;
            $this->resetValidation();

            return true;
        }
    }

    private function updateAttributes(Model &$model)
    {
        // Update each attribute
        foreach ($this->attributes() as $attribute) {
            if ($attribute->type == 'password' && ! $this->data[$attribute->name]) {
                // Ignore empty passwords
            } elseif ($attribute->type == 'password') {
                // The panel is where an administrator sets someone's password, so it
                // cannot depend on the application's model remembering to cast it.
                // A stock Laravel user model casts 'password' => 'hashed', and that
                // cast is idempotent; leave those to the model so nothing changes for
                // them, and hash here for a model that would otherwise have stored
                // the value as typed.
                $model->{$attribute->name} = ($model->getCasts()[$attribute->name] ?? null) === 'hashed'
                    ? $this->data[$attribute->name]
                    : Hash::make($this->data[$attribute->name]);
            } elseif ($attribute->type == 'media') {
                // Ignore media files
            } elseif ($attribute->type == 'pivot') {
                // Ignore pivot data
            } elseif ($attribute->type == 'sortable') {
                // Set sort value to highest current sort + 1
                if ($model->{$attribute->name} === null) {
                    $model->{$attribute->name} = $model::max($attribute->name) + 1;
                }
            } elseif ($attribute->isAccessor) {
                // Ignore accessors
            } elseif ($attribute->input == 'ace' && $attribute->options['mode'] == 'ace/mode/json') {
                $this->data[$attribute->name] = $this->data[$attribute->name] ? json_encode(json_decode($this->data[$attribute->name]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null;
                $model->{$attribute->name} = $this->data[$attribute->name];
            } else {
                if ($attribute->type == 'sections') {
                    // Extra treatment for each section
                    foreach ($this->data[$attribute->name] ?? [] as $key => $section) {
                        // Update section _view values
                        $view = collect($attribute->sections)->where('name', $section['_name'])->first()?->view;
                        if ($view) {
                            $this->data[$attribute->name][$key]['_view'] = $view;
                        }
                        // Set empty values to null (use strict check to preserve boolean false)
                        $this->data[$attribute->name][$key] = array_map(fn ($value) => $value === '' || $value === [] ? null : $value, $this->data[$attribute->name][$key]);
                    }
                }
                $model->{$attribute->name} = $this->data[$attribute->name] ?: ($attribute->type == 'checkbox' ? false : null);
            }
        }
    }

    public function syncMedia(Model $model)
    {
        foreach ($this->mediaUpdated as $attribute) {
            Mediable::where('mediable_type', $model::class)->where('mediable_id', $model->id)->where('mediable_attribute', $attribute)->delete();
            foreach ($this->data[$attribute] ?? [] as $sort => $media_id) {
                Mediable::create([
                    'media_id' => $media_id,
                    'mediable_type' => $model::class,
                    'mediable_id' => $model->id,
                    'mediable_attribute' => $attribute,
                    'sort' => $sort,
                ]);
            }
        }
        $this->mediaUpdated = [];
    }

    public function syncPivot(Model $model)
    {
        foreach ($this->attributes()->where('type', 'pivot') as $attribute) {
            $model->{$attribute->name}()->sync($this->data[$attribute->name]);
        }
    }

    /**
     * Save or create the edited model
     *
     * @return void
     */
    public function save()
    {
        Leap::validatePermission($this->editing == self::CREATE_NEW ? 'create' : 'update');

        if ($this->isValid($this->editing)) {
            // Get current model with data
            $model = $this->getModel($this->editing);

            $this->updateAttributes($model);

            // Collect the three dirty sets once: pivotIsDirty() queries the database,
            // so calling it per check would cost a round-trip each time.
            $dirty = $model->getDirty();
            $pivotDirty = $this->pivotIsDirty();

            // Check if anything changed
            if ($dirty || $this->mediaUpdated || $pivotDirty) {
                if ($this->editing == self::CREATE_NEW) {
                    $model->save();
                    $this->syncMedia($model);
                    $this->syncPivot($model);

                    $this->log('create', ['id' => $model->id]);
                    $this->dispatch('toast', $model[$this->parentModule()->indexAttributes()->first()->name].' ('.$model->id.') '.__('leap::resource.created'))->to(Toasts::class);
                    $this->dispatch('updateIndex', $model->id);
                    $this->editing = $model->id;
                } else {
                    $updated = count($dirty) + count($this->mediaUpdated) + count($pivotDirty);
                    if ($updated > 3) {
                        $this->dispatch('toast', $updated.' '.__('leap::resource.columns').' '.__('leap::resource.updated'))->to(Toasts::class);
                    } else {
                        foreach (array_merge($dirty, $this->mediaUpdated, $pivotDirty) as $attribute => $value) {
                            $this->dispatch('toast', ucfirst($this->parentModule()->validationAttributes()['data.'.explode('.', $attribute)[0]]).' '.__('leap::resource.updated'))->to(Toasts::class);
                        }
                    }
                    $model->save();
                    $this->syncMedia($model);
                    $this->syncPivot($model);

                    $this->log('update', ['id' => $this->editing]);
                    $this->dispatch('updateIndex', $model->id);
                }
                // Force reload of editor data
                $this->openEditor($model->id);
            } else {
                $this->dispatch('toast-alert', __('leap::resource.no_changes'))->to(Toasts::class);
            }

            // Let lazy rich-text fields drop back to their rendered-HTML preview,
            // whether or not there were changes to save
            $this->dispatch('leap-editor-saved');
        }
    }

    /**
     * Clone the edited model as a new model
     *
     * @return void
     */
    public function clone()
    {
        Leap::validatePermission('create');

        if ($this->isValid()) {
            // Create new model
            $model = $this->getModel();

            $this->updateAttributes($model);

            $model->save();

            // Set media updated to all possible media attributes to force syncing all media
            $this->mediaUpdated = $this->attributes()->where('type', 'media')->pluck('name')->toArray();
            // Do the same for all media attributes of sections
            foreach ($this->attributes()->where('type', 'sections') as $sectionAttribute) {
                if ($this->data[$sectionAttribute->name]) {
                    foreach ($this->data[$sectionAttribute->name] as $index => $section) {
                        if (isset($section['_name'])) {
                            // The code below needs some improvements for readability
                            foreach (collect(collect($sectionAttribute->sections)->where('name', $section['_name'])->first()?->attributes)->where('input', 'media') as $input) {
                                $this->mediaUpdated[] = $sectionAttribute->name.'.'.$index.'.'.$input->name;
                            }
                        }
                    }
                }
            }

            $this->syncMedia($model);
            $this->syncPivot($model);

            $this->log('create', ['clone' => $this->editing.' -> '.$model->id]);
            // Force reload of editor data
            $this->openEditor($model->id);
            $this->dispatch('toast', $model[$this->parentModule()->indexAttributes()->first()->name].' ('.$model->id.') '.__('leap::resource.created'))->to(Toasts::class);
            $this->dispatch('updateIndex', $model->id);
        }
    }

    /**
     * Delete the model being edited
     *
     * @return void
     */
    public function delete()
    {
        Leap::validatePermission('delete');
        $model = $this->getModel($this->editing);
        $this->dispatch('toast', $model[$this->parentModule()->indexAttributes()->first()->name].' ('.$model->id.') '.__('leap::resource.deleted'))->to(Toasts::class);
        $this->log('delete', ['id' => $this->editing]);
        $model->delete();
        $this->editing = null;
        $this->dispatch('updateIndex');
    }

    /**
     * Add the selected files from the file browser to the attribute value
     *
     * @param [type] $files
     * @return void
     */
    #[On('selectBrowsedFiles')]
    public function selectBrowsedFiles(string $attribute, array $files)
    {
        $this->data[$attribute] = trim($this->data[$attribute].PHP_EOL.implode(PHP_EOL, $files));
    }

    /**
     * A prompt suggestion for a media attribute, built from what the editor is
     * looking at: the record's title plus the text of the section the field belongs
     * to. Fills the generate dialog, where it can be edited before anything is sent.
     */
    public function imagePrompt(string $attribute): string
    {
        $content = array_filter([$this->recordTitle(), ...$this->sectionText($attribute)]);

        if ($content === []) {
            return '';
        }

        return __('leap::resource.image_prompt_prefix').' '.Str::limit(implode('. ', $content), 600);
    }

    /**
     * The record's own title, as the first index attribute holds it.
     */
    private function recordTitle(): string
    {
        $title = $this->parentModule()->indexAttributes()->first()?->name;

        return $title ? trim(strip_tags($this->localizedValue($this->data[$title] ?? null))) : '';
    }

    /**
     * The readable text of the section a section attribute belongs to. A section field
     * is named {field}.{index}.{name} and its data lives under the flat {field} key, so
     * the index points straight at the section's own values. Structural keys (_name,
     * _sort, …) and non-text values (media id lists, switches) drop out.
     *
     * @return list<string>
     */
    private function sectionText(string $attribute): array
    {
        $parts = explode('.', $attribute);

        if (count($parts) < 3) {
            return [];
        }

        $text = [];
        foreach ($this->data[$parts[0]][$parts[1]] ?? [] as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }
            if ($value = trim(strip_tags($this->localizedValue($value)))) {
                $text[] = $value;
            }
        }

        return $text;
    }

    /**
     * A field value as plain text, resolving a translatable field to the locale the
     * editor is showing. Anything that is not text (a media id list, a switch) yields
     * an empty string.
     */
    private function localizedValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[$this->activeLocale] ?? $value[$this->defaultLocale()] ?? null;
        }

        return is_string($value) ? $value : '';
    }

    /**
     * Accept a generated image: store it in the module's folder, describe it, and
     * attach it to the attribute the same way picking a file from the browser does.
     * Saving stays the editor's own Save button.
     */
    public function useGeneratedImage(string $attribute, string $token): void
    {
        if (! $media = $this->acceptGeneratedImage($token)) {
            return;
        }

        $this->mediaUpdated[$attribute] = $attribute;
        $this->data[$attribute] = [...(array) ($this->data[$attribute] ?? []), $media->id];
    }

    /**
     * Add the selected media files from the file browser to the mediable attribute
     *
     * @param [type] $files
     * @return void
     */
    #[On('selectMediaFiles')]
    public function selectMediaFiles(string $attribute, array $files)
    {
        $this->mediaUpdated[$attribute] = $attribute;
        foreach ($files as $file) {
            $media = Media::forFile($file);
            $this->data[$attribute][] = $media->id;
        }
    }

    public function unselectMedia($attribute, $id)
    {
        $this->mediaUpdated[$attribute] = $attribute;
        unset($this->data[$attribute][$id]);
        $this->data[$attribute] = array_values($this->data[$attribute]);
    }

    public function unselectFile($attribute, $id)
    {
        $data = explode(PHP_EOL, $this->data[$attribute]);
        unset($data[$id]);
        $this->data[$attribute] = implode(PHP_EOL, $data);
    }

    public function sortData($attribute, $old, $new)
    {
        if (is_array($this->data[$attribute])) {
            $out = array_splice($this->data[$attribute], $old, 1);
            array_splice($this->data[$attribute], $new, 0, $out);
            $this->mediaUpdated[$attribute] = $attribute;
        } else {
            $data = explode(PHP_EOL, $this->data[$attribute]);
            $out = array_splice($data, $old, 1);
            array_splice($data, $new, 0, $out);
            $this->data[$attribute] = implode(PHP_EOL, $data);
        }
    }

    public function media(string $attribute): array
    {
        $media = Media::find($this->data[$attribute])->keyBy('id');

        $return = [];
        foreach ($this->data[$attribute] as $id) {
            if ($media->has($id)) {
                $return[] = $media->get($id);
            }
        }

        return $return;
    }

    public function hydrate()
    {
        // Add the parentModule to the context so we can use it during each request
        Leap::context()->setModule(Crypt::decryptString($this->parentModuleEncrypted));
    }

    public function mount()
    {
        // Encrypt the parent module class name
        $this->parentModuleEncrypted = Crypt::encryptString(Leap::context()->module());

        // Default the active locale for the multilingual editor
        if ($this->editorLocales()) {
            $this->activeLocale = $this->activeLocale ?: $this->defaultLocale();
        }

        $this->setRandomSortSeed();
    }

    public function render()
    {
        return view('leap::livewire.editor');
    }
}
