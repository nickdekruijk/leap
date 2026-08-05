<?php

namespace NickDeKruijk\Leap\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use NickDeKruijk\Leap\Classes\ImagePreset;
use NickDeKruijk\Leap\Classes\ImageResizer;
use NickDeKruijk\Leap\Classes\ImageUrl;
use NickDeKruijk\Leap\Models\Media;

/**
 * Housekeeping for the resized copies under leap.images.
 *
 * --warm exists because a deploy to a fresh release directory starts with an
 * empty cache: without it the first visitors after every deploy pay for every
 * image on the site. --prune exists because nothing else ever deletes: a URL
 * carries the file's hash, so replacing an image leaves its old copies behind
 * as orphans rather than overwriting them.
 */
class ImageCommand extends Command
{
    /**
     * The descriptions are the long ones on purpose: they are what both
     * "leap:images" on its own and "leap:images --help" print, and what each of
     * these is for is less obvious than what it does.
     */
    protected $signature = 'leap:images
        {--sync : Re-read every image from disk. Run this after writing files outside leap (an rsync, a deploy script, a database import): nothing else re-reads the row that every URL is built from, and the web server answers those URLs off disk without asking PHP anything that would let it notice}
        {--warm : Generate every copy that is missing, so the first visitor after a deploy does not pay for it. A fresh release directory starts empty}
        {--prune : Delete copies nothing points at any more. A copy is addressed by the hash of what it was made from, so nothing is ever overwritten: a replaced or deleted image leaves its old copies behind. Leap takes those along itself, so this is for what happened out of its sight}
        {--clear : Delete every generated copy and start over. They come back one at a time as they are asked for, or all at once with --warm}
        {--preset= : Limit --warm to a single preset}
        {--dry-run : Report what any of the above would do, without writing or deleting anything}';

    protected $description = 'Generate, prune or clear the resized image copies';

    public function handle(): int
    {
        // Nothing to do is not a mistake to be told off for: it is someone
        // wondering what this command is. Answer that, and say where things
        // stand while we are at it.
        if (! $this->option('sync') && ! $this->option('warm') && ! $this->option('prune') && ! $this->option('clear')) {
            $this->overview();

            return self::SUCCESS;
        }

        if (! config('leap.images.enabled')) {
            $this->components->error('leap.images.enabled is false, there is nothing to do.');

            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $this->sync();
        }

        if ($this->option('clear')) {
            $this->clear();
        }

        if ($this->option('prune')) {
            $this->prune();
        }

        if ($this->option('warm')) {
            $this->warm();
        }

        return self::SUCCESS;
    }

    /**
     * What this command is for, and where the site stands: how many images
     * there are, how many copies have been made of them, and what each option
     * would do about it.
     */
    private function overview(): void
    {
        $this->newLine();
        $this->line('  <fg=gray>Resized copies of the images on the filemanager disk.</>');
        $this->newLine();

        if (! config('leap.images.enabled')) {
            $this->components->warn('Off: set leap.images.enabled to true in config/leap.php.');
        } else {
            $presets = array_map(fn (ImagePreset $preset) => $preset->name, ImagePreset::all());
            $copies = ImageResizer::disk()->allFiles();

            $this->components->twoColumnDetail('<fg=green>route</>', '/'.trim((string) config('leap.images.route'), '/').'  <fg=gray>served off disk by the web server</>');
            $this->components->twoColumnDetail('<fg=green>presets</>', implode(', ', $presets));
            $this->components->twoColumnDetail('<fg=green>images</>', $this->images()->count().' on the '.(config('leap.images.source_disk') ?: config('leap.filemanager.disk')).' disk');
            $this->components->twoColumnDetail('<fg=green>copies</>', count($copies).' files, '.$this->readableSize($copies));
            $this->newLine();
        }

        // Rendered by Symfony from the option descriptions, so this and --help
        // say the same thing and stay aligned. Not through $this->line(): the
        // console style Laravel wraps the output in collapses runs of spaces,
        // which takes every column with it.
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

    /**
     * @param  array<string>  $files
     */
    private function readableSize(array $files): string
    {
        $disk = ImageResizer::disk();
        $bytes = array_sum(array_map(fn (string $file) => $disk->size($file), $files));

        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }

            $bytes /= 1024;
        }

