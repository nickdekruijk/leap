<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\ArticleResource;
use NickDeKruijk\Leap\Tests\Fixtures\PreviewableModel;
use NickDeKruijk\Leap\Tests\Fixtures\PreviewableResource;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * The editor's preview: the frontend of one record, for the person editing it.
 *
 * Two things are load-bearing here and are asserted rather than assumed. It is
 * behind the read permission of the module that owns the record, so it cannot become
 * a way around what a role may see. And it widens nothing: the record is fetched by
 * id, so no scope was relaxed for it and the ordinary rules about what the frontend
 * serves are untouched.
 */
class PreviewTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.default_modules', [
            Dashboard::class,
            PreviewableResource::class,
            ArticleResource::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        View::addNamespace('previewfixture', __DIR__.'/../Fixtures/views');

        Schema::create('previewable_models', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->text('body')->nullable();
            $table->boolean('active')->default(true);
            $table->dateTime('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    private function record(array $attributes = []): PreviewableModel
    {
        return PreviewableModel::create(array_merge([
            'title' => ['en' => 'Draft page'],
            'body' => 'Saved body',
            'active' => true,
        ], $attributes));
    }

    private function actingAsEditor(?Role $role = null): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->roles()->attach($role ?: Role::find(1));
        $this->actingAs($user);

        return $user;
    }

    private function url(PreviewableModel $record, ?string $locale = null, string $module = 'previews'): string
    {
        return route('leap.preview', array_filter([
            'module' => $module,
            'id' => $record->id,
            'locale' => $locale,
        ]));
    }

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $record = $this->record();

        $this->get($this->url($record))->assertRedirect(route('leap.login'));
    }

    /**
     * 404 rather than 403, the same answer Module::boot() gives: a preview URL may not
     * tell someone that a record exists when the module it belongs to would not.
     */
    public function test_a_role_without_read_on_the_module_gets_a_404(): void
    {
        $record = $this->record();
        $this->actingAsEditor(Role::create(['name' => 'Nothing', 'permissions' => []]));

        $this->get($this->url($record))->assertNotFound();
    }

    public function test_an_unknown_module_or_record_is_a_404(): void
    {
        $record = $this->record();
        $this->actingAsEditor();

        $this->get($this->url($record, module: 'nosuchmodule'))->assertNotFound();
        $this->get(route('leap.preview', ['module' => 'previews', 'id' => $record->id + 99]))->assertNotFound();
    }

    /**
     * A deleted record has nothing to review. find() excludes soft-deleted rows and
     * the preview deliberately does not go looking for them.
     */
    public function test_a_soft_deleted_record_is_a_404(): void
    {
        $record = $this->record();
        $url = $this->url($record);
        $record->delete();
        $this->actingAsEditor();

        $this->get($url)->assertNotFound();
    }

    /**
     * The contract is the real switch: a module that does not say how its records render
     * has no preview, because only the application knows how one becomes a page.
     */
    public function test_a_module_that_does_not_preview_is_a_404(): void
    {
        $record = $this->record();
        $this->actingAsEditor();

        $this->get($this->url($record, module: 'article-resource'))->assertNotFound();
    }

    /**
     * The everyday case: one language, an ordinary record, and a button instead of
     * hunting for the page.
     */
    public function test_a_monolingual_site_previews_a_record_without_a_locale_segment(): void
    {
        config(['leap.locales' => null]);
        $record = $this->record(['title' => 'Plain page']);
        $this->actingAsEditor();

        $this->get($this->url($record))
            ->assertOk()
            ->assertSee('record:Plain page')
            ->assertSee('preview:yes');
    }

    public function test_a_locale_segment_on_a_monolingual_site_is_a_404(): void
    {
        config(['leap.locales' => null]);
        $record = $this->record();
        $this->actingAsEditor();

        $this->get($this->url($record, 'en'))->assertNotFound();
    }

    public function test_an_inactive_record_renders(): void
    {
        $record = $this->record(['active' => false, 'title' => 'Not live yet']);
        $this->actingAsEditor();

        $this->get($this->url($record))
            ->assertOk()
            ->assertSee('record:Not live yet')
            ->assertSee('active:no');
    }

    /**
     * The point of the whole thing: locales_published decides what a visitor reads,
     * and an unpublished language has no addresses. The preview is the one way in.
     */
    public function test_a_locale_the_frontend_does_not_publish_renders(): void
    {
        config([
            'leap.locales' => ['nl' => 'Nederlands', 'en' => 'English'],
            'leap.locales_published' => ['nl'],
        ]);
        $record = $this->record(['title' => ['nl' => 'Over ons', 'en' => 'About us']]);
        $this->actingAsEditor();

        $this->assertArrayNotHasKey('en', Leap::localesPublished());

        $this->get($this->url($record, 'en'))
            ->assertOk()
            ->assertSee('record:About us')
            ->assertSee('locale:en');
    }

    public function test_a_locale_outside_leap_locales_is_a_404(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);
        $record = $this->record();
        $this->actingAsEditor();

        $this->get($this->url($record, 'de'))->assertNotFound();
    }

    public function test_the_response_is_kept_out_of_indexes_and_shared_caches(): void
    {
        $record = $this->record();
        $this->actingAsEditor();

        $response = $this->get($this->url($record));

        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    /**
     * The flag lives in a scoped binding for exactly this reason. If it could outlive
     * its request, "a preview is open" would start to mean "this browser sees
     * unpublished things", which is what preview must never become. Forgetting the
     * scoped instances is what ends a request, so it is what the test does.
     */
    public function test_the_preview_flag_does_not_survive_the_request(): void
    {
        $record = $this->record();
        $this->actingAsEditor();

        $this->get($this->url($record))->assertOk()->assertSee('preview:yes');

        $this->app->forgetScopedInstances();

        $this->assertFalse(Leap::isPreview());
        $this->assertNull(Leap::preview());
        $this->assertNull(session('leap.preview.record'));
    }
}
