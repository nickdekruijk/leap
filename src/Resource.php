<?php

namespace NickDeKruijk\Leap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Livewire\Toasts;
use Spatie\Translatable\HasTranslations;

class Resource extends Module
{
    use WithFileUploads;

    /**
     * The model the module corresponds to.
     *
     * @var string
     */
    #[Locked]
    public $model;

    /**
     * Sort the index by this attribute
     *
     * @var string|null
     */
    #[Locked]
    public $orderBy;

    #[Locked]
    public $orderByDefault;

    /**
     * Enable descending index order
     *
     * @var bool
     */
    #[Locked]
    public $orderDesc = false;

    #[Locked]
    public $orderDescDefault;

    /**
     * The attribute that defines if a row is active or not
     *
     * When the attribute returns false the row will be shown as inactive (strikethrough) in the index.
     *
     * @var string|null
     */
    #[Locked]
    public $active = null;

    /**
     * The currently selected index row
     */
    #[Url(as: 'id', history: false)]
    public ?int $selectedRow = null;

    /**
     * When set shows the file browser
     *
     * @var array
     */
    #[Locked]
    public array|false $browse = false;

    #[Locked]
    public array|false $translatable = false;

    /**
     * Eager load model with these relationships
     *
     * @var array|string|null
     */
    #[Locked]
    public $with = null;

    /**
     * Eager load model with counts for these relationships
     *
     * @var array|string|null
     */
    #[Locked]
    public $withCount = null;

    /**
     * Active filters
     */
    #[Locked]
    public array $filters = [];

    /**
     * Return all unique values for the attribute
     *
     * A foreign or pivot filter is keyed by the id of the related record, not by the
     * text the index renders for it: a row with two pivot values renders as one joined
     * string ("Update, Announcement"), which as a filter option can never be matched by
     * a single value. Only the ids actually in use are offered, so no option can return
     * an empty index.
     */
    #[Computed()]
    public function filterData(Attribute $attribute): array
    {
        if ($attribute->type == 'checkbox') {
            return [
                0 => __('No'),
                1 => __('Yes'),
            ];
        }

        if (in_array($attribute->type, ['foreign', 'pivot'])) {
            $used = $attribute->type == 'pivot' ? $this->pivotFilterIds($attribute) : $this->foreignFilterIds($attribute);

            return array_filter($attribute->getValues(), fn ($id) => in_array($id, $used), ARRAY_FILTER_USE_KEY);
        }

        return $this->rows(index: true, filtered: false)->pluck($attribute->name, $attribute->name)->unique()->toArray();
    }

    /**
     * Return the ids of the related records that are attached to at least one row
     *
     * The pivot table of a MorphToMany is shared with the other models using it, so
     * without the morph constraint the filter would offer values from other resources.
     * Whether the row itself is active or in the current treeview branch is not taken
     * into account, one query for the whole column is worth that.
     *
     * @return array<int, mixed>
     */
    private function pivotFilterIds(Attribute $attribute): array
    {
        $model = $this->getModel();
        $relation = $model->{$attribute->name}();

        $query = DB::table($relation->getTable())->distinct();

        if ($relation instanceof MorphToMany) {
            $query->where($relation->getMorphType(), $model->getMorphClass());
        }

        return $query->pluck($relation->getRelatedPivotKeyName())->all();
    }

    /**
     * Return the foreign key values that are in use by at least one row
     *
     * @return array<int, mixed>
     */
    private function foreignFilterIds(Attribute $attribute): array
    {
        return $this->getModel()->distinct()->whereNotNull($attribute->name)->pluck($attribute->name)->all();
    }

    /**
     * If enabled shows an extra row with a index letter based on the current index ordering, not all attribute types support it
     *
     * @var bool
     */
    #[Locked]
    public $showIndexGroups = true;

    /**
     * Memoised indexGroupable() answer, keyed by the column ordered on. Protected, so
     * Livewire neither persists nor restores it: the ordering can change between
     * requests, and the answer with it.
     *
     * @var array<string, bool>
     */
    protected array $indexGroupableCache = [];

