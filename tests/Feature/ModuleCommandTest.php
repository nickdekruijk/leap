<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Tests\Fixtures\TestModel;
use NickDeKruijk\Leap\Tests\TestCase;

class ModuleCommandTest extends TestCase
{
    private string $temp;

    protected function setUp(): void
    {
        parent::setUp();

        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('body')->nullable();
            $table->text('summary')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('categories');
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        $this->temp = sys_get_temp_dir().'/leap-module-'.uniqid();
        mkdir($this->temp.'/app/Leap', 0777, true);
        $this->app->setBasePath($this->temp);
    }

    protected function tearDown(): void
    {
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

    public function test_generates_a_module_from_the_model_schema(): void
    {
        $this->artisan('leap:module', [
            'model' => TestModel::class,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $source = file_get_contents($this->temp.'/app/Leap/TestModel.php');

        $this->assertStringContainsString('public $model = '.TestModel::class.'::class;', $source);
        $this->assertStringContainsString("public \$active = 'active';", $source);
        $this->assertStringContainsString("public \$orderBy = 'sort';", $source);
        $this->assertStringContainsString("Attribute::make('active')->switch()", $source);
        $this->assertStringContainsString("Attribute::make('title')->index(1)->searchable()->required()", $source);
        $this->assertStringContainsString("Attribute::make('slug')->unique()->slugFrom('title')", $source);
        $this->assertStringContainsString('Attribute::make(\'category_id\')->foreign(App\Models\Category::class)->filterable()', $source);
        $this->assertStringContainsString("Attribute::make('sort')->sortable()", $source);
        $this->assertStringContainsString("Attribute::make('body')->richtext()", $source);
        $this->assertStringContainsString("Attribute::make('summary')->textarea()", $source);
        $this->assertStringContainsString("'nl' => 'Title', 'en' => 'Title'", $source);
    }

    public function test_dry_run_does_not_write_anything(): void
    {
        $this->artisan('leap:module', [
            'model' => TestModel::class,
            '--dry-run' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $this->assertFileDoesNotExist($this->temp.'/app/Leap/TestModel.php');
    }

    public function test_writes_the_module_file_when_not_a_dry_run(): void
    {
        $this->artisan('leap:module', [
            'model' => TestModel::class,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $this->assertFileExists($this->temp.'/app/Leap/TestModel.php');
        $this->assertStringContainsString('class TestModel extends Resource', file_get_contents($this->temp.'/app/Leap/TestModel.php'));
    }

    private function fixtureModuleFile(): string
    {
        return <<<'PHP'
        <?php

        namespace App\Leap;

        use NickDeKruijk\Leap\Classes\Attribute;
        use NickDeKruijk\Leap\Resource;
        use NickDeKruijk\Leap\Tests\Fixtures\TestModel;

        class TestModel extends Resource
        {
            public $model = TestModel::class;

            public $icon = 'fas-table';

            public function attributes(): array
            {
                return [
                    Attribute::make('id')->indexOnly(),
                    Attribute::make('title')->index(1)->searchable()->required()->label('Custom Title Label'),
                    Attribute::make('slug')->unique()->slugFrom('title'),
                    Attribute::make('body')->richtext(),
                    Attribute::make('summary')->textarea(),
                    Attribute::make('active')->switch()->label('Custom Active Label'),
                    Attribute::make('category_id')->foreign(),
                    Attribute::make('sort')->sortable(),
                ];
            }
        }

        PHP;
    }

    public function test_merging_appends_only_new_columns_and_leaves_existing_lines_untouched(): void
    {
        file_put_contents($this->temp.'/app/Leap/TestModel.php', $this->fixtureModuleFile());

        // A column added to the table after the module was first generated
        Schema::table('test_models', function (Blueprint $table) {
            $table->string('subtitle')->nullable();
        });

        $this->artisan('leap:module', [
            'model' => TestModel::class,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $updated = file_get_contents($this->temp.'/app/Leap/TestModel.php');

        // Hand-written lines survive byte-for-byte
        $this->assertStringContainsString('Custom Title Label', $updated);
        $this->assertStringContainsString('Custom Active Label', $updated);
        $this->assertSame(1, substr_count($updated, "Attribute::make('title')"));
        $this->assertSame(1, substr_count($updated, "Attribute::make('active')"));

        // Only the genuinely new column was appended, exactly once
        $this->assertSame(1, substr_count($updated, "Attribute::make('subtitle')"));
        $this->assertSame(1, substr_count($updated, "Attribute::make('slug')"));
        $this->assertSame(1, substr_count($updated, "Attribute::make('body')"));
    }

    public function test_interactively_asks_for_the_label_in_a_non_english_app_locale_and_keeps_english_as_generated(): void
    {
        file_put_contents($this->temp.'/app/Leap/TestModel.php', $this->fixtureModuleFile());

        Schema::table('test_models', function (Blueprint $table) {
            $table->string('subtitle')->nullable();
        });

        $this->app->setLocale('nl');

        $editableTypes = ['text', 'textarea', 'richtext', 'number', 'date', 'datetime', 'time', 'email', 'password', 'switch', 'json'];

        $this->artisan('leap:module', ['model' => TestModel::class])
            ->expectsChoice("Field type for 'subtitle'", 'text', array_merge($editableTypes, $editableTypes))
            ->expectsConfirmation("Is 'subtitle' required?", 'no')
            ->expectsQuestion("Label for 'subtitle' (nl)", 'Ondertitel')
            ->expectsConfirmation('Add subtitle to app/Leap/TestModel.php?', 'yes')
            ->assertExitCode(0);

        $updated = file_get_contents($this->temp.'/app/Leap/TestModel.php');

        $this->assertStringContainsString("'en' => 'Subtitle'", $updated);
        $this->assertStringContainsString("'nl' => 'Ondertitel'", $updated);
    }

    public function test_merging_is_a_no_op_when_no_new_columns_exist(): void
    {
        $this->artisan('leap:module', [
            'model' => TestModel::class,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $before = file_get_contents($this->temp.'/app/Leap/TestModel.php');

        $this->artisan('leap:module', [
            'model' => TestModel::class,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $this->assertSame($before, file_get_contents($this->temp.'/app/Leap/TestModel.php'));
    }
}
