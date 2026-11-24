<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\DatedModel;
use NickDeKruijk\Leap\Tests\Fixtures\DatedResource;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * A date cast gives a Carbon at midnight, and the index printed it as it stringifies:
 * "2027-03-03 00:00:00". Editors read that as a wrong time.
 */
class ResourceIndexDateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('dated_models', function (Blueprint $table): void {
            $table->id();
            $table->date('starts_on')->nullable();
            $table->dateTime('received_at')->nullable();
        });
    }

    private function indexText(): string
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->roles()->attach(Role::find(1));
        Gate::before(fn () => true);

        return strip_tags(Livewire::actingAs($user)->test(DatedResource::class)->html());
    }

    public function test_a_date_column_shows_no_time(): void
    {
        DatedModel::create(['starts_on' => '2027-03-03']);

        $text = $this->indexText();

        $this->assertStringContainsString('2027-03-03', $text);
        $this->assertStringNotContainsString('00:00', $text);
    }

    public function test_a_datetime_column_keeps_its_time(): void
    {
        DatedModel::create(['received_at' => '2027-03-03 16:45:00']);

        $this->assertStringContainsString('2027-03-03 16:45:00', $this->indexText());
    }

    public function test_an_empty_date_stays_empty(): void
    {
        DatedModel::create(['received_at' => '2027-03-03 16:45:00']);

        $this->assertSame(1, substr_count($this->indexText(), '2027-'));
    }
}