        return '';
    }

    /**
     * Re-read every image, for the files leap did not write itself.
     *
     * A URL is built from the hash on the Media row, and nothing re-reads that
     * row on its own. Replace a file through the file manager and leap keeps up;
     * replace it with an rsync, a deploy script or a database import and the
     * pages go on pointing at the picture that used to be there, without ever
     * asking PHP a question that would give it the chance to notice.
     *
     * Run this after writing to the disk from outside. It is the deliberate
     * counterpart of the automatic correction a cold URL already gets.
     */
    private function sync(): void
    {
        $changed = 0;

        foreach ($this->images() as $media) {
            if ($this->option('dry-run')) {
                $storage = ImageResizer::sourceDisk();

                if ($storage->exists($media->file_name) && hash('sha256', (string) $storage->get($media->file_name)) !== $media->sha256) {
                    $this->line('  would re-read '.$media->file_name);
                    $changed++;
                }

                continue;
            }

            if ($media->syncFromDisk('Re-read from disk')) {
                $this->line('  re-read '.$media->file_name);
                $changed++;
            }
        }

        $this->components->info($changed.($this->option('dry-run') ? ' would be re-read.' : ' re-read.'));
    }

    /**
     * Generate every missing copy. Cheap to re-run: anything already on disk is
     * skipped, and the file is only read when something has to be made from it.
     */
    private function warm(): void
    {
        $presets = $this->presets();

        if (! $presets) {
            $this->components->error('No such preset: '.$this->option('preset'));

            return;
        }

        $generated = 0;
        $skipped = 0;

        foreach ($this->images() as $media) {
            // Measured here rather than left to the first visitor, and it
            // corrects rows whose cached dimensions predate leap reading the
            // EXIF orientation.
            $media->dimensions();

            $result = ImageResizer::warm($media, $presets, (bool) $this->option('dry-run'));

            foreach ($result['generated'] as $target) {
                $this->line('  '.($this->option('dry-run') ? 'would generate ' : 'generated ').$target);
            }

            $generated += count($result['generated']);
            $skipped += count($result['skipped']);
        }

        $this->components->info($generated.($this->option('dry-run') ? ' would be generated, ' : ' generated, ').$skipped.' already there.');
    }

    /**
     * Delete copies nothing points at any more.
     *
     * One question per copy: does the original it names still exist, and does it
     * still hold the contents this copy was made from. That covers a preset
     * taken out of the config, a file that was replaced, and a file that was
     * deleted or renamed, without needing to tell those cases apart.
     */
    private function prune(): void
    {
        $disk = ImageResizer::disk();
        // Cast: PHP turns a numeric array key into an int, and a directory name
        // read back off disk is always a string.
        $presets = array_map('strval', array_keys(ImagePreset::all()));
        $hashes = $this->currentHashes();
        $deleted = 0;

        foreach ($disk->directories() as $directory) {
            if (! in_array(basename($directory), $presets, true)) {
                $deleted += $this->delete($directory);

                continue;
            }

            $preset = ImagePreset::find(basename($directory));

            foreach ($disk->allFiles($directory) as $copy) {
                if ($this->isOrphan(substr($copy, strlen($directory) + 1), $preset, $hashes)) {
                    $deleted += $this->delete($copy, file: true);
                }
            }
        }

        $this->emptyDirectories();

        $this->components->info($deleted.($this->option('dry-run') ? ' would be deleted.' : ' deleted.'));
    }

    /**
     * Whether nothing points at this copy any more.
     *
     * @param  string  $path  Below the preset directory
     * @param  array<string>  $hashes  Every hash a file on the source disk currently has
     */
    private function isOrphan(string $path, ImagePreset $preset, array $hashes): bool
    {
        $parsed = ImageResizer::parseTargetPath($path, $preset);

        // Not a path this package writes: an older layout, or something that
        // wandered in. Either way nothing here will ever serve it.
        if (! $parsed) {
            return true;
        }

        if (! ImageResizer::sourceDisk()->exists($parsed['path'])) {
            return true;
        }

        // The file is there under that name, but the copy was made from what it
        // held before. Media rows answer this without touching the disk; a file
        // with no row (a video poster) is hashed, once per version of it.
        return ! in_array($parsed['hash'], $hashes, true)
            && ImageUrl::hash($parsed['path']) !== $parsed['hash'];
    }

    /**
     * Remove the directories the deleting left empty, deepest first so a
     * directory that only held empty ones goes too.
     */
    private function emptyDirectories(): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        $disk = ImageResizer::disk();
        $directories = $disk->allDirectories();

        usort($directories, fn (string $a, string $b) => substr_count($b, '/') <=> substr_count($a, '/'));

        foreach ($directories as $directory) {
            if (! $disk->allFiles($directory)) {
                $disk->deleteDirectory($directory);
            }
        }
    }

    private function clear(): void
    {
        $disk = ImageResizer::disk();
        $deleted = 0;

        foreach ($disk->directories() as $directory) {
            $deleted += $this->delete($directory);
        }

        $this->components->info($deleted.($this->option('dry-run') ? ' would be cleared.' : ' cleared.'));
    }

    private function delete(string $path, bool $file = false): int
    {
        $this->line('  '.($this->option('dry-run') ? 'would delete ' : 'deleting ').$path);

        if (! $this->option('dry-run')) {
            $file
                ? ImageResizer::disk()->delete($path)
                : ImageResizer::disk()->deleteDirectory($path);
        }

        return 1;
    }

    /**
     * The hash prefix of every file that currently exists, as it appears in a
     * URL. Anything else on the images disk is an orphan.
     *
     * @return array<string>
     */
    private function currentHashes(): array
    {
        $length = (int) config('leap.images.hash_length', 8);

        return $this->images()
            ->filter(fn (Media $media) => $media->sha256)
            ->map(fn (Media $media) => substr($media->sha256, 0, $length))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Media>
     */
    private function images(): Collection
    {
        return Media::query()
            ->where('disk', config('leap.images.source_disk') ?: config('leap.filemanager.disk'))
            ->whereIn('mime_type', Media::TYPES['bitmap']['mimes'])
            ->get();
    }

    /**
     * @return array<string, ImagePreset>
     */
    private function presets(): array
    {
        $only = $this->option('preset');

        if (! $only) {
            return ImagePreset::all();
        }

        $preset = ImagePreset::find($only);

        return $preset ? [$only => $preset] : [];
    }
}
