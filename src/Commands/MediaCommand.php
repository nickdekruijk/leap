<?php

namespace NickDeKruijk\Leap\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Models\Mediable;

/**
 * Housekeeping for the links between media and the models that use them.
 *
 * A model deleted through leap takes its links along by itself. This is for the
 * links that were left behind before it did, and for what happens out of its
 * sight: a mass delete, a truncate, an import that renumbers. Left alone they do
 * two things. The file manager counts them, finds one, and refuses to delete that
 * file forever, without anyone being able to see whose link it is. And ids restart
 * at 1 after a migrate:fresh, so the next record to be given number 12 inherits
 * the pictures of the one that had it before.
 */
class MediaCommand extends Command
{
    /**
     * The descriptions are the long ones on purpose: they are what both
     * "leap:media" on its own and "leap:media --help" print.
     */
    protected $signature = 'leap:media
        {--prune : Delete the links whose model no longer exists. Leap takes them along itself when a model is deleted, so this is for the rows left behind before it did, and for the deletes model events never see: a mass Model::where(...)->delete(), a truncate, an import that renumbers}
        {--unknown : Also prune links whose mediable_type names a class this application does not have. Off by default: a renamed or moved model reads exactly like a deleted one, and pruning it throws away the media of records that are still there}
        {--dry-run : Report what --prune would delete, without deleting anything}';

    protected $description = 'Report or prune media links whose model no longer exists';

    /**
     * Ids are read back in blocks, because a whereIn of every id at once runs
     * into the placeholder limit on a table with half a million links.
     */
    private const CHUNK = 500;

    public function handle(): int
    {
        $alive = 0;
        $orphans = 0;
        $unknown = 0;
        $rows = [];

        $this->newLine();
        $this->line('  <fg=gray>The links between media and the models using them.</>');
        $this->newLine();

        foreach (Mediable::query()->distinct()->pluck('mediable_type') as $type) {
            $class = $this->modelFor($type);

            if (! $class) {
                $count = Mediable::where('mediable_type', $type)->count();
                $unknown += $count;
                $rows[] = ['<fg=yellow>'.$type.'</>', '<fg=gray>'.$this->links($count).', no such model in this application</>'];

                if ($this->option('unknown') && $this->option('prune')) {
                    $this->deleteLinks($type);
                }

                continue;
            }

            $dead = $this->prune($type, new $class);

            $alive += $dead['alive'];
            $orphans += $dead['orphans'];
            $rows[] = [
                '<fg=green>'.$type.'</>',
                '<fg=gray>'.$this->links($dead['alive']).' in use'
                    .($dead['orphans'] ? ', </><fg=yellow>'.$this->links($dead['orphans']).' '.$this->word().'</>' : '</>'),
            ];
        }

        $this->report($rows, $alive, $orphans, $unknown);

        return self::SUCCESS;
    }

