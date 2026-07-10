<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Commands\TemplateCommand;
use NickDeKruijk\Leap\Tests\TestCase;
use ReflectionMethod;

class TemplateManifestTest extends TestCase
{
    public function test_template_files_manifest_matches_the_shipped_stubs(): void
    {
        $method = new ReflectionMethod(TemplateCommand::class, 'templateFiles');
        $files = $method->invoke(new TemplateCommand);

        $stubBase = dirname(__DIR__, 2).'/stubs/template';

        $this->assertNotEmpty($files, 'templateFiles() returned no files.');

        foreach ($files as $relative) {
            $this->assertFileExists(
                $stubBase.'/'.$relative,
                "leap:template lists \"{$relative}\" but no matching stub ships under stubs/template.",
            );
        }
    }
}