    /**
     * Whether the current ordering groups into letters at all.
     *
     * A group header is the first character of the ordered value, which only says
     * something when that value is text: a date puts every row of this century under
     * "2", an id and a counter group by their leading digit, and a select shows a
     * label while grouping by the key behind it.
     */
    public function indexGroupable(): bool
    {
        if (! $this->orderBy || ! $this->model) {
            return false;
        }

        return $this->indexGroupableCache[$this->orderBy] ??= $this->columnGroupable($this->orderBy);
    }

    /**
     * The three things that have to agree before a column groups: what the attribute
     * says it is, what it shows, and what the model holds in it.
     */
    protected function columnGroupable(string $column): bool
    {
        $attribute = $this->getAttribute($column);

        if (! $attribute || ! in_array($attribute->type, ['text', 'email'], true)) {
            return false;
        }

        // The index renders a select's label ($attribute->values[...]), not the value
        // it would group by, so the headers would spell out the keys behind it.
        if (in_array($attribute->input, ['select', 'radio'], true)) {
            return false;
        }

        // 'text' is also the default of an attribute that never said what it holds --
        // an id, a foreign key, a counter -- so the model's casts have the last word.
        // getCasts() always carries the primary key, so an id is caught for being an
        // int rather than for being called "id".
        $cast = (new $this->model)->getCasts()[$column] ?? null;

        // A translatable column is json and casts to none of these or to array; the
        // json-shaped types that are not text (sections, meta) already left above.
        return $cast === null || in_array($cast, ['string', 'array', 'json', 'object', 'collection'], true);
    }

    /**
     * Return the first letter of a value to use as the index group
     *
     * @param  Attribute|null  $attribute  Unused; the ordered column is read from $this. Kept for backwards compatibility.
     */
    public function indexGroupChar(Model $row, ?Attribute $attribute = null): string
    {
        $orderByValue = $row[$this->orderBy];
        $value = $this->hasTranslation($this->getAttribute($this->orderBy)) ? ($orderByValue[app()->getLocale()] ?? (is_array($orderByValue) ? reset($orderByValue) : $orderByValue)) : ($orderByValue ?? '');
        $char = ucfirst(mb_substr($value, 0, 1));
        $char = iconv('UTF-8', 'ASCII//TRANSLIT', $char);

        return $char;
    }

    /**
     * The livewire editor component to use
     *
     * @var string
     */
    #[Locked]
    public $editor = 'leap.editor';

    /**
     * Close the file browser
     *
     * @return void
     */
    #[On('selectBrowsedFiles')]
    #[On('selectMediaFiles')]
    #[On('tinymceBrowser')]
    public function closeBrowser()
    {
        $this->browse = false;
        $this->dispatch('recalculate-columns');
    }

    /**
     * Open or close the file browser for the file(s) or media attribute
     *
     * @param [type] $attribute
     * @return void
     */
    public function fileBrowser($attribute = null, $files = null, $sectionName = '')
    {
        if ($sectionName) {
            $attributeParts = explode('.', $attribute);

            $sections = collect($this->getAttribute($attributeParts[0])->sections);
            $section = $sections->where('name', $sectionName)->first();
            $sectionAttributes = collect($section->attributes);
            $sectionAttribute = $sectionAttributes->where('name', $attributeParts[2])->first();

            $this->browse = $attribute && ! $files ? array_merge(['attribute' => $attribute], $sectionAttribute->options) : false;
        } else {
            $this->browse = $attribute && ! $files ? array_merge(['attribute' => $attribute], $this->getAttribute($attribute)->options) : false;
        }

        $this->dispatch('recalculate-columns');
    }

    /**
     * Open the browser as tinymc file picker
     *
     * @return void
     */
    public function tinymceBrowser()
    {
        $this->browse = [
            'attribute' => '_tinymce',
            'media' => false,
            'multiple' => false,
        ];
    }

    public function hasTranslation(Attribute $attribute): bool
    {
        if ($this->translatable) {
            return in_array($attribute->name, $this->translatable);
        }

        return false;
    }

    private Attribute|false|null $sortableCache = null;

    private Attribute|false|null $treeviewCache = null;

    public function sortable(): Attribute|false
    {
        return $this->sortableCache ??= ($this->allAttributes()->where('type', 'sortable')->first() ?: false);
    }

    public function treeview(): Attribute|false
    {
        return $this->treeviewCache ??= ($this->allAttributes()->where('type', 'tree')->first() ?: false);
    }

