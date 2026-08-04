<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\ServiceProvider;
use NickDeKruijk\Leap\Tests\TestCase;
use ReflectionMethod;

/**
 * The merge as the application actually meets it: this package's real
 * config/leap.php combined with one a project published some releases ago,
 * standing in for every install that has not republished since.
 *
 * ConfigMergeTest covers the rules against hand-written arrays; this runs the
 * method register() calls, against the file this package ships. The stub is
 * leap.ai as it looked before presets and the cost switches landed, so every
 * assertion below fails on a plain array_merge -- which is the point of it.
 */
class ConfigMergePublishedTest extends TestCase
{
    /**
     * Publish $leap, then merge the shipped config into it the way register()
     * does. Testbench applies defineEnvironment after the providers register,
     * so setting the config there would overwrite the merge instead of feeding
     * it -- the order has to be arranged here.
     *
     * @param  array<mixed>  $leap
     */
    private function publish(array $leap): void
    {
        config(['leap' => $leap]);

        (new ReflectionMethod(ServiceProvider::class, 'mergeLeapConfigFrom'))
            ->invoke(new ServiceProvider($this->app), __DIR__.'/../../config/leap.php', 'leap');
    }

    private function publishPreOnePointOne(): void
    {
        $this->publish([
            'ai' => [
                'timeout' => 120,
                'image' => [
                    'folder' => 'ai/{module}',
                    'max_width' => 1600,
                ],
            ],
            'auth_2fa' => ['enabled' => false],
            'default_modules' => ['App\Leap\Dashboard'],
        ]);
    }

    public function test_keys_added_after_the_publish_arrive_at_their_shipped_default(): void
    {
        $this->publishPreOnePointOne();

        $this->assertTrue(config('leap.ai.show_costs'));
        $this->assertTrue(config('leap.ai.record_costs'));
    }

    public function test_that_holds_below_the_second_level_too(): void
    {
        $this->publishPreOnePointOne();

        $this->assertSame([], config('leap.ai.image.presets'));
        $this->assertSame(82, config('leap.ai.image.jpeg_quality'));
    }

    public function test_what_the_project_did_publish_is_untouched(): void
    {
        $this->publishPreOnePointOne();

        $this->assertSame(120, config('leap.ai.timeout'));
        $this->assertSame('ai/{module}', config('leap.ai.image.folder'));
        $this->assertSame(1600, config('leap.ai.image.max_width'));
        $this->assertFalse(config('leap.auth_2fa.enabled'));
    }

    /**
     * Filling in what a project is silent about must not undo what it decided:
     * auth_2fa.enabled is published false, so the sub-array it never mentioned
     * arriving in full is right, but the false itself has to survive.
     */
    public function test_a_published_section_keeps_its_own_answers(): void
    {
        $this->publishPreOnePointOne();

        $this->assertFalse(config('leap.auth_2fa.enabled'));
        $this->assertIsArray(config('leap.auth_2fa.email'));
    }

    /**
     * The navigation is the list this merge most has to keep its hands off:
     * a project trimming it to one module means one module.
     */
    public function test_a_trimmed_module_list_is_not_refilled(): void
    {
        $this->publishPreOnePointOne();

        $this->assertSame(['App\Leap\Dashboard'], config('leap.default_modules'));
    }

    /**
     * A project that published nothing at all -- the config is the package's.
     */
    public function test_an_unpublished_install_gets_the_package_config(): void
    {
        $this->publish([]);

        $this->assertTrue(config('leap.ai.show_costs'));
        $this->assertSame('admin', config('leap.route_prefix'));
        $this->assertNull(config('leap.ai.image.max_width'));
    }
}
