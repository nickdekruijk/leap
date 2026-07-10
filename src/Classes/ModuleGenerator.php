<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * Detects a Leap Resource's `attributes()` plan from an Eloquent model's table
 * schema and casts, and renders the resulting PHP source. Used by `leap:module`.
 *
 * @phpstan-type ColumnPlan array{
 *     name: string,
 *     skip: bool,
 *     indexOnly: bool,
 *     baseType: string,
 *     foreignModel: ?string,
 *     enumValues: ?array,
 *     required: bool,
 *     unique: bool,
 *     titleColumn: bool,
 *     slugFrom: ?string,
 *     validateUrl: bool,
 *     default: mixed,
 *     label: string|array<string, string>,
 * }
 */
class ModuleGenerator
{
    /**
     * Column names never turned into an editable attribute.
     */
    protected const SKIPPED_COLUMNS = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * Column name keywords that mark a boolean as the module's $active flag.
     */
    protected const ACTIVE_KEYWORDS = ['active', 'published', 'enabled', 'visible'];

    /**
     * Column name keywords that mark an int column as a manual drag-sort column.
     */
    protected const SORT_KEYWORDS = ['sort', 'position', 'order'];

    /**
     * Column name keywords for a "newest first" default order (news-style content).
     */
    protected const RECENCY_ORDER_KEYWORDS = ['created_at', 'published_at', 'posted_at'];

    /**
     * Column name keywords for a "soonest first" default order (event-style content).
     */
    protected const UPCOMING_ORDER_KEYWORDS = ['event_date', 'start_date', 'starts_at', 'begin_at', 'begins_at', 'date'];

    /**
     * Column name keywords that suggest a rich text (TinyMCE) editor over a plain textarea.
     */
    protected const RICHTEXT_KEYWORDS = ['body', 'content', 'intro'];

    /**
     * Model basename keyword => blade-icon name, used as a starting suggestion only.
     */
    protected const ICON_KEYWORDS = [
        'user' => 'fas-users',
        'customer' => 'fas-users',
        'member' => 'fas-users',
        'page' => 'fas-sitemap',
        'event' => 'fas-calendar-days',
        'news' => 'fas-newspaper',
        'product' => 'fas-box',
        'item' => 'fas-box',
        'order' => 'fas-cart-shopping',
        'invoice' => 'fas-cart-shopping',
        'category' => 'fas-tags',
        'tag' => 'fas-tags',
        'comment' => 'fas-comment',
        'review' => 'fas-comment',
        'setting' => 'fas-gear',
        'config' => 'fas-gear',
        'image' => 'fas-image',
        'photo' => 'fas-image',
        'media' => 'fas-image',
        'file' => 'fas-file',
        'document' => 'fas-file',
        'role' => 'fas-user-lock',
        'permission' => 'fas-user-lock',
        'log' => 'fas-list',
    ];

    protected Model $model;

    protected string $table;

    /**
     * @var array<int, array{name: string, type_name: string, nullable: bool, default: mixed}>
     */
    protected array $columns;

    /**
     * @var array<int, array{columns: array<int, string>, unique: bool, primary: bool}>
     */
    protected array $indexes;

    /**
     * @var array<int, array{columns: array<int, string>, foreign_table: string}>
     */
    protected array $foreignKeys;

    public function __construct(protected string $modelClass)
    {
        $this->model = new $modelClass;
        $this->table = $this->model->getTable();
        $this->columns = Schema::getColumns($this->table);
        $this->indexes = Schema::getIndexes($this->table);
        $this->foreignKeys = Schema::getForeignKeys($this->table);
    }

    public function table(): string
    {
        return $this->table;
    }

    /**
     * The `tinyint(1)` convention MySQL/SQLite Laravel migrations use for
     * `boolean()` columns — `Schema::getColumns()` reports the raw driver type
     * name (`tinyint`), never a normalised `boolean`, so this is the real signal
     * when the model doesn't declare an explicit cast.
     */
    protected function columnLooksBoolean(array $column): bool
    {
        return in_array($column['type_name'], ['boolean', 'tinyint']);
    }

