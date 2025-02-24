<?php

namespace NickDeKruijk\Leap\Commands;

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
    protected $signature = 'leap:template';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install a basic template replacing the default Laravel welcome template';

    /**
     * Copy or replace a file from the stubs/template folder after confirmation, asks to overwrite if it exists and sha1 hashes differ
     *
     * @param string $file          The file including path relative to the stubs/template folder
     * @param string $description   The description of the file to show in confirmation
     * @return void
     */
    public function copyOrReplace(string $file, string $description)
    {
        $exists = file_exists($file);

        // Skip if file exists and sha1 hashes match
        if ($exists && sha1_file(__DIR__ . '/../../stubs/template/' . $file) == sha1_file($file)) {
            return;
        }

        if (confirm($exists ? ucfirst("$description already exists, do you want to overwrite it?") : "Copy $description?", !$exists)) {
            copy(__DIR__ . '/../../stubs/template/' . $file, $file);
            $this->info('Copied ' . $file);
        } else {
            $this->info('Skipping ' . $file);
        }
    }

    public function copyDir(string $directory, string $description)
    {
        if (confirm("Copy $description?", false)) {
            $filesystem = new Filesystem;
            $filesystem->copyDirectory(__DIR__ . '/../../stubs/template/' . $directory, $directory);
            $this->info('Copied ' . $directory);
        }
    }

    /**
     * Delete a file if it exists and sha1 hashes match the list
     *
     * @param string $file      The file including path relative to base path
     * @param array $sha1_list  List of sha1 hashes that match the file, e.g. ['e8928af0db9d15ccd7d75c5fc31ae3c63f7ffe1c', 'd2e5cf4f96d815b68a4f2a012b54a0d25daa4952']
     * @return void
     */
    public function deleteFile(string $file, array $sha1_list = [])
    {
        if (file_exists($file)) {
            $sha1 = sha1_file($file);
            if (!in_array($sha1, $sha1_list)) {
                $this->info("Skipping $file because SHA-1 $sha1 is unknown");
            } elseif (confirm("Delete $file?", false)) {
                unlink($file);
                $this->info('Deleted ' . $file);
            }
        }
    }

    /**
     * Update the contents of a file with the logic of a given callback
     *
     * @param string $file          The file to update
     * @param callable $callback    The callback function to run
     * @return void
     */
    public static function updateFile(string $file, callable $callback)
    {
        $originalFileContents = file_get_contents($file);
        $newFileContents = $callback($originalFileContents);
        file_put_contents($file, $newFileContents);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Don't run in production
        if (!$this->confirmToProceed()) {
            return 1;
        }

        // Ask to publish config if it doesn't exist
        if (!file_exists('config/leap.php') && confirm('Publish leap config file?', false)) {
            $this->call('vendor:publish', ['--provider' => 'NickDeKruijk\Leap\ServiceProvider', '--tag' => 'config']);
        }

        // Ask to create app/Leap directory if it doesn't exist
        if (!file_exists('app/Leap')) {
            if (confirm('Create app/Leap directory?')) {
                mkdir('app/Leap');
                $this->info('Created app/Leap');
            }
        }

        // Ask to copy or replace files
        $this->copyOrReplace('app/Http/Controllers/PageController.php', 'PageController');
        $this->copyOrReplace('database/migrations/2025_01_03_094203_create_pages_table.php', 'pages table migration');
        $this->copyOrReplace('app/Models/Page.php', 'Page model');
        $this->copyOrReplace('app/Leap/Page.php', 'Page model Leap module');

        // Ask to delete default Laravel welcome view, js/app.js, app/bootstrap.js and css/app.css
        $this->deleteFile('resources/views/welcome.blade.php', ['e8928af0db9d15ccd7d75c5fc31ae3c63f7ffe1c']);
        $this->deleteFile('resources/js/app.js', ['d2e5cf4f96d815b68a4f2a012b54a0d25daa4952']);
        $this->deleteFile('resources/js/bootstrap.js', ['737674b888fd746d93f53ca3b748936d85f14c20']);
        $this->deleteFile('resources/css/app.css', ['d88dc6b14989074f8d407e9b7f7f855d5816203e']);

        // Ask to copy scss files and views
        $this->copyDir('resources/css', 'SCSS files');
        $this->copyDir('resources/views', 'template views');

        // Ask to delete Laravel default welcome route
        $route = "Route::get('/', function () {\n    return view('welcome');\n});\n";
        if (str_contains(file_get_contents('routes/web.php'), $route) && confirm('Delete default Laravel welcome route?', false)) {
            self::updateFile(base_path('routes/web.php'), function ($file) use ($route) {
                return str_replace($route, "", $file);
            });
        }

        // Ask to add PageController route
        $route = "Route::get('{any}', [App\Http\Controllers\PageController::class, 'route'])->where('any', '(.*)');\n";
        if (!str_contains(file_get_contents('routes/web.php'), $route) && confirm('Add PageController route?', true)) {
            self::updateFile(base_path('routes/web.php'), function ($file) use ($route) {
                return $file .= $route;
            });
        }
    }
}
