<?php

namespace NickDeKruijk\Leap\Commands;

use Database\Seeders\PageSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\confirm;

class TemplateCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leap:template {--diff : Show how this project'."'".'s template files differ from the current stubs without changing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install a basic template replacing the default Laravel welcome template';

    /**
     * Copy or replace a file from the stubs/template folder after confirmation, asks to overwrite if it exists and sha1 hashes differ
     *
     * @param  string  $file  The file including path relative to the stubs/template folder
     * @param  string  $description  The description of the file to show in confirmation
     * @return void
     */
    public function copyOrReplace(string $file, string $description)
    {
        $exists = file_exists($file);

        // Skip if file exists and sha1 hashes match
        if ($exists && sha1_file(__DIR__.'/../../stubs/template/'.$file) == sha1_file($file)) {
            return;
        }

        if (confirm($exists ? ucfirst("$description already exists, do you want to overwrite it?") : "Copy $description?", ! $exists)) {
            copy(__DIR__.'/../../stubs/template/'.$file, $file);
            $this->info('Copied '.$file);
        } else {
            $this->info('Skipping '.$file);
        }
    }

    public function copyDir(string $directory, string $description)
    {
        if (confirm("Copy $description?", false)) {
            $filesystem = new Filesystem;
            $filesystem->copyDirectory(__DIR__.'/../../stubs/template/'.$directory, $directory);
            $this->info('Copied '.$directory);
        }
    }

    /**
     * Offer to delete a leftover Laravel scaffolding file that the template
     * replaces. Always prompts (defaulting to Yes) rather than matching against
     * hardcoded sha1 hashes, so it keeps working across Laravel releases without
     * maintenance. The file is git-tracked in a fresh project, so an accidental
     * delete is recoverable.
     *
     * @param  string  $file  The file including path relative to base path
     * @return void
     */
    public function deleteFile(string $file)
    {
        if (file_exists($file) && confirm("Delete $file? (Laravel default, replaced by the template)", true)) {
            unlink($file);
            $this->info('Deleted '.$file);
        }
    }

    /**
     * Update the contents of a file with the logic of a given callback
     *
     * @param  string  $file  The file to update
     * @param  callable  $callback  The callback function to run
     * @return void
     */
    public static function updateFile(string $file, callable $callback)
    {
        $originalFileContents = file_get_contents($file);
        $newFileContents = $callback($originalFileContents);
        file_put_contents($file, $newFileContents);
    }

    /**
     * Ask to create a directory if it doesn't exist
     *
     * @return void
     */
    public function createDirectory(string $directory)
    {
        if (! file_exists($directory) && confirm("Create $directory directory?")) {
            mkdir($directory);
            $this->info("Created $directory");
        }
    }

    /**
     * The template files copied by this command, as paths relative to the project
     * root (and to the stubs/template folder). Individual files plus everything in
     * the copied directories. Used by both the installer and --diff.
     *
     * @return array<int, string>
     */
    protected function templateFiles(): array
    {
        $files = [
            'app/Http/Controllers/PageController.php',
            'database/migrations/2025_01_03_094203_create_pages_table.php',
            'database/seeders/PageSeeder.php',
            'app/Models/Page.php',
            'app/Leap/Page.php',
            'app/Livewire/Search.php',
            'app/Traits/HasSections.php',
            'app/Traits/HasSlug.php',
            'config/imageresize.php',
            'public/css/tinymce.css',
            'tests/Feature/PageRoutingTest.php',
            'tests/Feature/HasSlugTest.php',
            'tests/Feature/MultilingualTest.php',
        ];

        $stubBase = __DIR__.'/../../stubs/template';
        $filesystem = new Filesystem;
        foreach (['resources/css', 'resources/views', 'resources/js'] as $directory) {
            if (! is_dir($stubBase.'/'.$directory)) {
                continue;
            }
            foreach ($filesystem->allFiles($stubBase.'/'.$directory) as $file) {
                $files[] = $directory.'/'.$file->getRelativePathname();
            }
        }

        return $files;
    }