    /**
     * `Schema::getColumns()` reports "no default" as an empty string, not null,
     * for both SQLite and MySQL — so `default === null` never actually fires.
     */
    protected function hasNoDefault(array $column): bool
    {
        return $column['default'] === null || $column['default'] === '';
    }

    protected function isUniqueColumn(string $name): bool
    {
        foreach ($this->indexes as $index) {
            if ($index['unique'] && ! $index['primary'] && $index['columns'] === [$name]) {
                return true;
            }
        }

        return false;
    }

    protected function foreignModelFor(string $name): ?string
    {
        foreach ($this->foreignKeys as $foreignKey) {
            if ($foreignKey['columns'] === [$name]) {
                return 'App\\Models\\'.Str::studly(Str::singular($foreignKey['foreign_table']));
            }
        }

        // No formal FK constraint, but the naming convention still implies one.
        if (str_ends_with($name, '_id')) {
            $guess = 'App\\Models\\'.ucfirst(Str::camel(substr($name, 0, -3)));

            return class_exists($guess) ? $guess : null;
        }

        return null;
    }

    protected function enumValuesFor(string $column): ?array
    {
        $cast = $this->model->getCasts()[$column] ?? null;
        if (is_string($cast) && enum_exists($cast)) {
            return collect($cast::cases())->mapWithKeys(fn ($case) => [
                $case->value ?? $case->name => Str::headline($case->name),
            ])->all();
        }

        return null;
    }

    protected function isTranslatable(string $column): bool
    {
        if (! in_array(HasTranslations::class, class_uses_recursive($this->model))) {
            return false;
        }

        return in_array($column, $this->model->getTranslatableAttributes());
    }

    /**
     * Columns the model already exposes as per-locale (Spatie `HasTranslations`).
     * Leap's `Resource::getModel()` picks these up automatically for the editor —
     * nothing to change in the generated `Attribute::make()` chain — so this is
     * only used to print a reminder, not to alter detection.
     *
     * @return array<int, string>
     */
    public function translatableColumns(): array
    {
        return collect($this->columns)
            ->pluck('name')
            ->filter(fn (string $name) => $this->isTranslatable($name))
            ->values()
            ->all();
    }

    /**
     * Best-guess title column: the first non-skipped varchar column that isn't a
     * foreign key, preferring one literally named "title" or "name".
     */
    protected function titleColumn(): ?string
    {
        $candidates = collect($this->columns)
            ->pluck('name')
            ->filter(fn (string $name) => in_array($name, ['title', 'name']))
            ->first();

        if ($candidates) {
            return $candidates;
        }

        foreach ($this->columns as $column) {
            if (in_array($column['name'], self::SKIPPED_COLUMNS)
                || $column['name'] === 'id'
                || $this->foreignModelFor($column['name'])) {
                continue;
            }
            if (in_array($column['type_name'], ['varchar', 'string', 'char'])) {
                return $column['name'];
            }
        }

        return null;
    }

