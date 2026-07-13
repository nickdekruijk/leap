<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Tests\TestCase;

class TemplateInstallTest extends TestCase
{
    private string $temp;

    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCwd = getcwd();
        $this->temp = sys_get_temp_dir().'/leap-template-'.uniqid();

        // Minimal Laravel-app skeleton: the dirs copy() writes into (it does not
        // create parents) plus the files the patch steps expect to edit.
        foreach ([
            'app/Http/Controllers', 'app/Models', 'database/migrations',
            'database/seeders', 'config', 'public', 'tests', 'routes',
        ] as $dir) {
            mkdir($this->temp.'/'.$dir, 0777, true);
        }

        copy(dirname(__DIR__, 2).'/config/leap.php', $this->temp.'/config/leap.php');
        file_put_contents($this->temp.'/routes/web.php', "<?php\n\nRoute::get('/', function () {\n    return view('welcome');\n});\n");
        file_put_contents($this->temp.'/database/seeders/DatabaseSeeder.php', "<?php\n\nnamespace Database\\Seeders;\n\nuse Illuminate\\Database\\Seeder;\n\nclass DatabaseSeeder extends Seeder\n{\n    public function run(): void\n    {\n    }\n}\n");
        file_put_contents($this->temp.'/app/Models/User.php', "<?php\n\nnamespace App\\Models;\n\nclass User {}\n");
        file_put_contents($this->temp.'/.env', "APP_LOCALE=en\nAPP_FALLBACK_LOCALE=en\n");

        // One of the two compiled-asset rules is already here, so a re-run has to
        // add the missing one without duplicating the other
        file_put_contents($this->temp.'/.gitignore', "/vendor\n/public/css/builds\n");

        $this->app->setBasePath($this->temp);
        chdir($this->temp);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->deleteDir($this->temp);

        parent::tearDown();
    }

    private function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->deleteDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public function test_leap_template_installs_into_a_bare_app(): void
    {
        $this->artisan('leap:template')
            ->expectsConfirmation('Create app/Leap directory?', 'yes')
            ->expectsConfirmation('Create app/Traits directory?', 'yes')
            ->expectsConfirmation('Copy PageController?', 'yes')
            ->expectsConfirmation('Copy pages table migration?', 'yes')
            ->expectsConfirmation('Copy PageSeeder?', 'yes')
            ->expectsConfirmation('Copy Page model?', 'yes')
            ->expectsConfirmation('Copy Page model Leap module?', 'yes')
            ->expectsConfirmation('Copy HasSections trait?', 'yes')
            ->expectsConfirmation('Copy HasSlug trait?', 'yes')
            ->expectsConfirmation('Create app/Livewire directory?', 'yes')
            ->expectsConfirmation('Copy Search Livewire component?', 'yes')
            ->expectsConfirmation('Create public/css directory?', 'yes')
            ->expectsConfirmation('Copy TinyMCE editor stylesheet?', 'yes')
            ->expectsConfirmation('Copy ImageResize config (frontend resize templates)?', 'yes')
            ->expectsConfirmation('Create tests/Feature directory?', 'yes')
            ->expectsConfirmation('Copy PageRouting test?', 'yes')
            ->expectsConfirmation('Copy HasSlug test?', 'yes')
            ->expectsConfirmation('Copy Multilingual test?', 'yes')
            ->expectsConfirmation('Copy SCSS files?', 'yes')
            ->expectsConfirmation('Copy template views?', 'yes')
            ->expectsConfirmation('Copy JavaScript files?', 'yes')
            ->expectsConfirmation('Run "composer require" for the missing packages now?', 'no')
            ->expectsConfirmation('Delete default Laravel welcome route?', 'yes')
            ->expectsConfirmation('Add sitemap.xml route?', 'yes')
            ->expectsConfirmation('Add PageController route?', 'yes')
            ->expectsConfirmation('Register PageSeeder in DatabaseSeeder?', 'yes')
            ->expectsConfirmation('Enable multilingual content (Dutch + English)?', 'yes')
            ->expectsConfirmation('Run database migrations now?', 'no')
            ->expectsConfirmation('Seed the sample pages now?', 'no')
            ->assertExitCode(0);

        // Representative copies landed
        foreach ([
            'app/Http/Controllers/PageController.php',
            'app/Models/Page.php',
            'app/Leap/Page.php',
            'app/Livewire/Search.php',
            'config/imageresize.php',
            'public/css/tinymce.css',
            'resources/views/sections/default.blade.php',
            'resources/css/template.scss',
        ] as $file) {
            $this->assertFileExists($this->temp.'/'.$file, "Expected {$file} to be copied.");
        }

        // The compiled CSS/JS is build output, written on request by minify, so it
        // is kept out of version control rather than committed as a stale artifact
        $gitignore = file_get_contents($this->temp.'/.gitignore');
        $this->assertStringContainsString('/public/js/builds', $gitignore);
        $this->assertSame(1, substr_count($gitignore, '/public/css/builds'), 'The rule was already there and must not be added twice.');

        // Route + config patches applied
        $routes = file_get_contents($this->temp.'/routes/web.php');
        $this->assertStringContainsString('PageController::class', $routes);
        $this->assertStringNotContainsString("return view('welcome');", $routes);

        $leapConfig = file_get_contents($this->temp.'/config/leap.php');
        $this->assertStringContainsString("'content_css' => '/css/tinymce.css'", $leapConfig);
        $this->assertStringContainsString("'nl' => 'Nederlands'", $leapConfig);

        $seeder = file_get_contents($this->temp.'/database/seeders/DatabaseSeeder.php');
        $this->assertStringContainsString('PageSeeder::class', $seeder);
    }
}