    /**
     * Return the labels for each attribute for nice validation messages
     */
    public function validationAttributes(): array
    {
        $attributes = [];
        foreach ($this->attributes() as $attribute) {
            $attributes['data.'.$attribute->name] = $attribute->label;
        }

        return $attributes;
    }

    /**
     * When set to true shows the import view
     */
    #[Locked]
    public bool $importing = false;

    /**
     * CSV file to use for importing new data
     */
    public ?TemporaryUploadedFile $importCSV = null;

    /**
     * Data extracted from the CSV file for importing
     */
    public array $importData = [];

    public array $importRows = [];

    public array $importColumns = [];

    public array $importColumnOptions = [];

    public array $importErrors = [];

    public int $importColumnCount = 0;

    /**
     * Handle the uploaded CSV file
     *
     * @return void
     */
    public function updatedImportCSV()
    {
        // Open the import view
        $this->importing = true;

        $this->handleImportCSV();

        // Close editor in case it was open
        $this->selectedRow = null;
        $this->dispatch('closeEditor');
    }

    public function handleImportCSV()
    {
        // Prepare import columns
        $this->importColumns = [];
        $this->importColumnOptions = [];
        $this->importColumnCount = 0;
        $labels = [];
        foreach ($this->allowImport['columns'] as $key => $value) {
            if (! is_string($key) && ! is_array($value)) {
                $key = $value;
            }
            $this->importColumnOptions[] = $key;
            $labels[Str::slug($this->getAttribute($key)->label)] = $key;
        }

        // Initialize attribute data
        $attributes = $this->importAttributes()->pluck('name')->toArray();
        $this->data = $this->getModel()->only($attributes);
        // Pivot attributes need to be initialized as empty arrays
        foreach ($this->importAttributes()->where('type', 'pivot') as $attribute) {
            $this->data[$attribute->name] = [];
        }
        // Set default values for attributes that have them
        foreach ($this->importAttributes()->where('default') as $attribute) {
            $this->data[$attribute->name] = $attribute->default;
        }

        // Handle CSV file
        if ($this->importCSV) {
            $handle = fopen($this->importCSV->getRealPath(), 'r');

            // Determine the delimiter by checking the first line for ; or ,
            $firstLine = fgets($handle);
            $delimiter = Str::contains($firstLine, ';') ? ';' : ',';
            rewind($handle);

            $this->importData = [];
            $this->importRows = [];
            while (($row = fgetcsv($handle, null, $delimiter)) !== false) {
                $this->importData[] = $row;
                $this->importRows[] = true;
                $this->importColumnCount = max($this->importColumnCount ?? 0, count($row));
            }

            fclose($handle);

            // Reset the file input
            $this->importCSV = null;
        }

        // Determine if the first line is a header row by checking if any of the values match the allowed columns
        foreach ($this->importData[0] as $key => $value) {
            if (isset($labels[Str::slug($value)])) {
                $this->importColumns[$key] = $labels[Str::slug($value)];
            }
            if (in_array($value, $this->importColumnOptions)) {
                $this->importColumns[$key] = $value;
            }
        }
        if (count($this->importColumns)) {
            $this->importRows[0] = false;
        }
    }

    // Return the import data selected rows mapped to the selected columns
    public function importData(): array
    {
        $data = [];
        foreach ($this->importData as $index => $row) {
            if (! $this->importRows[$index]) {
                continue;
            }
            $line = [];
            foreach ($this->importColumns as $key => $attribute) {
                if ($attribute) {
                    $line[$attribute] = $row[$key] ?? null;
                }
            }
            $data[$index] = $line;
        }

        return $data;
    }

    /**
     * The model data which can be updated by the editor
     */
    public array $data;

    public function importAttributes()
    {
        $attributes = [];
        foreach ($this->allowImport['attributes'] as $key => $value) {
            if (! is_string($key) && ! is_array($value)) {
                $key = $value;
            }
            $attributes[] = $this->getAttribute($key);
        }

        return collect($attributes);
    }

