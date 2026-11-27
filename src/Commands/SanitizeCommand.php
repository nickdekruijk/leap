<?php

namespace NickDeKruijk\Leap\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Classes\RichtextSanitizer;
use NickDeKruijk\Leap\Controllers\ModuleController;
use NickDeKruijk\Leap\Resource;
use Throwable;

/**
 * What the rich-text filter would take out of the content that is stored now.
 *
 * leap.tinymce.sanitize filters a value when it is saved, and what it does not
 * allow is gone from that value for good. A site that embeds an iframe from its
 * own host, or keeps a script in a text on purpose, should know that before the
 * filter is on. This reads every rich-text field of every record, runs the filter
 * over it and reports what would go. It never writes.
 *
 * It answers a second question on the way: whether something is stored that
 * should not be, since a script or an event handler in a record shows up here
 * whether the filter is on or not.
 */
class SanitizeCommand extends Command
{
    protected $signature = 'leap:sanitize
        {--show= : Print the stored and the filtered HTML of one record, as Module:id, for example Page:12}';

    protected $description = 'Report what the rich-text filter would remove from the stored content, without changing anything';

    private const CHUNK = 200;

    /** How many records to name per kind of thing that goes. */
    private const EXAMPLES = 5;

    public function handle(RichtextSanitizer $sanitizer): int
    {
        if ($reason = RichtextSanitizer::unavailable()) {
            $this->components->error($reason);

            return self::FAILURE;
        }

        $show = $this->option('show');
        if ($show !== null && ! preg_match('/^([\w\\\\]+):(.+)$/', (string) $show, $only)) {
            $this->components->error('--show takes Module:id, for example Page:12.');

            return self::INVALID;
        }

        $dropped = [];
        $examples = [];
        $records = 0;
        $values = 0;
        $changing = 0;
        $undefined = 0;
        $unread = [];

        foreach (ModuleController::getAllModules() as $module) {
            if (! $module instanceof Resource) {
                continue;
            }
            $name = class_basename($module);
            if ($show !== null && strcasecmp($only[1], $name) !== 0 && strcasecmp($only[1], $module::class) !== 0) {
                continue;
            }

            // A module may build its attributes from the request or the user, and
            // the console has neither. Say which ones were not read, not nothing.
            try {
                $fields = $this->richtextFields($module);
            } catch (Throwable $e) {
                $unread[] = $name.' ('.$e->getMessage().')';

                continue;
            }
            if ($fields['plain'] === [] && $fields['sections'] === []) {
                continue;
            }

            $query = $module->getModel()->newQueryWithoutScopes();
            if ($show !== null) {
                $query->whereKey($only[2]);
            }

            $query->chunkById(self::CHUNK, function ($chunk) use ($module, $name, $fields, $sanitizer, $show, &$dropped, &$examples, &$records, &$values, &$changing, &$undefined): void {
                foreach ($chunk as $record) {
                    $records++;
                    foreach ($this->richtextValues($record, $module, $fields, $undefined) as $path => $html) {
                        $values++;
                        $goes = $sanitizer->dropped($html);
                        if ($goes === []) {
                            continue;
                        }
                        $changing++;
                        foreach ($goes as $what => $count) {
                            $dropped[$what] = ($dropped[$what] ?? 0) + $count;
                            $examples[$what][$name.' '.$record->getKey()] = true;
                        }
                        if ($show !== null) {
                            $this->newLine();
                            $this->line('  <options=bold>'.$name.' '.$record->getKey().'</>, '.$path);
                            $this->line('  <fg=gray>stored</>');
                            $this->line($html);
                            $this->line('  <fg=gray>filtered</>');
                            $this->line($sanitizer->clean($html));
                        }
                    }
                }
            });
        }

        $this->newLine();

        foreach ($unread as $module) {
            $this->line('  <fg=yellow>Not read, its attributes could not be listed here: '.$module.'</>');
        }

        if ($records === 0) {
            $this->line($show !== null ? '  No such record, or it has no rich text.' : '  No records with rich text.');
            $this->newLine();

            return self::SUCCESS;
        }

        if ($dropped === []) {
            $this->line('  <info>Nothing to remove.</info> '.$this->summary($records, $values));
        } else {
            ksort($dropped);
            $rows = [];
            foreach ($dropped as $what => $count) {
                $where = array_keys($examples[$what]);
                $more = count($where) - self::EXAMPLES;
                $rows[] = [$what, $count, implode(', ', array_slice($where, 0, self::EXAMPLES)).($more > 0 ? ' and '.$more.' more' : '')];
            }
            $this->table(['Would go', 'Times', 'In'], $rows);
            $this->line('  '.$changing.' of '.$this->summary($records, $values).' would change when saved with the filter on.');
            if ($show === null) {
                $this->line('  <fg=gray>php artisan leap:sanitize --show=Module:id prints one record before and after.</>');
            }
        }

        if ($undefined > 0) {
            $this->line('  <fg=yellow>'.$undefined.' '.($undefined === 1 ? 'section has' : 'sections have').' no definition in the module any more and were skipped.</>');
        }
        if (! RichtextSanitizer::enabled()) {
            $this->line('  <fg=gray>The filter is off: leap.tinymce.sanitize. Nothing was changed, and nothing is.</>');
        }
        $this->newLine();

        return self::SUCCESS;
    }

