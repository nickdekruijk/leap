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

        $this->warnAboutTheLink();

        if (($this->option('prune') || $this->option('warm')) && ($blind = $this->formatsThisDriverCannotWrite()) !== []) {
            $this->components->error(
                'The image driver cannot encode '.implode(' or ', $blind).', which leap.images asks every preset for.'
            );
            $this->line('  <fg=gray>Every copy already written that way then reads as a layout this package does not write:</>');
            $this->line('  <fg=gray>--prune would delete all of them and --warm would rewrite them at addresses nothing asks for.</>');
            $this->line('  <fg=gray>Almost always a command line running a different PHP than the site. The driver is</> '
                .'<fg=yellow>'.Media::imageDriver().'</><fg=gray>; check that this PHP has it:</> <fg=yellow>'.PHP_BINARY.'</>');
            $this->newLine();

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
     * The formats the config offers that this driver cannot encode a single one
     * of, so the paths those presets describe are not the paths on disk.
     *
     * Both destructive options read the file names back through the preset that
     * would have written them, and a preset asks the driver which format that
     * is: format() drops what cannot be encoded, and with nothing left it
     * answers "the source's own", so a copy ending in .avif no longer looks like
     * anything this package writes. prune() calls that an older layout and
     * deletes it; warm() writes the copy again at an address the markup does not
     * use. Neither is wrong about what it was told. What it was told is wrong.
     *
     * It happens for one reason in practice: a command line running a different
     * PHP than the site, without the extension the driver needs. The site keeps
     * serving AVIF perfectly well while artisan cannot see that AVIF exists,
     * which is exactly the sort of disagreement that ends in deleted files.
     *
     * Only the presets that offer several formats can say this. One format is a
     * plain string, and that is passed through whether the driver has it or not.
     *
     * @return array<int, string>
     */
    private function formatsThisDriverCannotWrite(): array
    {
        $blind = [];

        foreach (ImagePreset::all() as $preset) {
            if ($preset->formats() === [] || $preset->format() !== null) {
                continue;
            }

            $blind = array_merge($blind, array_keys($preset->formats()));
        }

        return array_values(array_unique($blind));
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
            $this->warnAboutTheLink();
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
     * Say it when the link the copies are served over is not there.
     *
     * Nothing breaks without it, which is the problem: every request then misses
     * on disk and is answered by PHP out of storage, for every size of every
     * image on the site, and the only trace is a server that got slower. The one
     * case that really bites is an upgrade from the release that wrote into
     * public/ directly, where the old cache is still sitting in the way as a
     * real directory and storage:link refuses to replace it.
     */
    private function warnAboutTheLink(): void
    {
        $problem = $this->linkProblem();

        if (! $problem) {
            return;
        }

        $this->components->warn($problem);
        $this->line('  <fg=gray>Without it every resized image is served by PHP instead of by the web server.</>');
        $this->line('  <fg=gray>Nothing is lost either way; it is only slower.</>');
        $this->newLine();
    }

    /**
     * What is wrong with that link, in a sentence, or null when there is nothing
     * to say. A disk with no directory behind it (s3) and a disk rooted inside
     * public/ both need no link at all.
     */
    private function linkProblem(): ?string
    {
        $config = config('filesystems.disks.'.config('leap.images.disk'));
        $root = $config['root'] ?? null;

        if (($config['driver'] ?? null) !== 'local' || ! $root || str_starts_with($root, public_path())) {
            return null;
        }

        $link = public_path(trim((string) config('leap.images.route'), '/'));

        if (is_link($link)) {
            // Compared as written as well as resolved: the directory it points
            // at only comes into being when the first copy is written, and
            // realpath() answers false for both sides of a comparison it cannot
            // resolve, which would call any two dangling links the same one.
            $target = (string) readlink($link);
            $points_at_it = rtrim($target, '/') === rtrim($root, '/')
                || (realpath($target) !== false && realpath($target) === realpath($root));

            return $points_at_it ? null : $link.' is a link to '.$target.', not to '.$root.'.';
        }

        if (is_dir($link)) {
            return $link.' is a real directory, so the link cannot be made. Delete it and run php artisan storage:link.';
        }

        return $link.' is missing. Run php artisan storage:link, and add it to the deploy.';
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
