<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Tests\TestCase;
use ReflectionMethod;

class TranslatableNormalizationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // nl is the default (first) locale used to key legacy values
        $app['config']->set('leap.locales', ['nl' => 'Nederlands', 'en' => 'English']);
    }

    /**
     * Invoke the protected Editor::normalizeTranslations() helper.
     *
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     */
    private function normalize(array $translations, mixed $raw): array
    {
        $method = new ReflectionMethod(Editor::class, 'normalizeTranslations');

        return $method->invoke(new Editor, $translations, $raw);
    }

    public function test_legacy_plain_string_is_wrapped_in_the_default_locale(): void
    {
        $this->assertSame(['nl' => 'Hello legacy'], $this->normalize([], 'Hello legacy'));
    }

    public function test_existing_translations_are_returned_untouched(): void
    {
        $translations = ['nl' => 'Hallo', 'en' => 'Hello'];

        // Even with a stray raw value, a populated translations array wins.
        $this->assertSame($translations, $this->normalize($translations, 'ignored'));
    }

    public function test_null_and_empty_raw_stay_empty(): void
    {
        $this->assertSame([], $this->normalize([], null));
        $this->assertSame([], $this->normalize([], ''));
        $this->assertSame([], $this->normalize([], '   '));
    }

    public function test_real_translations_json_is_not_resurrected(): void
    {
        // spatie legitimately returned [] (all locales empty); the raw column is
        // already a translations JSON object — do not wrap it as a legacy string.
        $this->assertSame([], $this->normalize([], '{"nl":"","en":""}'));
        $this->assertSame([], $this->normalize([], '[]'));
    }
}
