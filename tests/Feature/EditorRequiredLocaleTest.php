<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Tests\Fixtures\RequiredTitleResource;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * A required translatable field used to be required specifically in the default (first)
 * locale, so a page written in only a secondary language failed with "the <default locale>
 * field is required" — even though it had a perfectly good title in another language. It is
 * now required in at least one locale.
 */
class EditorRequiredLocaleTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Default/first locale is English; content here is Dutch-first.
        $app['config']->set('leap.locales', ['en' => 'English', 'nl' => 'Nederlands']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->boolean('active')->default(true);
            $table->text('title')->nullable();
            $table->text('slug')->nullable();
            $table->text('html_title')->nullable();
            $table->timestamps();
        });

        Leap::context()->setModule(RequiredTitleResource::class);
    }

    private function validator(array $titles)
    {
        $editor = new Editor;
        $data = ['data' => ['title' => $titles]];

        return Validator::make($data, $editor->rules(), $editor->messages(), $editor->validationAttributes());
    }

    public function test_the_default_locale_is_required_only_when_no_other_locale_is_filled(): void
    {
        $rules = (new Editor)->rules();

        $this->assertContains('required_without_all:data.title.nl', $rules['data.title.en']);
        $this->assertContains('nullable', $rules['data.title.nl']);
    }

    public function test_a_title_in_only_a_secondary_locale_validates(): void
    {
        $this->assertFalse($this->validator(['en' => '', 'nl' => 'Over ons'])->fails());
    }

    public function test_a_title_in_only_the_default_locale_validates(): void
    {
        $this->assertFalse($this->validator(['en' => 'About', 'nl' => ''])->fails());
    }

    public function test_an_empty_title_in_every_locale_fails_with_a_readable_message(): void
    {
        $validator = $this->validator(['en' => '', 'nl' => '']);

        $this->assertTrue($validator->fails());

        // The field is named by its label (not the raw data.title.en path), and the message
        // does not leak the default "when none of ... are present" phrasing.
        $message = $validator->messages()->first('data.title.en');
        $this->assertSame('The Title (English) field is required in at least one language.', $message);
    }
}