    /**
     * The model class behind a mediable_type, or null when this application does
     * not have one.
     *
     * A morph map turns the stored name into a class; a class name stored as it
     * is falls through unchanged. is_subclass_of on top of class_exists, so a
     * string that happens to name something that is not a model (a stale alias,
     * an interface) is treated as unknown rather than instantiated.
     *
     * @return class-string<Model>|null
     */
    private function modelFor(string $type): ?string
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
    }

    /**
     * Weigh one mediable_type's links against the table behind it, and delete the
     * ones whose row is gone.
     *
     * Asked without a single scope on purpose. A soft deleted model still owns its
     * media, because it can be restored, and a project's own global scopes
     * (published, tenant, locale) hide rows that are very much still there.
     * newQueryWithoutScopes() covers both at once, where withTrashed() would only
     * cover the first and would fail outright on a model that does not soft delete.
     *
     * @return array{alive: int, orphans: int}
     */
    private function prune(string $type, Model $instance): array
    {
        $key = $instance->getKeyName();
        // Counted up front, so alive and orphaned are both numbers of links
        // rather than one of links and one of models.
        $total = Mediable::where('mediable_type', $type)->count();
        $orphans = 0;

        Mediable::where('mediable_type', $type)->distinct()->pluck('mediable_id')
            ->chunk(self::CHUNK)
            ->each(function (Collection $ids) use ($type, $instance, $key, &$orphans): void {
                // Compared as strings: an id read back off the pivot and the key
                // on the model are not always the same PHP type, and a uuid key
                // is a string on both sides.
                $existing = array_flip(
                    $instance->newQueryWithoutScopes()
                        ->whereIn($key, $ids)
                        ->pluck($key)
                        ->map(fn ($id) => (string) $id)
                        ->all()
                );

                $dead = $ids->reject(fn ($id) => isset($existing[(string) $id]))->values();

                $orphans += $this->deleteLinks($type, $dead);
            });

        return ['alive' => $total - $orphans, 'orphans' => $orphans];
    }

    /**
     * Delete the links of one mediable_type, or of the given ids within it, and
     * return how many rows that was.
     *
     * The Media rows are never touched. The file is still on disk, and a media row
     * with no links left is exactly what the file manager needs in order to be
     * allowed to delete it, which is the whole point of the exercise.
     *
     * @param  Collection<int, mixed>|null  $ids
     */
    private function deleteLinks(string $type, ?Collection $ids = null): int
    {
        $query = Mediable::where('mediable_type', $type);

        if ($ids !== null) {
            if ($ids->isEmpty()) {
                return 0;
            }

            $query->whereIn('mediable_id', $ids);
        }

        $count = $query->count();

        if (! $count) {
            return 0;
        }

        $this->line('  '.($this->pruning() ? 'deleting ' : 'would delete ')
            .$this->links($count).' <fg=gray>of</> '.$type
            .($ids === null ? '' : ' <fg=gray>#'.$ids->take(10)->implode(', #')
                .($ids->count() > 10 ? ' and '.($ids->count() - 10).' more' : '').'</>'));

        if ($this->pruning()) {
            return $query->delete();
        }

        return $count;
    }

    private function links(int $count): string
    {
        return $count.' '.Str::plural('link', $count);
    }

    /**
     * Whether the run is really deleting, so a report of what happened does not
     * read as a report of what could happen.
     */
    private function pruning(): bool
    {
        return $this->option('prune') && ! $this->option('dry-run');
    }

    private function word(): string
    {
        return $this->pruning() ? 'deleted' : 'orphaned';
    }

    /**
     * Where things stand, and what each option would do about it.
     *
     * @param  array<int, array{0: string, 1: string}>  $rows
     */
    private function report(array $rows, int $alive, int $orphans, int $unknown): void
    {
        if ($rows) {
            $this->newLine();
        }

        foreach ($rows as $row) {
            $this->components->twoColumnDetail($row[0], $row[1]);
        }

        if ($rows) {
            $this->newLine();
        }

        $this->components->info(
            $this->links($alive).' in use, '
            .$orphans.' '.$this->word()
            .($unknown ? ', '.$unknown.($this->pruning() && $this->option('unknown') ? ' of an unknown model deleted' : ' of an unknown model') : '')
            .'.'
        );

        if ($unknown && ! $this->option('unknown')) {
            $this->line('  <fg=gray>An unknown model is left alone: a renamed or moved class reads exactly like a deleted one. Use</> <fg=yellow>--unknown</> <fg=gray>once you are sure.</>');
            $this->newLine();
        }

        if (! $this->option('prune')) {
            $this->overview();
        }
    }

    /**
     * What the options are, rendered by Symfony from their descriptions so this
     * and --help say the same thing. Not through $this->line(): the console style
     * Laravel wraps the output in collapses runs of spaces, which takes every
     * column with it.
     */
    private function overview(): void
    {
        $out = $this->getOutput()->getOutput();

        // The command's own definition rather than the merged one: --env, --ansi
        // and the rest of Laravel's globals belong under --help, not here.
        $options = $this->getNativeDefinition()->getOptions();
        $width = max(array_map(fn ($option) => strlen($option->getName()) + 3, $options)) + 2;

        foreach ($options as $option) {
            $out->writeln(
                '  <fg=yellow>'.str_pad('--'.$option->getName().($option->acceptValue() ? '=' : ''), $width).'</>'
                .str_replace("\n", "\n".str_repeat(' ', $width + 2), wordwrap($option->getDescription(), 86 - $width))
            );
        }

        $out->writeln('');
        $out->writeln('  <fg=gray>Why any of this: vendor/nickdekruijk/leap/docs/images.md</>');
        $out->writeln('');
    }
}