    public function import(bool $save = true)
    {
        // Reset import errors
        $this->importErrors = [];

        // Validate import attributes
        $rules = [];
        foreach ($this->importAttributes()->whereNotNull('validate') as $attribute) {
            $rules['data.'.$attribute->name] = $attribute->validate;
        }
        $validator = Validator::make(['data' => $this->data], $rules, [], $this->validationAttributes());
        if ($validator->fails()) {
            foreach ($validator->messages()->keys() as $fieldKey) {
                $this->dispatch('toast-error', $validator->messages()->first($fieldKey), $fieldKey)->to(Toasts::class);
            }
            $validator->validate();

            return false;
        }
        $this->resetValidation();

        // Determine import data validation rules
        $rules = [];
        foreach ($this->allowImport['columns'] as $key => $value) {
            if (! is_string($key) && ! is_array($value)) {
                $key = $value;
            }
            $attr = $this->getAttribute($key);
            if ($attr->validate) {
                $rules[$key] = $attr->validate;
            }
        }

        $replace = [
            '{id}' => null,
            '{table}' => $this->getModel()->getTable(),
        ];

        // Validate import data
        foreach ($this->importData() as $index => $row) {
            $rulesWithReplacements = $rules;
            foreach ($rulesWithReplacements as $attribute => $attributeRules) {
                foreach ($attributeRules as $key => $rule) {
                    foreach ($replace as $search => $replaceValue) {
                        $rulesWithReplacements[$attribute][$key] = str_replace($search, $replaceValue ?: '', $rulesWithReplacements[$attribute][$key]);
                    }
                }
            }

            $validator = Validator::make($row, $rulesWithReplacements, [], $this->validationAttributes());
            if ($validator->fails()) {
                foreach ($this->importColumns as $key => $attribute) {
                    if ($attribute && $validator->errors()->has($attribute)) {
                        $this->importErrors[$index][$attribute] = $validator->errors()->first($attribute);
                    }
                }
                foreach ($validator->errors()->all() as $messages) {
                    if (empty($this->importErrors[$index])) {
                        $this->importErrors[$index] = true;
                    }
                    $this->dispatch('toast-error', $messages)->to(Toasts::class);
                }
            }
        }

        if (count($this->importErrors) == 0) {
            // Import each row
            foreach ($this->importData() as $index => $row) {
                // Create new model instance
                $model = $this->getModel();
                foreach ($row as $key => $value) {
                    $model->{$key} = $value;
                }
                if ($save) {
                    $model->save();
                    // Handle pivot attributes
                    foreach ($this->importAttributes()->where('type', 'pivot') as $attribute) {
                        $model->{$attribute->name}()->sync($this->data[$attribute->name]);
                    }
                }
            }
            $this->dispatch('toast', __('leap::resource.imported', ['count' => count($this->importData())]))->to(Toasts::class);
        }
    }

    #[On('openImport')]
    public function openImport()
    {
        $this->importing = true;
        $this->selectedRow = null;
    }

    #[On('closeImport')]
    public function closeImport()
    {
        $this->importing = false;
    }

    public function canImport(): bool
    {
        return isset($this->allowImport) && Auth::user()->can(['leap::create', 'leap::update']);
    }

    /**
     * Undocumented function
     */
    public function sortableDone(int $parent_id, int $item_id, int $position): void
    {
        // Check if item is valid
        $item = $this->getModel()->find($item_id);
        if (! $item) {
            $this->dispatch('toast-error', 'Item not found')->to(Toasts::class);

            return;
        }

        if ($this->treeview()) {
            if ($parent_id == 0) {
                $parent_id = null;
                $position--; // Since leap-index-header is at position 0 we need to decrease the position by 1
            } else {
                // Check if parent exists
                $parent = $this->getModel()->find($parent_id);
                if (! $parent) {
                    $this->dispatch('toast-error', 'Parent item not found')->to(Toasts::class);

                    return;
                }
            }

            // Move item to different parent if required
            if ($parent_id != $item->{$this->treeview()->name}) {
                $item->{$this->treeview()->name} = $parent_id;
                $item->save();
            }

            $orderItems = $this->getModel()->where($this->treeview()->name, $parent_id);
        } else {
            $position--; // Since leap-index-header is at position 0 we need to decrease the position by 1
            $orderItems = $this->getModel();
        }

        // Move item to new position within parent
        foreach ($orderItems->where('id', '!=', $item_id)->orderBy($this->sortable()->name)->get(['id', $this->sortable()->name]) as $index => $row) {
            $row->{$this->sortable()->name} = $index >= $position ? $index + 1 : $index;
            $row->save();
        }
        $item->{$this->sortable()->name} = $position;
        $item->save();

        $this->dispatch('toast', __('leap::resource.moved', ['title' => $item->{$this->allAttributes()->first()->name}]))->to(Toasts::class);

        $this->dispatch('recalculate-columns');
    }