    /**
     * Report how the project's template files differ from the current stubs,
     * without changing anything. Shows a unified diff per changed file when the
     * `diff` binary is available, and lists files that are new or unchanged.
     */
    public function showDiff(): int
    {
        $stubBase = realpath(__DIR__.'/../../stubs/template');
        $changed = $new = $unchanged = [];

        foreach ($this->templateFiles() as $relative) {
            $stub = $stubBase.'/'.$relative;
            $project = base_path($relative);

            if (! file_exists($project)) {
                $new[] = $relative;
            } elseif (sha1_file($stub) === sha1_file($project)) {
                $unchanged[] = $relative;
            } else {
                $changed[] = $relative;
            }
        }

        foreach ($changed as $relative) {
            $this->newLine();
            $this->line('<fg=yellow>changed:</> '.$relative);
            $output = [];
            exec('diff -u '.escapeshellarg(base_path($relative)).' '.escapeshellarg($stubBase.'/'.$relative).' 2>/dev/null', $output);
            foreach ($output as $line) {
                if (str_starts_with($line, '+')) {
                    $this->line('<fg=green>'.$line.'</>');
                } elseif (str_starts_with($line, '-')) {
                    $this->line('<fg=red>'.$line.'</>');
                } else {
                    $this->line($line);
                }
            }
        }

        foreach ($new as $relative) {
            $this->line('<fg=blue>new:</>     '.$relative.' (not in this project yet)');
        }

        $this->newLine();
        $this->info(count($changed).' changed, '.count($new).' new, '.count($unchanged).' unchanged.');

        return 0;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Report differences without touching anything
        if ($this->option('diff')) {
            return $this->showDiff();
        }

        // Don't run in production
        if (! $this->confirmToProceed()) {
            return 1;
        }

        // Ask to publish config if it doesn't exist
        if (! file_exists('config/leap.php') && confirm('Publish leap config file?', false)) {
            $this->call('vendor:publish', ['--provider' => 'NickDeKruijk\Leap\ServiceProvider', '--tag' => 'config']);
        }

        // Ask to create app/Leap directory if it doesn't exist
        $this->createDirectory('app/Leap');
        $this->createDirectory('app/Traits');

        // Ask to copy or replace files
        $this->copyOrReplace('app/Http/Controllers/PageController.php', 'PageController');
        $this->copyOrReplace('database/migrations/2025_01_03_094203_create_pages_table.php', 'pages table migration');
        $this->copyOrReplace('database/seeders/PageSeeder.php', 'PageSeeder');
        $this->copyOrReplace('app/Models/Page.php', 'Page model');
        $this->copyOrReplace('app/Leap/Page.php', 'Page model Leap module');
        $this->copyOrReplace('app/Traits/HasSections.php', 'HasSections trait');
        $this->copyOrReplace('app/Traits/HasSlug.php', 'HasSlug trait');

        // Live search (a plain Livewire class component so it works on Livewire 3 and 4)
        $this->createDirectory('app/Livewire');
        $this->copyOrReplace('app/Livewire/Search.php', 'Search Livewire component');

        // TinyMCE editor content styles, so rich-text matches the frontend in the editor
        $this->createDirectory('public/css');
        $this->copyOrReplace('public/css/tinymce.css', 'TinyMCE editor stylesheet');
        $this->enableTinymceContentCss();

        // ImageResize width presets used by the template's srcset/backgrounds
        // (overrides the vendor-published default, which lacks these templates)
        $this->copyOrReplace('config/imageresize.php', 'ImageResize config (frontend resize templates)');

        // Starter feature tests for the copied template code (run under the host's test suite)
        $this->createDirectory('tests/Feature');
        $this->copyOrReplace('tests/Feature/PageRoutingTest.php', 'PageRouting test');
        $this->copyOrReplace('tests/Feature/HasSlugTest.php', 'HasSlug test');
        $this->copyOrReplace('tests/Feature/MultilingualTest.php', 'Multilingual test');

        // Ask to delete default Laravel welcome view, js/app.js, app/bootstrap.js and css/app.css
        $this->deleteFile('resources/views/welcome.blade.php');
        $this->deleteFile('resources/js/app.js');
        $this->deleteFile('resources/js/bootstrap.js');
        $this->deleteFile('resources/css/app.css');
        // Laravel's stock ExampleTest asserts GET / returns 200 for the static welcome
        // page; the homepage is now DB-driven (PageController), so it would fail. The
        // template's PageRoutingTest covers routing properly instead.
        $this->deleteFile('tests/Feature/ExampleTest.php');

        // Ask to copy scss files, views and javascript
        $this->copyDir('resources/css', 'SCSS files');
        $this->copyDir('resources/views', 'template views');
        $this->copyDir('resources/js', 'JavaScript files');

        // Suggest installing the frontend packages the template relies on
        $this->suggestFrontendPackages();

        // Ask to delete Laravel default welcome route
        $route = "Route::get('/', function () {\n    return view('welcome');\n});\n";
        if (str_contains(file_get_contents('routes/web.php'), $route) && confirm('Delete default Laravel welcome route?', false)) {
            self::updateFile(base_path('routes/web.php'), function ($file) use ($route) {
                return str_replace($route, '', $file);
            });
        }

        // Ask to add the sitemap route (before the catch-all so it isn't swallowed)
        $sitemap = "Route::get('sitemap.xml', [App\Http\Controllers\PageController::class, 'sitemap'])->name('sitemap');\n";
        if (! str_contains(file_get_contents('routes/web.php'), $sitemap) && confirm('Add sitemap.xml route?', true)) {
            self::updateFile(base_path('routes/web.php'), function ($file) use ($sitemap) {
                return $file .= $sitemap;
            });
        }

        // Ask to add PageController route
        $route = "Route::get('{any}', [App\Http\Controllers\PageController::class, 'route'])->where('any', '(.*)');\n";
        if (! str_contains(file_get_contents('routes/web.php'), $route) && confirm('Add PageController route?', true)) {
            self::updateFile(base_path('routes/web.php'), function ($file) use ($route) {
                return $file .= $route;
            });
        }

        // Register the PageSeeder so `php artisan db:seed` seeds sample pages
        $this->registerPageSeeder();

        // Offer to enable multilingual (nl/en) content (must run before seeding)
        $this->configureMultilingual();

        // Warn about the traits/contract the User model needs for Leap
        $this->checkUserModel();

        // Offer to run migrations and seed the sample pages
        $this->runMigrationsAndSeed();

        // Closing summary with the remaining manual steps
        $this->printNextSteps();
    }

