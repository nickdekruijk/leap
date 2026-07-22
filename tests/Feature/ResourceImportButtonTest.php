<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\ImportableResource;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * A module enables CSV import by declaring $allowImport with the columns and attributes
 * it accepts. The index template also read $allowImport['type'] — a key nothing sets,
 * documents or generates — so any such module died on its own index page with
 * "Undefined array key 'type'" before it drew a single row.
 */
class ResourceImportButtonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->boolean('active')->default(true);
            $table->text('title')->nullable();
            $table->timestamps();
        });
    }

    private function superuser(): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->roles()->attach(Role::find(1));
        Gate::before(fn () => true);

        return $user;
    }

    public function test_a_module_that_allows_importing_renders_its_index(): void
    {
        $html = Livewire::actingAs($this->superuser())->test(ImportableResource::class)->html();

        $this->assertStringContainsString('leap-search-input', $html, 'The index rendered.');
        // The file input inside the @if; the property names alone appear in the
        // Livewire snapshot whether the button renders or not.
        $this->assertStringContainsString('x-ref="importCSV"', $html, 'The import button is there.');
    }

    public function test_an_explicit_type_still_decides_whether_the_button_shows(): void
    {
        $resource = new ImportableResource;
        $resource->allowImport['type'] = 'something-else';

        $this->assertStringNotContainsString(
            'x-ref="importCSV"',
            Livewire::actingAs($this->superuser())->test(ImportableResource::class, ['allowImport' => $resource->allowImport])->html(),
        );
    }
}
