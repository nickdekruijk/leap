<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\ServiceProvider;
use NickDeKruijk\Leap\Tests\TestCase;
use ReflectionMethod;

/**
 * How a published config/leap.php combines with the one this package ships.
 *
 * Laravel's mergeConfigFrom is a single array_merge, so a published section
 * replaces the package's whole. Every release that adds a key below the top
 * level would then arrive as null in every project that published its config
 * before it -- silently off, with no error to notice. Merging nested maps
 * fixes that, but only maps: a list is a complete answer, and merging one by
 * index hands back the very entries someone removed.
 */
class ConfigMergeTest extends TestCase
{
    /**
     * @param  array<mixed>  $package
     * @param  array<mixed>  $published
     * @return array<mixed>
     */
    private function merge(array $package, array $published): array
    {
        $merge = new ReflectionMethod(ServiceProvider::class, 'mergeLeapConfig');

        return $merge->invoke(new ServiceProvider($this->app), $package, $published);
    }

    public function test_a_key_the_published_config_never_heard_of_arrives_with_its_default(): void
    {
        $merged = $this->merge(
            ['ai' => ['show_costs' => true, 'record_costs' => true, 'timeout' => 60]],
            ['ai' => ['timeout' => 120]],
        );

        $this->assertTrue($merged['ai']['show_costs']);
        $this->assertTrue($merged['ai']['record_costs']);
        $this->assertSame(120, $merged['ai']['timeout'], 'What the project does say must still win.');
    }

    public function test_the_published_value_wins_at_every_depth(): void
    {
        $merged = $this->merge(
            ['ai' => ['image' => ['max_width' => null, 'jpeg_quality' => 82]]],
            ['ai' => ['image' => ['max_width' => 1600]]],
        );

        $this->assertSame(1600, $merged['ai']['image']['max_width']);
        $this->assertSame(82, $merged['ai']['image']['jpeg_quality']);
    }

    public function test_null_is_an_answer_rather_than_a_gap(): void
    {
        $merged = $this->merge(
            ['ai' => ['image' => ['max_width' => 1600]]],
            ['ai' => ['image' => ['max_width' => null]]],
        );

        $this->assertNull($merged['ai']['image']['max_width'], 'Publishing null must not fall back to the package value.');
    }

    /**
     * The reason this merge is not array_replace_recursive: that matches lists
     * up by index, so a project keeping two of six modules would silently get
     * the other four back.
     */
    public function test_a_shortened_list_stays_shortened(): void
    {
        $merged = $this->merge(
            ['default_modules' => ['Dashboard', 'FileManager', 'Profile', 'Logout', 'Roles', 'User']],
            ['default_modules' => ['Dashboard', 'Logout']],
        );

        $this->assertSame(['Dashboard', 'Logout'], $merged['default_modules']);
    }

    public function test_an_emptied_list_stays_empty(): void
    {
        $merged = $this->merge(
            ['ai' => ['image' => ['presets' => ['standard' => 'gpt-image-1-mini']]]],
            ['ai' => ['image' => ['presets' => []]]],
        );

        $this->assertSame([], $merged['ai']['image']['presets'], 'An empty array says "none", which a merge must not undo.');
    }

    public function test_a_map_is_merged_where_a_list_is_replaced(): void
    {
        $merged = $this->merge(
            ['ai' => ['pricing' => ['a' => 1, 'b' => 2]], 'credentials' => ['email', 'password']],
            ['ai' => ['pricing' => ['b' => 20]], 'credentials' => ['username']],
        );

        $this->assertSame(['a' => 1, 'b' => 20], $merged['ai']['pricing']);
        $this->assertSame(['username'], $merged['credentials']);
    }

    /**
     * A value that changed shape cannot be combined with the other -- keys and
     * positions do not correspond -- so the published one is taken whole.
     */
    public function test_a_shape_change_replaces_rather_than_merges(): void
    {
        $this->assertSame(
            ['content' => ['news' => 'App\Models\News']],
            $this->merge(['content' => []], ['content' => ['news' => 'App\Models\News']]),
        );

        $this->assertSame(
            ['locales' => ['nl', 'en']],
            $this->merge(['locales' => ['nl' => 'Nederlands']], ['locales' => ['nl', 'en']]),
        );
    }

    public function test_a_section_the_project_never_published_is_kept_whole(): void
    {
        $merged = $this->merge(
            ['logging' => ['enabled' => true, 'ip_address' => false]],
            [],
        );

        $this->assertSame(['enabled' => true, 'ip_address' => false], $merged['logging']);
    }

    /**
     * The end of it: what the running application reads. Boots with the package
     * config as published, so a key this package ships must be readable without
     * the caller passing a default.
     */
    public function test_the_booted_application_sees_the_shipped_defaults(): void
    {
        // assertFalse, not assertFalsy: null is what a key that never arrived reads as,
        // and this asserting the shipped false is the whole point.
        $this->assertFalse(config('leap.ai.show_costs'));
        $this->assertTrue(config('leap.ai.record_costs'));
        $this->assertSame([], config('leap.ai.image.presets'));
    }
}