    /**
     * Register the copied PageSeeder in DatabaseSeeder::run() so `php artisan
     * db:seed` (and `migrate --seed`) seed the sample pages. No-op if the call
     * is already present or DatabaseSeeder can't be located.
     */
    protected function registerPageSeeder(): void
    {
        $path = base_path('database/seeders/DatabaseSeeder.php');
        if (! file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);
        if (str_contains($contents, 'PageSeeder')) {
            return;
        }

        // Insert the call as the first statement inside run() { ... }
        $patched = preg_replace(
            '/(function run\(\)(?:\s*:\s*void)?\s*\{)/',
            "$1\n        \$this->call(\\Database\\Seeders\\PageSeeder::class);",
            $contents,
            1
        );

        if ($patched && $patched !== $contents && confirm('Register PageSeeder in DatabaseSeeder?', true)) {
            file_put_contents($path, $patched);
            $this->info('Registered PageSeeder in DatabaseSeeder');
        }
    }

    /**
     * Point leap.tinymce.options.content_css at the copied /css/tinymce.css so the
     * rich-text editor loads the frontend button/prose styles. No-op when the leap
     * config isn't published, or the key is already customised.
     */
    protected function enableTinymceContentCss(): void
    {
        $config = base_path('config/leap.php');
        if (! file_exists($config)) {
            return;
        }

        $contents = file_get_contents($config);
        $commented = "// 'content_css' => '/css/tinymce.css',";
        if (str_contains($contents, $commented)) {
            $contents = str_replace($commented, "'content_css' => '/css/tinymce.css',", $contents);
            file_put_contents($config, $contents);
            $this->info('Enabled leap.tinymce.content_css → /css/tinymce.css');
        }
    }

    /**
     * Offer to enable multilingual (nl/en) content by setting leap.locales and
     * the app locale. Requires the leap config to have been published.
     */
    protected function configureMultilingual(): void
    {
        if (! confirm('Enable multilingual content (Dutch + English)?', false)) {
            return;
        }

        $config = base_path('config/leap.php');
        if (! file_exists($config)) {
            $this->warn('config/leap.php not found — publish it first, then set leap.locales manually.');

            return;
        }

        $contents = file_get_contents($config);
        if (preg_match("/'locales'\s*=>\s*null/", $contents)) {
            $contents = preg_replace(
                "/'locales'\s*=>\s*null/",
                "'locales' => ['nl' => 'Nederlands', 'en' => 'English']",
                $contents,
                1
            );
            file_put_contents($config, $contents);
            $this->info('Set leap.locales to nl + en');
        } else {
            $this->warn('leap.locales is already customised — left untouched.');
        }

        // Point the app locale at the default (first) locale
        $env = base_path('.env');
        if (file_exists($env)) {
            $envContents = file_get_contents($env);
            $envContents = preg_replace('/^APP_LOCALE=.*/m', 'APP_LOCALE=nl', $envContents);
            $envContents = preg_replace('/^APP_FALLBACK_LOCALE=.*/m', 'APP_FALLBACK_LOCALE=nl', $envContents);
            file_put_contents($env, $envContents);
            $this->info('Set APP_LOCALE / APP_FALLBACK_LOCALE to nl');
        }
    }

