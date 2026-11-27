<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Console\Events\CommandFinished;
use NickDeKruijk\Leap\Controllers\AssetController;
use NickDeKruijk\Leap\ServiceProvider;
use NickDeKruijk\Leap\Tests\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * The scripts the panel loads from a CDN, and which version of them.
 *
 * leap.tinymce.cdn used to name a major, and a published config kept naming it
 * long after that major had stopped getting security fixes: TinyMCE 7 sat in the
 * panel for a year that way. Null now means "the one leap was tested with", and
 * the old default is read as null, so a release can move every site along.
 */
class CdnScriptsTest extends TestCase
{
    private ?string $configPath = null;

    protected function tearDown(): void
    {
        if ($this->configPath !== null) {
            @unlink($this->configPath.'/leap.php');
            @rmdir($this->configPath);
        }

        parent::tearDown();
    }

    /**
     * Stand in for a project's own config/leap.php, the file the notice reads.
     */
    private function publishConfigFile(string $php): void
    {
        $this->configPath = sys_get_temp_dir().'/leap-config-'.uniqid();
        mkdir($this->configPath);
        file_put_contents($this->configPath.'/leap.php', $php);
        $this->app->useConfigPath($this->configPath);
    }

    private function discoverOutput(string $command = 'package:discover'): string
    {
        $output = new BufferedOutput;
        event(new CommandFinished($command, new ArrayInput([]), $output, 0));

        return $output->fetch();
    }

    public function test_the_shipped_config_leaves_the_choice_to_leap(): void
    {
        $shipped = require __DIR__.'/../../config/leap.php';

        $this->assertNull($shipped['tinymce']['cdn']);
        $this->assertNull($shipped['tinymce']['dompurify_cdn']);
        $this->assertSame(AssetController::TINYMCE_CDN, AssetController::tinymceCdn());
        $this->assertSame(AssetController::DOMPURIFY_CDN, AssetController::dompurifyCdn());
    }

    /**
     * A config published before 1.23 holds this exact string, and not by choice:
     * it is the default that was published along with everything else.
     */
    public function test_the_default_of_an_older_release_follows_leap(): void
    {
        config(['leap.tinymce.cdn' => 'https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js']);

        $this->assertSame(AssetController::TINYMCE_CDN, AssetController::tinymceCdn());
    }

    public function test_that_holds_through_the_merge_of_a_published_config(): void
    {
        config(['leap' => ['tinymce' => ['cdn' => AssetController::TINYMCE_CDN_RETIRED, 'lazy' => true]]]);

        (new ReflectionMethod(ServiceProvider::class, 'mergeLeapConfigFrom'))
            ->invoke(new ServiceProvider($this->app), __DIR__.'/../../config/leap.php', 'leap');

        $this->assertSame(AssetController::TINYMCE_CDN, AssetController::tinymceCdn());
        $this->assertSame(AssetController::DOMPURIFY_CDN, AssetController::dompurifyCdn());
        // What the project did decide stays decided.
        $this->assertTrue(config('leap.tinymce.lazy'));
        $this->assertSame('gpl', config('leap.tinymce.options.license_key'));
    }

    public function test_a_url_the_project_chose_wins(): void
    {
        foreach (['https://cdn.jsdelivr.net/npm/tinymce@7.9.3/tinymce.min.js', '/js/tinymce/tinymce.min.js'] as $own) {
            config(['leap.tinymce.cdn' => $own]);

            $this->assertSame($own, AssetController::tinymceCdn());
        }

        config(['leap.tinymce.dompurify_cdn' => '/js/purify.min.js']);

        $this->assertSame('/js/purify.min.js', AssetController::dompurifyCdn());
    }

    public function test_an_empty_value_is_not_a_script_to_load(): void
    {
        config(['leap.tinymce.cdn' => '', 'leap.tinymce.dompurify_cdn' => '']);

        $this->assertSame(AssetController::TINYMCE_CDN, AssetController::tinymceCdn());
        $this->assertSame(AssetController::DOMPURIFY_CDN, AssetController::dompurifyCdn());
    }

    /**
     * A major floats, so a fix within it arrives on its own. "latest" floats over
     * majors too, and a new major is exactly what has to be tested first.
     */
    public function test_no_cdn_script_follows_latest(): void
    {
        $offenders = [];
        $seen = 0;

        foreach (['config', 'src', 'resources/views'] as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../../'.$directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                preg_match_all('~https://cdn\.jsdelivr\.net/npm/[^\'"\s]+~', (string) file_get_contents($file->getPathname()), $urls);
                foreach ($urls[0] as $url) {
                    $seen++;
                    if (! preg_match('~@\d~', $url)) {
                        $offenders[] = basename($file->getPathname()).': '.$url;
                    }
                }
            }
        }

        // A scan that finds no URL at all would pass without checking anything.
        $this->assertGreaterThanOrEqual(6, $seen);
        $this->assertSame([], $offenders, 'Name a major (package@8), not @latest or no version at all.');
    }

    public function test_composer_says_so_while_the_old_default_is_in_the_config_file(): void
    {
        $this->publishConfigFile("<?php return ['tinymce' => ['cdn' => 'https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js']];");

        $output = $this->discoverOutput();

        $this->assertStringContainsString('config/leap.php still sets tinymce.cdn to tinymce@7', $output);
        $this->assertStringContainsString('Set the value to null', $output);
    }

    public function test_composer_stays_quiet_otherwise(): void
    {
        // No published config at all.
        $this->assertSame('', $this->discoverOutput());

        foreach ([
            "<?php return ['tinymce' => ['cdn' => null]];",
            "<?php return ['tinymce' => ['cdn' => 'https://cdn.jsdelivr.net/npm/tinymce@7.9.3/tinymce.min.js']];",
            "<?php return ['tinymce' => ['lazy' => true]];",
            // Commented out is not set.
            "<?php return ['tinymce' => [/* 'cdn' => 'https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js' */]];",
            // A config file that fails to load is somebody else's error to report.
            "<?php throw new RuntimeException('broken');",
        ] as $php) {
            $this->publishConfigFile($php);

            $this->assertSame('', $this->discoverOutput(), $php);

            unlink($this->configPath.'/leap.php');
            rmdir($this->configPath);
            $this->configPath = null;
        }
    }

    public function test_only_package_discover_carries_the_notice(): void
    {
        $this->publishConfigFile("<?php return ['tinymce' => ['cdn' => 'https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js']];");

        $this->assertSame('', $this->discoverOutput('migrate'));
    }
}