    private function summary(int $records, int $values): string
    {
        return $values.' rich-text '.($values === 1 ? 'value' : 'values').' in '.$records.' '.($records === 1 ? 'record' : 'records');
    }

    /**
     * The rich-text fields of a resource: those of its own, and per sections
     * attribute and section name those inside its sections.
     *
     * @return array{plain: list<string>, sections: array<string, array<string, list<string>>>}
     */
    private function richtextFields(Resource $module): array
    {
        $fields = ['plain' => [], 'sections' => []];

        foreach ($module->attributes() as $attribute) {
            if ($attribute->input === 'tinymce') {
                $fields['plain'][] = $attribute->name;
            } elseif ($attribute->type === 'sections') {
                foreach ($attribute->sections as $section) {
                    $fields['sections'][$attribute->name][$section->name] = collect($section->attributes)
                        ->where('input', 'tinymce')->pluck('name')->values()->all();
                }
            }
        }

        return $fields;
    }

    /**
     * Every stored rich-text value of a record, keyed by where it sits: "body",
     * "body (nl)", "sections 2 text". Read as it is in the database, not through
     * the model, so a cast or an accessor does not stand in between.
     *
     * @param  array{plain: list<string>, sections: array<string, array<string, list<string>>>}  $fields
     * @return array<string, string>
     */
    private function richtextValues(Model $record, Resource $module, array $fields, int &$undefined): array
    {
        $values = [];
        $translatable = $module->translatable() ?: [];

        foreach ($fields['plain'] as $field) {
            $raw = $record->getRawOriginal($field);
            if (in_array($field, $translatable, true) && is_string($raw) && is_array($decoded = json_decode($raw, true))) {
                $raw = $decoded;
            }
            $this->collect($values, $field, $raw);
        }

        foreach ($fields['sections'] as $field => $definitions) {
            $sections = $record->getRawOriginal($field);
            $sections = is_string($sections) ? json_decode($sections, true) : $sections;
            foreach (is_array($sections) ? $sections : [] as $index => $section) {
                if (! is_array($section) || ! isset($definitions[$section['_name'] ?? ''])) {
                    $undefined++;

                    continue;
                }
                foreach ($definitions[$section['_name']] as $name) {
                    $this->collect($values, $field.' '.$index.' '.$name, $section[$name] ?? null);
                }
            }
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function collect(array &$values, string $path, mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $locale => $text) {
                $this->collect($values, $path.' ('.$locale.')', $text);
            }
        } elseif (is_string($value) && $value !== '') {
            $values[$path] = $value;
        }
    }
}