    /**
     * Return a model instance
     */
    public function getModel(): Model
    {
        $model = $this->model ?: 'App\\'.(is_dir(app_path('Models')) ? 'Models\\' : '').class_basename(static::class);
        $model = new $model;
        $this->translatable = in_array(HasTranslations::class, class_uses($model)) ? $model->getTranslatableAttributes() : false;

        return $model;
    }

    /**
     * Return the model attributes to show in the index
     */
    public function indexAttributes(): Collection
    {
        return $this->allAttributes(true);
    }

    /**
     * Return all editable model attributes
     *
     * @param  bool  $index  Only return index attributes
     */
    public function allAttributes(bool $index = false): Collection
    {
        if ($index) {
            return collect($this->attributes())->where('index')->sortBy('index');
        } else {
            return collect($this->attributes());
        }
    }

    /**
     * Return the attribute details as defined by the Leap module
     *
     * @param  string  $attribute  The attribute name
     */
    public function getAttribute(string $attribute): ?Attribute
    {
        return $this->allAttributes()->where('name', $attribute)->first();
    }

    /**
     * Sort the index by this attribute
     *
     * @param  string  $attribute  The attribute name
     * @return void
     */
    public function order(string $attribute)
    {
        // Set default orderBy attribute
        $this->orderByDefault ??= $this->orderBy;
        $this->orderDescDefault ??= $this->orderDesc;

        if ($this->getAttribute($attribute)->isAccessor) {
            // If attribute is an accessor restore default orderBy
            $this->orderBy = $this->orderByDefault;
            $this->orderDesc = $this->orderDescDefault;
        } elseif ($this->orderBy == $attribute && ! $this->orderDesc) {
            // If currently ascending sorted by this attribute, change to descending
            $this->orderDesc = true;
        } elseif ($this->orderBy == $attribute && $this->orderDesc) {
            // If currently descending sorted by this attribute set orderBy to null for default sorting
            $this->orderBy = null;
        } else {
            // Set new orderBy attribute
            $this->orderBy = $attribute;
            $this->orderDesc = false;
        }

        $this->dispatch('recalculate-columns');
    }

    /**
     * How a query has to address an attribute's column.
     *
     * A translatable attribute is stored as json ({"nl": "Aap", "en": "Ape"}), so a
     * query naming the column plainly gets the whole object rather than the text in
     * it: ordering compares json objects (every row sorts equal, and descending reads
     * the same as ascending) and a LIKE matches the raw json, keys included -- which
     * is why searching an index for "nl" returned every row.
     *
     * The json path is what the query builder turns into the driver's own accessor --
     * json_unquote(json_extract(..)) on MySQL, json_extract(..) on SQLite -- so this
     * stays out of writing SQL by hand.
     *
     * @param  string|null  $locale  Which language to address; the active one by default
     */
    protected function localeColumn(string $column, ?string $locale = null): string
    {
        // Already addresses a json key of its own (an attribute named "meta->author")
        if (str_contains($column, '->')) {
            return $column;
        }

        $attribute = $this->getAttribute($column);

        return $attribute && $this->hasTranslation($attribute)
            ? $column.'->'.($locale ?: app()->getLocale())
            : $column;
    }

    /**
     * Every language a translatable attribute has to be searched in, and the one
     * language a plain column has.
     *
     * The panel is one place where the site's languages sit side by side, so a title
     * is worth finding by whatever language it is written in -- being in the Dutch
     * panel is no reason to be unable to find a page by its English title.
     *
     * @return array<int, string>
     */
    protected function searchColumns(string $column): array
    {
        $attribute = $this->getAttribute($column);

        if (! $attribute || ! $this->hasTranslation($attribute) || str_contains($column, '->')) {
            return [$column];
        }

        $locales = array_keys(config('leap.locales') ?: []) ?: [app()->getLocale()];

        return array_map(fn (string $locale): string => $this->localeColumn($column, $locale), $locales);
    }

