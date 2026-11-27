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
 * over it and reports what would go, and what would stay but be written another
 * way. It never writes.
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

        // What goes and what is rewritten, each with the records it is in.
        $dropped = [];
        $examples = [];
        $rewritten = [];
        $rewrites = [];
        // Records with something left open at the end of a value.
        $unclosed = [];
        $records = 0;
        $values = 0;
        // Values that lose or change something, and values that only get written
        // the filter's way.
        $changing = 0;
        $renotated = 0;
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

            $query->chunkById(self::CHUNK, function ($chunk) use ($module, $name, $fields, $sanitizer, $show, &$dropped, &$examples, &$rewritten, &$rewrites, &$unclosed, &$records, &$values, &$changing, &$renotated, &$undefined): void {
                foreach ($chunk as $record) {
                    $records++;
                    $label = $name.' '.$record->getKey();
                    foreach ($this->richtextValues($record, $module, $fields, $undefined) as $path => $html) {
                        $values++;
                        $changes = $sanitizer->changes($html);
                        $clean = $sanitizer->clean($html);

                        if ($changes['dropped'] === [] && $changes['rewritten'] === [] && ! $changes['unclosed']) {
                            $renotated += $clean === $html ? 0 : 1;

                            continue;
                        }
                        $changing++;
                        foreach ($changes['dropped'] as $what => $count) {
                            $dropped[$what] = ($dropped[$what] ?? 0) + $count;
                            $examples[$what][$label] = true;
                        }
                        foreach ($changes['rewritten'] as $what => $count) {
                            $rewritten[$what] = ($rewritten[$what] ?? 0) + $count;
                            $rewrites[$what][$label] = true;
                        }
                        if ($changes['unclosed']) {
                            $unclosed[$label] = true;
                        }
                        if ($show !== null) {
                            $this->newLine();
                            $this->line('  <options=bold>'.$label.'</>, '.$path);
                            $this->line('  <fg=gray>stored</>');
                            $this->line($html);
                            $this->line('  <fg=gray>filtered</>');
                            $this->line($clean);
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

        if ($changing === 0) {
            $this->line('  <info>Nothing to remove.</info> '.$this->summary($records, $values));
        } else {
            if ($dropped !== []) {
                $this->table(['Would go', 'Times', 'In'], $this->rows($dropped, $examples));
            }
            if ($rewritten !== []) {
                // Not a loss: the attribute stays, written another way. A link to
                // "mailto: info@example.com" keeps working without the space.
                $this->table(['Would stay, rewritten', 'Times', 'In'], $this->rows($rewritten, $rewrites));
            }
            if ($unclosed !== []) {
                // A tag, an attribute or a comment that is not finished: on a page the
                // markup after the value finishes it. The filter closes or drops it.
                $this->line('  <fg=yellow>Left open at the end of a value, and closed or cut off by the filter:</> '.$this->records(array_keys($unclosed)));
            }
            $this->line('  '.$changing.' of '.$this->summary($records, $values).' would lose or change something when saved with the filter on.');
            if ($show === null) {
                $this->line('  <fg=gray>php artisan leap:sanitize --show=Module:id prints one record before and after.</>');
            }
        }

        if ($renotated > 0) {
            // Everything the filter stores is in its own notation. That is not a loss,
            // and not something to go through record by record.
            $this->line('  <fg=gray>'.$renotated.' other '.($renotated === 1 ? 'value is' : 'values are').' not written the filter\'s way yet (&eacute; for é, <br> for <br />). The first save rewrites '.($renotated === 1 ? 'it' : 'them').', and what '.($renotated === 1 ? 'it says' : 'they say').' stays the same.</>');
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

    /**
     * @param  list<string>  $where
     */
    private function records(array $where): string
    {
        $more = count($where) - self::EXAMPLES;

        return implode(', ', array_slice($where, 0, self::EXAMPLES)).($more > 0 ? ' and '.$more.' more' : '');
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<string, array<string, true>>  $examples
     * @return list<array{string, int, string}>
     */
    private function rows(array $counts, array $examples): array
    {
        ksort($counts);
        $rows = [];

        foreach ($counts as $what => $count) {
            $rows[] = [$what, $count, $this->records(array_keys($examples[$what]))];
        }

        return $rows;
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
