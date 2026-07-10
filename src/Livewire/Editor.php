<?php

namespace NickDeKruijk\Leap\Livewire;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use NickDeKruijk\Leap\Classes\AiTask;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\Media;
use NickDeKruijk\Leap\Models\Mediable;
use NickDeKruijk\Leap\Traits\CanLog;

class Editor extends Component
{
    use CanLog;
    use WithRateLimiting;

    /**
     * Rate-limit an AI action (paid third-party call) per user; toast + abort when
     * exceeded. Returns false when the caller should stop.
     */
    private function aiRateLimit(): bool
    {
        try {
            $this->rateLimit((int) config('leap.ai.rate_limit', 30), method: 'ai');

            return true;
        } catch (TooManyRequestsException $e) {
            $this->dispatch('toast-error', __('leap::resource.ai_rate_limited', ['seconds' => $e->secondsUntilAvailable]))->to(Toasts::class);

            return false;
        }
    }

    const int CREATE_NEW = -1;

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
        $module = $this->parentModule();
        // Resource::$translatable is populated from the model in getModel(); ensure it's initialised
        $module->getModel();

        return $module->translatable ? (config('leap.locales') ?: []) : [];
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
                            $value = $sectionData[$title->name] ?? '';
                            // Translatable section fields are stored per locale; use the active one for the label
                            if (is_array($value)) {
                                $value = $value[$this->activeLocale] ?? (reset($value) ?: '');
                            }

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
                // Add the validation rule — per locale for translatable fields (default required, rest optional)
                if ($this->editorLocales() && $this->parentModule()->hasTranslation($attribute)) {
                    foreach (array_keys($this->editorLocales()) as $locale) {
                        $rules['data.'.$attribute->name.'.'.$locale] = $locale === $this->defaultLocale()
                            ? $attribute->validate
                            : array_map(fn ($rule) => $rule === 'required' ? 'nullable' : $rule, $attribute->validate);
                    }
                } else {
                    $rules['data.'.$attribute->name] = $attribute->validate;
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

    public function sectionAttribute(Attribute $sectionAttribute, string $name, int $index, $sectionName): Attribute
    {
        $newAttribute = clone $sectionAttribute;
        // Translatable section fields are edited per locale: data.{name}.{index}.{field}.{locale}
        $translatable = $sectionAttribute->translatable && $this->editorLocales();
        $locale = $this->activeLocale ?: $this->defaultLocale();
        $newAttribute->dataName = 'data.'.$name.'.'.$index.'.'.$sectionAttribute->name.($translatable ? '.'.$locale : '');
        $newAttribute->name = $name.'.'.$index.'.'.$sectionAttribute->name;
        $newAttribute->sectionName = $sectionName;
        $newAttribute->currentLocale = $translatable ? $locale : null;

        return $newAttribute;
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

        // Update slug placeholder if this field feeds a slug target (value is the
        // active-locale value for translatable fields)
        $slugMap = $this->slugMap();
        if ($attribute && isset($slugMap[$attribute->name])) {
            $this->placeholder[$slugMap[$attribute->name]] = Str::slug($value);
        }

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

        $validator = Validator::make(['data' => $data], $this->rules($id), [], $this->parentModule()->validationAttributes());
        if ($validator->fails()) {
            // Show validation errors as toasts
            foreach ($validator->messages()->keys() as $fieldKey) {
                $this->dispatch('toast-error', $validator->messages()->first($fieldKey), $fieldKey)->to(Toasts::class);
            }
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

            // Check if anything changed
            if ($model->isDirty() || $this->mediaUpdated || $this->pivotIsDirty()) {
                if ($this->editing == self::CREATE_NEW) {
                    $model->save();
                    $this->syncMedia($model);
                    $this->syncPivot($model);

                    $this->log('create', ['id' => $model->id]);
                    $this->dispatch('toast', $model[$this->parentModule()->indexAttributes()->first()->name].' ('.$model->id.') '.__('leap::resource.created'))->to(Toasts::class);
                    $this->dispatch('updateIndex', $model->id);
                    $this->editing = $model->id;
                } else {
                    if (count($model->getDirty()) + count($this->mediaUpdated) + count($this->pivotIsDirty()) > 3) {
                        $this->dispatch('toast', count($model->getDirty()) + count($this->mediaUpdated).' '.__('leap::resource.columns').' '.__('leap::resource.updated'))->to(Toasts::class);
                    } else {
                        foreach (array_merge($model->getDirty(), $this->mediaUpdated, $this->pivotIsDirty()) as $attribute => $value) {
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
