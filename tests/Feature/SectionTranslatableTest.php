<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\Section;
use NickDeKruijk\Leap\Tests\TestCase;

class SectionTranslatableTest extends TestCase
{
    private function section(): Section
    {
        return Section::make('block')->attributes(
            Attribute::make('active')->switch(),
            Attribute::make('image')->media(),
            Attribute::make('image_position')->select()->values(['left' => 'Left', 'right' => 'Right']),
            Attribute::make('head'),
            Attribute::make('intro')->textarea(),
            Attribute::make('body')->richtext(),
        );
    }

    /**
     * @return array<string, bool>
     */
    private function flags(Section $section): array
    {
        $flags = [];
        foreach ($section->attributes as $attribute) {
            $flags[$attribute->name] = $attribute->translatable;
        }

        return $flags;
    }

    public function test_translatable_only_marks_exactly_the_named_fields(): void
    {
        $flags = $this->flags($this->section()->translatableOnly('head', 'body'));

        $this->assertTrue($flags['head']);
        $this->assertTrue($flags['body']);
        $this->assertFalse($flags['intro']);
        $this->assertFalse($flags['active']);
        $this->assertFalse($flags['image']);
        $this->assertFalse($flags['image_position']);
    }

    public function test_translatable_except_marks_every_textual_field_and_skips_structural_ones(): void
    {
        // No names excluded: plain text, textarea and rich-text become translatable;
        // a switch, media picker and a layout select are skipped automatically.
        $flags = $this->flags($this->section()->translatableExcept());

        $this->assertTrue($flags['head']);
        $this->assertTrue($flags['intro']);
        $this->assertTrue($flags['body']);

        $this->assertFalse($flags['active']);
        $this->assertFalse($flags['image']);
        $this->assertFalse($flags['image_position']);
    }

    public function test_translatable_except_keeps_a_named_textual_field_shared(): void
    {
        $flags = $this->flags($this->section()->translatableExcept('head'));

        $this->assertFalse($flags['head']);
        $this->assertTrue($flags['intro']);
        $this->assertTrue($flags['body']);
    }
}