    /**
     * Build the per-column attribute plan, in schema order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function columnPlans(): array
    {
        $titleColumn = $this->titleColumn();
        $castBooleans = collect($this->model->getCasts())->filter(fn ($cast) => in_array($cast, ['bool', 'boolean']))->keys();

        $plans = [];

        foreach ($this->columns as $column) {
            $name = $column['name'];

            if (in_array($name, self::SKIPPED_COLUMNS)) {
                continue;
            }

            if ($name === 'id') {
                $plans[] = $this->plan($name, indexOnly: true);

                continue;
            }

            $cast = $this->model->getCasts()[$name] ?? null;
            $isBoolean = $castBooleans->contains($name) || $this->columnLooksBoolean($column);
            $foreignModel = $this->foreignModelFor($name);
            $enumValues = $this->enumValuesFor($name);

            $isSortColumn = in_array($column['type_name'], ['integer', 'bigint', 'smallint'])
                && $this->matchesKeyword($name, self::SORT_KEYWORDS);

            $baseType = match (true) {
                $isBoolean => 'switch',
                $foreignModel !== null => 'foreign',
                $enumValues !== null => 'select',
                $isSortColumn => 'sortable',
                in_array($column['type_name'], ['date']) => 'date',
                in_array($column['type_name'], ['datetime', 'timestamp']) => 'datetime',
                $column['type_name'] === 'time' => 'time',
                in_array($column['type_name'], ['integer', 'bigint', 'smallint', 'decimal', 'float', 'double']) => 'number',
                in_array($cast, ['array', 'json', 'collection']) || $column['type_name'] === 'json' => 'json',
                in_array($column['type_name'], ['text', 'longtext', 'mediumtext']) => $this->matchesKeyword($name, self::RICHTEXT_KEYWORDS) ? 'richtext' : 'textarea',
                $name === 'email' => 'email',
                $name === 'password' => 'password',
                $name === 'slug' => 'slug',
                default => 'text',
            };

            $validateUrl = $baseType === 'text' && in_array($name, ['url', 'website']);

            $plans[] = $this->plan(
                name: $name,
                baseType: $baseType,
                foreignModel: $foreignModel,
                enumValues: $enumValues,
                required: ! $column['nullable'] && $this->hasNoDefault($column) && $name !== 'id',
                unique: $this->isUniqueColumn($name),
                titleColumn: $name === $titleColumn,
                slugFrom: $baseType === 'slug' ? ($titleColumn ?: 'title') : null,
                validateUrl: $validateUrl,
                default: $this->hasNoDefault($column) ? null : $column['default'],
                label: Str::headline($name),
            );
        }

        return $plans;
    }

    /**
     * @return array<string, mixed>
     */
    protected function plan(
        string $name,
        bool $indexOnly = false,
        string $baseType = 'text',
        ?string $foreignModel = null,
        ?array $enumValues = null,
        bool $required = false,
        bool $unique = false,
        bool $titleColumn = false,
        ?string $slugFrom = null,
        bool $validateUrl = false,
        mixed $default = null,
        string $label = '',
    ): array {
        return [
            'name' => $name,
            'indexOnly' => $indexOnly,
            'baseType' => $baseType,
            'foreignModel' => $foreignModel,
            'enumValues' => $enumValues,
            'required' => $required,
            'unique' => $unique,
            'titleColumn' => $titleColumn,
            'slugFrom' => $slugFrom,
            'validateUrl' => $validateUrl,
            'default' => $default,
            'label' => $label,
        ];
    }