    /**
     * Return an array of all rows with the id and the index attributes
     *
     * @param  int|null  $parent_id  The parent id for the treeview
     */
    public function indexRows(?int $parent_id = null): Collection
    {
        return $this->rows($parent_id, true);
    }

    /**
     * Return an array of all rows with the id and all attributes
     *
     * @param  int|null  $parent_id  The parent id for the treeview
     * @param  bool  $index  Only return index attributes
     * @param  bool  $filtered  Apply filters if true
     */
    public function rows(?int $parent_id = null, bool $index = false, bool $filtered = true): Collection
    {
        $data = $this->getModel();

        if ($this->treeview()) {
            $data = $data->where($this->treeview()->name, $parent_id);
        }

        // Eager load relationships
        if ($this->with) {
            $data = $data->with($this->with);
        }
        // Eager load relationship counts
        if ($this->withCount) {
            $data = $data->withCount($this->withCount);
        }

        // Apply the filters that are keyed by an id on the query, the rest is applied on
        // the rendered values after they are fetched below
        $postFilters = [];
        if ($filtered) {
            foreach ($this->filters as $key => $value) {
                if (! $this->filterIsActive($value)) {
                    continue;
                }
                $attribute = $this->allAttributes($index)->where('name', $key)->first();
                if ($attribute?->type == 'pivot') {
                    $data = $data->whereHas($key, function ($query) use ($attribute, $value) {
                        $query->where($query->getModel()->getTable().'.'.$attribute->options['id_column'], $value);
                    });
                } elseif ($attribute?->type == 'foreign') {
                    $data = $data->where($key, $value);
                } else {
                    $postFilters[$key] = $value;
                }
            }
        }

        // Check if data needs to be sorted by a foreign or pivot attribute, in that case we can't use orderBy on the model but manually sort the array later
        $sortForeign = $this->orderBy && in_array($this->allAttributes($index)->where('name', $this->orderBy)->first()?->type, ['foreign', 'pivot']);

        if ($this->orderBy && ! $sortForeign) {
            if (is_array($this->orderBy)) {
                foreach ($this->orderBy as $orderBy => $desc) {
                    $data = $data->orderBy($this->localeColumn($orderBy ?: $desc), $desc == 'desc' ? 'desc' : ($this->orderDesc ? 'desc' : 'asc'));
                }
            } else {
                $data = $data->orderBy($this->localeColumn($this->orderBy), $this->orderDesc ? 'desc' : 'asc');
            }
        }

        // Apply search in the resource index
        if ($this->search && $this->canSearch() && $index) {
            $data = $data->where(function ($query) use ($index) {
                foreach ($this->allAttributes($index)->where('searchable') as $attribute) {
                    // A translatable attribute is searched per language: naming the json
                    // column plainly matches its keys, so "nl" found every row.
                    foreach ($this->searchColumns($attribute->name) as $column) {
                        $query->orWhere($column, 'like', '%'.$this->search.'%');
                    }
                }
            });
        }

        $merge = ['id'];
        if ($this->active) {
            $merge[] = $this->active;
        }
        if ($this->treeview()) {
            $merge[] = $this->treeview()->name;
        }

        // Get all index columns without accessors but including required accessor columns, the id and the treeview parent id if required
        $accessorColumns = $this->allAttributes($index)->where('isAccessor')->pluck('accessorColumns')->flatten()->unique()->toArray();
        $data = $data->get(array_merge($merge, $accessorColumns, $this->allAttributes($index)->where('isAccessor', false)->where('type', '!=', 'pivot')->pluck('name')->toArray()));

        // Replace all foreign keys with their value
        foreach ($this->allAttributes($index)->where('type', 'foreign') as $foreignAttribute) {
            $values = $foreignAttribute->getValues();
            foreach ($data as $id => $row) {
                $data[$id][$foreignAttribute->name] = $values[$row[$foreignAttribute->name]] ?? null;
            }
        }

        // Replace all attributes with -> in name with their value from the json column json_unquote(json_extract(`json`, '$."key"'))
        foreach ($this->allAttributes($index) as $attribute) {
            if (str_contains($attribute->name, '->')) {
                [$column, $key] = explode('->', $attribute->name);
                foreach ($data as $id => $row) {
                    $data[$id][$attribute->name] = $data[$id]['json_unquote(json_extract(`'.$column.'`, \'$."'.$key.'"\'))'];
                }
            }
        }

        // Replace all pivot keys with their value
        foreach ($this->allAttributes($index)->where('type', 'pivot') as $foreignAttribute) {
            $values = $foreignAttribute->getValues();
            foreach ($data as $id => $row) {
                $array_values = [];
                foreach ($row->{$foreignAttribute->name}->pluck('id')->toArray() as $value_id) {
                    $array_values[] = $values[$value_id] ?? null;
                }
                $data[$id][$foreignAttribute->name] = implode(', ', $array_values);
            }
        }

        if ($sortForeign) {
            if (is_array($data)) {
                Leap::sortBy($data, $this->orderBy, $this->orderDesc);
            } else {
                $data = $data->sortBy($this->orderBy, SORT_NATURAL, $this->orderDesc);
            }
        }

        foreach ($postFilters as $key => $value) {
            $data = $data->where($key, $value);
        }

        return $data;
    }

