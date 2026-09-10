<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\RecordDraft;
use NickDeKruijk\Leap\Tests\Fixtures\AceModel;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * What the code editor stores when it holds JSON.
 *
 * The value is written by hand and read back by the same person, so it is kept in the
 * shape they would have typed rather than the shortest one a machine would accept.
 */
class AceJsonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('ace_models', function (Blueprint $table): void {
            $table->id();
            $table->text('config')->nullable();
        });
    }

    private function store(string $json): string
    {
        $model = new AceModel;
        $data = ['config' => $json];

        RecordDraft::apply($model, collect([Attribute::make('config')->ace()]), $data);

        return (string) $model->config;
    }

    public function test_json_is_stored_the_way_it_was_typed(): void
    {
        // Escaped slashes are valid JSON and are not what anybody wrote: a field full
        // of "https:\/\/example.com" is read back by the person who typed the address.
        $stored = $this->store('{"url":"https://example.com/a/b","name":"Café"}');

        $this->assertStringContainsString('https://example.com/a/b', $stored);
        $this->assertStringNotContainsString('\\/', $stored);
        $this->assertStringContainsString('Café', $stored);

        // Still pretty-printed, which is the reason this branch exists at all.
        $this->assertStringContainsString("\n    ", $stored);

        // And still the same JSON.
        $this->assertSame(
            ['url' => 'https://example.com/a/b', 'name' => 'Café'],
            json_decode($stored, true),
        );
    }

    public function test_an_empty_editor_stores_nothing(): void
    {
        $model = new AceModel;
        $data = ['config' => ''];

        RecordDraft::apply($model, collect([Attribute::make('config')->ace()]), $data);

        $this->assertNull($model->config);
    }
}