    /**
     * Check the User model for the traits and contract Leap needs and print a
     * copy-paste snippet for anything missing. Does not modify the model.
     */
    protected function checkUserModel(): void
    {
        $path = base_path('app/Models/User.php');
        if (! file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);
        $missing = [];
        foreach ([
            'HasRoles' => 'use NickDeKruijk\Leap\Traits\HasRoles;',
            'TwoFactorAuthenticatable' => 'use Laravel\Fortify\TwoFactorAuthenticatable;',
            'PasskeyAuthenticatable' => 'use Laravel\Passkeys\PasskeyAuthenticatable;',
            'PasskeyUser' => 'use Laravel\Passkeys\Contracts\PasskeyUser; (and "implements PasskeyUser")',
        ] as $needle => $hint) {
            if (! str_contains($contents, $needle)) {
                $missing[] = $hint;
            }
        }

        if ($missing) {
            $this->newLine();
            $this->warn('Your User model (app/Models/User.php) is missing some Leap requirements. Add:');
            foreach ($missing as $line) {
                $this->line('  '.$line);
            }
            $this->line('  ...and add the traits to the class\'s "use ...;" statement.');
        }
    }

    /**
     * Offer to run the migrations and seed the sample pages.
     */
    protected function runMigrationsAndSeed(): void
    {
        if (confirm('Run database migrations now?', false)) {
            $this->call('migrate');
        }

        if (confirm('Seed the sample pages now?', false)) {
            $this->call('db:seed', ['--class' => PageSeeder::class]);
        }
    }

    /**
     * Print the remaining manual steps once the template is installed.
     */
    protected function printNextSteps(): void
    {
        $this->newLine();
        $this->info('Template installed. Next steps:');
        $this->line('  • No asset build needed — SCSS/JS compile on request (no npm/Vite).');
        $this->line('  • Serve with a public/-rooted server (Herd/nginx), not `php artisan serve`.');
        $this->line('  • Run `php artisan storage:link` so resized images resolve (originals live in /storage).');
        $this->line('  • Create an admin user: php artisan leap:user you@example.com');
        $this->line('  • Visit /admin to manage pages, and / for the site.');
    }

    /**
     * Suggest installing the composer packages the frontend template relies on.
     * They are kept out of leap's own "require" so existing projects are never
     * forced to pull them; the template opts in here.
     */
    public function suggestFrontendPackages(): void
    {
        $packages = [
            'nickdekruijk/settings' => 'admin-editable settings + footer',
            'nickdekruijk/imageresize' => 'responsive asset_resized() images',
            'nickdekruijk/vanilla-slider' => 'carousel',
            'nickdekruijk/horizontal-scroller' => 'horizontal-scroll sections',
        ];

        $missing = array_filter(array_keys($packages), fn (string $package): bool => ! is_dir(base_path('vendor/'.$package)));

        if (empty($missing)) {
            return;
        }

        $this->info('The frontend template uses these packages:');
        foreach ($packages as $package => $why) {
            $this->line('  - '.$package.' ('.$why.')');
        }

        if (confirm('Run "composer require" for the missing packages now?', true)) {
            $command = 'composer require '.implode(' ', $missing);
            $this->info('Running: '.$command);
            passthru($command, $status);
            if ($status === 0) {
                $this->info('Publishing settings and imageresize config...');
                $this->call('vendor:publish', ['--provider' => 'NickDeKruijk\Settings\ServiceProvider']);
                $this->call('vendor:publish', ['--provider' => 'NickDeKruijk\ImageResize\ServiceProvider']);
            }
        } else {
            $this->line('Install later with: composer require '.implode(' ', $missing));
        }
    }
}