    protected function matchesKeyword(string $name, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Module-level suggestions: icon, priority, $active column, $orderBy/$orderDesc.
     *
     * @return array{icon: string, priority: int, activeColumn: ?string, sortColumn: ?string, orderByColumn: ?string, orderDesc: bool}
     */
    public function modulePlan(): array
    {
        $activeColumn = null;
        $sortColumn = null;
        foreach ($this->columns as $column) {
            $cast = $this->model->getCasts()[$column['name']] ?? null;
            $isBoolean = in_array($cast, ['bool', 'boolean']) || $this->columnLooksBoolean($column);

            if (! $activeColumn && $isBoolean && $this->matchesKeyword($column['name'], self::ACTIVE_KEYWORDS)) {
                $activeColumn = $column['name'];
            }

            if (! $sortColumn
                && in_array($column['type_name'], ['integer', 'bigint', 'smallint'])
                && $this->matchesKeyword($column['name'], self::SORT_KEYWORDS)) {
                $sortColumn = $column['name'];
            }
        }

        [$orderByColumn, $orderDesc] = $sortColumn ? [null, false] : $this->guessDateOrder();

        return [
            'icon' => static::guessIcon(class_basename($this->modelClass)),
            'priority' => 100,
            'activeColumn' => $activeColumn,
            'sortColumn' => $sortColumn,
            'orderByColumn' => $orderByColumn,
            'orderDesc' => $orderDesc,
        ];
    }

    /**
     * Guess an $orderBy/$orderDesc default from a single, confidently-named date
     * column. Returns [null, false] when there's no sort column and either no date
     * column matches a known keyword, or more than one plausibly could — silence
     * is safer than a wrong guess here.
     *
     * @return array{0: ?string, 1: bool}
     */
    protected function guessDateOrder(): array
    {
        $dateColumns = collect($this->columns)->filter(
            fn ($column) => in_array($column['type_name'], ['date', 'datetime', 'timestamp'])
                && ! in_array($column['name'], self::SKIPPED_COLUMNS)
        );

        $matches = [];
        foreach ($dateColumns as $column) {
            if (in_array($column['name'], self::RECENCY_ORDER_KEYWORDS)) {
                $matches[] = [$column['name'], true];
            } elseif ($this->matchesKeyword($column['name'], self::UPCOMING_ORDER_KEYWORDS)) {
                $matches[] = [$column['name'], false];
            }
        }

        return count($matches) === 1 ? $matches[0] : [null, false];
    }

    public static function guessIcon(string $modelBasename): string
    {
        $needle = Str::lower(Str::snake($modelBasename));

        foreach (self::ICON_KEYWORDS as $keyword => $icon) {
            if (str_contains($needle, $keyword)) {
                return $icon;
            }
        }

        return 'fas-table';
    }

    /**
     * Render a single `Attribute::make(...)->...,` line (without leading indent or
     * trailing newline) from a (possibly interactively overridden) column plan.
     *
     * @param  array<string, string>|null  $locales  config('leap.locales'), or null for a plain string label
     */
    public function renderAttributeLine(array $plan, ?array $locales): string
    {
        $chain = "Attribute::make('{$plan['name']}')";

        if ($plan['indexOnly']) {
            return $chain.'->indexOnly(),';
        }

        $chain .= match ($plan['baseType']) {
            'switch' => '->switch()',
            'foreign' => '->foreign('.$plan['foreignModel'].'::class)->filterable()',
            'select' => '->select()->values('.$this->renderPhpArray($plan['enumValues']).')',
            'date' => '->date()',
            'datetime' => '->datetime()',
            'time' => '->time()',
            'number' => '->number()',
            'sortable' => '->sortable()',
            'json' => '->json()',
            'richtext' => '->richtext()',
            'textarea' => '->textarea()',
            'email' => '->email()',
            'password' => '->password()',
            'slug' => "->unique()->slugFrom('".$plan['slugFrom']."')",
            default => '',
        };

        if ($plan['titleColumn']) {
            $chain .= '->index(1)->searchable()';
        }

        if ($plan['unique'] && $plan['baseType'] !== 'slug') {
            $chain .= '->unique()';
        }

        if ($plan['required']) {
            $chain .= '->required()';
        }

        if ($plan['validateUrl']) {
            $chain .= "->validate('url')";
        }

        if ($plan['baseType'] === 'switch' && $plan['default'] !== null) {
            $chain .= '->default('.($plan['default'] ? 'true' : 'false').')';
        }

        $chain .= '->'.$this->renderLabelCall('label', $plan['label'], $locales);

        return $chain.',';
    }

    /**
     * @param  string|array<string, string>  $text  A plain string, or an explicit
     *                                              locale => text map (e.g. from an interactive per-locale prompt) that
     *                                              may include locales beyond config('leap.locales') (always at least 'en').
     */
    protected function renderLabelCall(string $method, string|array $text, ?array $locales): string
    {
        if (is_array($text)) {
            $pairs = [];
            foreach ($text as $locale => $value) {
                $pairs[] = var_export($locale, true).' => '.var_export($value, true);
            }

            return "{$method}([".implode(', ', $pairs).'])';
        }

        if (! $locales) {
            return "{$method}(".var_export($text, true).')';
        }

        $pairs = [];
        foreach (array_keys($locales) as $locale) {
            $pairs[] = var_export($locale, true).' => '.var_export($text, true);
        }

        return "{$method}([".implode(', ', $pairs).'])';
    }

    protected function renderPhpArray(array $values): string
    {
        $pairs = [];
        foreach ($values as $key => $value) {
            $pairs[] = var_export($key, true).' => '.var_export($value, true);
        }

        return '['.implode(', ', $pairs).']';
    }

    /**
     * Render a brand-new Resource class from scratch.
     *
     * @param  array<int, array<string, mixed>>  $columnPlans
     * @param  array{icon: string, priority: int, activeColumn: ?string, sortColumn: ?string, orderByColumn: ?string, orderDesc: bool}  $modulePlan
     */
    public function renderClass(string $className, array $columnPlans, array $modulePlan): string
    {
        $locales = config('leap.locales');
        $lines = array_map(fn ($plan) => '            '.$this->renderAttributeLine($plan, $locales), $columnPlans);

        $header = [];
        $header[] = "    public \$model = {$this->modelClass}::class;";
        $header[] = '    public $icon = '.var_export($modulePlan['icon'], true).';';
        $header[] = "    public \$priority = {$modulePlan['priority']};";

        $titleLabel = Str::headline(Str::plural(class_basename($this->modelClass)));
        $header[] = '    public $title = '.$this->renderLabelExpression($titleLabel, $locales).';';

        if ($modulePlan['activeColumn']) {
            $header[] = "    public \$active = '{$modulePlan['activeColumn']}';";
        }

        if ($modulePlan['sortColumn']) {
            $header[] = "    public \$orderBy = '{$modulePlan['sortColumn']}';";
        } elseif ($modulePlan['orderByColumn']) {
            $header[] = "    public \$orderBy = '{$modulePlan['orderByColumn']}';";
            if ($modulePlan['orderDesc']) {
                $header[] = '    public $orderDesc = true;';
            }
        }

        return <<<PHP
        <?php

        namespace App\Leap;

        use {$this->modelClass};
        use NickDeKruijk\Leap\Classes\Attribute;
        use NickDeKruijk\Leap\Resource;

        class {$className} extends Resource
        {
        {$this->joinLines($header)}

            public function attributes(): array
            {
                return [
        {$this->joinLines($lines)}
                ];
            }
        }

        PHP;
    }

    protected function renderLabelExpression(string $text, ?array $locales): string
    {
        if (! $locales) {
            return var_export($text, true);
        }

        $pairs = [];
        foreach (array_keys($locales) as $locale) {
            $pairs[] = "\n        ".var_export($locale, true).' => '.var_export($text, true).',';
        }

        return '['.implode('', $pairs)."\n    ]";
    }

    protected function joinLines(array $lines): string
    {
        return implode("\n", $lines);
    }

    /**
     * Column names already declared as `Attribute::make('name')` in an existing
     * module file, found via a plain regex scan (matching the string-patching
     * style already used by TemplateCommand, no PHP parser dependency needed).
     */
    public static function existingColumns(string $contents): array
    {
        preg_match_all('/Attribute::make\(\s*[\'"](\w+)[\'"]\s*\)/', $contents, $matches);

        return $matches[1];
    }

    /**
     * Whether $contents looks like a Resource with a plain `attributes()` array we
     * can safely append to.
     */
    public static function looksMergeable(string $contents): bool
    {
        return (bool) preg_match('/function\s+attributes\s*\([^)]*\)[^{]*\{.*return\s*\[/s', $contents);
    }

    /**
     * Insert new attribute lines just before the closing `];` of the
     * `attributes()` method's return array. Returns null if the closing bracket
     * of that return statement can't be located unambiguously.
     *
     * @param  array<int, string>  $newLines  Fully rendered `Attribute::make(...)->...,` lines (no indent)
     */
    public static function insertAttributes(string $contents, array $newLines): ?string
    {
        if (! preg_match('/function\s+attributes\s*\([^)]*\)[^{]*\{.*?return\s*\[/s', $contents, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $returnArrayStart = $match[0][1] + strlen($match[0][0]);
        $closingPos = self::findMatchingBracket($contents, $returnArrayStart - 1);
        if ($closingPos === null) {
            return null;
        }

        $indent = '            ';
        $insertion = implode("\n", array_map(fn ($line) => $indent.$line, $newLines))."\n";

        return substr($contents, 0, $closingPos).$insertion.substr($contents, $closingPos);
    }

    /**
     * Given the offset of an opening `[`, find the matching closing `]`'s offset,
     * accounting for nested brackets and quoted strings.
     */
    protected static function findMatchingBracket(string $contents, int $openPos): ?int
    {
        $depth = 0;
        $length = strlen($contents);
        $inString = null;

        for ($i = $openPos; $i < $length; $i++) {
            $char = $contents[$i];

            if ($inString !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $inString) {
                    $inString = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = $char;
            } elseif ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }
}