    /**
     * Whether the filter value stands for an actual filter, 'NULL' is the empty option
     */
    private function filterIsActive(mixed $value): bool
    {
        return $value != 'NULL' && ($value || $value === '0' || $value === '');
    }

    /**
     * Rerender the component when updateIndex event is triggered
     *
     * This is mostly used after updating a model.
     *
     * @return void
     */
    #[On('updateIndex')]
    public function updateIndex(?int $id = null)
    {
        $this->dispatch('recalculate-columns');
        $this->selectedRow = $id;
    }

    public function downloadCSVfile()
    {
        $data = [];
        $keys = [];
        foreach ($this->rows()->toArray() as $row) {
            $line = [];
            foreach ($this->allAttributes() as $attribute) {
                if (is_array($row[$attribute->name] ?? null)) {
                    foreach ($row[$attribute->name] as $key => $value) {
                        if (isset($line[$key])) {
                            $keys[$attribute->name.'.'.$key] = 'value';
                            $line[$attribute->name.'.'.$key] = $value;
                        } else {
                            $keys[$key] = 'value';
                            $line[$key] = $value;
                        }
                    }
                } elseif (! $attribute->isAccessor) {
                    $keys[$attribute->name] = $attribute->type;
                    $line[$attribute->name] = $row[$attribute->name] ?? null;
                }
            }
            $data[] = $line;
        }

        // Reorder keys and values
        foreach ($data as $id => $row) {
            $data[$id] = [];
            foreach ($keys as $key => $type) {
                if (is_array($row[$key] ?? null)) {
                    $data[$id][$key] = implode(', ', $row[$key]);
                } else {
                    $data[$id][$key] = $row[$key] ?? null;
                }
            }
        }

        $filename = Str::slug($this->title).'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($data, $keys) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_keys($keys), escape: '');
            foreach ($data as $line) {
                fputcsv($handle, $line, escape: '');
            }
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Search the resource index
     */
    public ?string $search = null;

    /**
     * Determine if there are searchable attributes
     */
    public function canSearch(): bool
    {
        return $this->allAttributes()->where('searchable')->count() > 0;
    }

    /**
     * When search input is updated update index
     *
     * @return void
     */
    public function updatedSearch(string $search)
    {
        if ($search == '') {
            $this->search = null;
        }
        $this->dispatch('recalculate-columns');
    }

    public function filterBy($attribute, $value)
    {
        $this->filters[$attribute] = $value;
    }

    /**
     * Extra buttons to add to editor toolbar just before the cancel/close button
     *
     * @return array
     */
    public function editorButtons()
    {
        return [
            // [
            //     'label' => 'Opslaan',
            //     'icon' => 'fas-save',
            //     'livewire' => 'ComponentName',
            //     'action' => 'method',
            // ],
        ];
    }

    public function render()
    {
        $this->log('read');

        /** @disregard P1013 Undefined method intelephense error */
        return view('leap::livewire.resource')->layout('leap::layouts.app');
    }
}
