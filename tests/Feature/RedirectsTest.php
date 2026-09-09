<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Livewire\NotFounds as NotFoundsScreen;
use NickDeKruijk\Leap\Livewire\Redirects as RedirectsScreen;
use NickDeKruijk\Leap\Models\NotFound;
use NickDeKruijk\Leap\Models\Redirect;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Where the old addresses of a site go, and what happens to the ones with nowhere
 * to go yet.
 *
 * The ordering against the 404 log is the part worth pinning: both hang off the same
 * render hook, and a path that redirects must never also be written down as a broken
 * link. That is decided by registration order, which is the kind of thing that
 * survives a refactor only if a test says so.
 */
class RedirectsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['leap.redirects.enabled' => true]);
        config(['leap.redirects.capture.enabled' => false]);
    }

    private function rule(string $path, ?string $destination, array $attributes = []): Redirect
    {
        return Redirect::create(array_merge([
            'path' => $path,
            'destination' => $destination,
            'active' => true,
        ], $attributes));
    }

    public function test_an_old_address_moves_permanently_to_the_new_one(): void
    {
        $this->rule('mogelijkheden/fysiotherapie', 'behandelingen/fysiotherapie');

        $this->get('/mogelijkheden/fysiotherapie')
            ->assertStatus(301)
            ->assertRedirect('/behandelingen/fysiotherapie');
    }

    public function test_a_destination_is_made_absolute(): void
    {
        // Without the leading slash a browser resolves it against the directory the
        // old address was in, so this would land on /a/b/c rather than /c.
        $this->rule('a/b/old', 'c');

        $this->get('/a/b/old')->assertRedirect('/c');
    }

    public function test_slashes_and_casing_do_not_make_a_second_rule(): void
    {
        $this->rule('/Praktijk/', '/');

        $this->assertSame('praktijk', Redirect::first()->path);
        $this->get('/praktijk')->assertRedirect('/');
    }

    public function test_an_old_address_arrives_in_whatever_casing_it_was_written_in(): void
    {
        // A URL path is case-sensitive and this deliberately is not: old links and old
        // search results carry the casing the previous site used, which is the whole
        // reason this table exists. Leaving it to the database would answer this
        // differently on MySQL than on PostgreSQL.
        $this->rule('mogelijkheden/manueel-therapie', '/behandelingen/manuele-therapie');

        $this->get('/Mogelijkheden/Manueel-Therapie')->assertRedirect('/behandelingen/manuele-therapie');
    }

    public function test_a_destination_keeps_its_casing(): void
    {
        // What is redirected to may well be case-sensitive, so it is left alone.
        $this->rule('oud', '/Nieuw/Pad');

        $this->get('/oud')->assertRedirect('/Nieuw/Pad');
    }

    public function test_an_encoded_address_matches_the_one_that_was_typed(): void
    {
        $this->rule('cliëntroute', 'clientroute');

        $this->get('/'.rawurlencode('cliëntroute'))->assertRedirect('/clientroute');
    }

    public function test_a_wildcard_catches_everything_below_it(): void
    {
        $this->rule('mogelijkheden/*', '/behandelingen');

        $this->get('/mogelijkheden/wat-dan-ook')->assertRedirect('/behandelingen');
        $this->get('/mogelijkheden')->assertRedirect('/behandelingen');
        $this->get('/mogelijkhedenX')->assertNotFound();
    }

    public function test_the_longest_wildcard_wins(): void
    {
        $this->rule('oud/*', '/a');
        $this->rule('oud/diep/*', '/b');

        $this->get('/oud/diep/pagina')->assertRedirect('/b');
        $this->get('/oud/pagina')->assertRedirect('/a');
    }

    public function test_an_exact_rule_beats_a_wildcard(): void
    {
        $this->rule('oud/*', '/algemeen');
        $this->rule('oud/pagina', '/specifiek');

        $this->get('/oud/pagina')->assertRedirect('/specifiek');
    }

    public function test_the_query_string_comes_along(): void
    {
        $this->rule('oud', '/nieuw');

        $this->get('/oud?utm_source=nieuwsbrief')->assertRedirect('/nieuw?utm_source=nieuwsbrief');
    }

    public function test_a_whole_url_is_left_alone_as_a_destination(): void
    {
        $this->rule('afspraak', 'https://booking.example.com/practice');

        $this->get('/afspraak')->assertRedirect('https://booking.example.com/practice');
    }

    public function test_a_temporary_rule_answers_302(): void
    {
        $this->rule('tijdelijk', '/elders', ['status' => 302]);

        $this->get('/tijdelijk')->assertStatus(302);
    }

    public function test_a_rule_that_is_switched_off_does_nothing(): void
    {
        $this->rule('oud', '/nieuw', ['active' => false]);

        $this->get('/oud')->assertNotFound();
    }

    public function test_the_whole_feature_can_be_switched_off(): void
    {
        config(['leap.redirects.enabled' => false]);
        $this->rule('oud', '/nieuw');

        $this->get('/oud')->assertNotFound();
    }

    public function test_a_post_is_not_redirected(): void
    {
        $this->rule('oud', '/nieuw');

        $this->post('/oud')->assertNotFound();
    }

    public function test_it_counts_how_often_a_rule_is_used(): void
    {
        $redirect = $this->rule('oud', '/nieuw');

        $this->get('/oud');
        $this->get('/oud');

        $redirect->refresh();

        $this->assertSame(2, $redirect->hits);
        $this->assertNotNull($redirect->last_used_at);
    }

    public function test_counting_can_be_switched_off(): void
    {
        config(['leap.redirects.count_hits' => false]);
        $redirect = $this->rule('oud', '/nieuw');

        $this->get('/oud');

        $this->assertSame(0, $redirect->refresh()->hits);
    }

    public function test_an_edited_wildcard_takes_effect_without_waiting_for_a_cache(): void
    {
        $rule = $this->rule('oud/*', '/eerst');
        $this->get('/oud/x')->assertRedirect('/eerst');

        $rule->update(['destination' => '/daarna']);

        $this->get('/oud/x')->assertRedirect('/daarna');
    }

    public function test_an_address_that_is_not_in_the_list_is_left_alone(): void
    {
        $this->rule('oud', '/nieuw');

        $this->get('/iets-anders')->assertNotFound();
    }

    public function test_a_page_that_exists_is_never_touched(): void
    {
        Route::get('/bestaat', fn () => 'hier ben ik');
        $this->rule('bestaat', '/ergens-anders');

        $this->get('/bestaat')->assertOk()->assertSee('hier ben ik');
    }

    public function test_it_writes_down_an_address_with_nowhere_to_go(): void
    {
        config(['leap.redirects.capture.enabled' => true]);

        $this->get('/kwijt')->assertNotFound();

        $redirect = NotFound::firstWhere('path', 'kwijt');

        $this->assertNotNull($redirect);
        $this->assertNull($redirect->destination);
        $this->assertSame(1, $redirect->hits);
    }

    public function test_it_writes_down_nothing_unless_a_project_asks(): void
    {
        $this->get('/kwijt')->assertNotFound();

        $this->assertSame(0, NotFound::count());
    }

    public function test_the_same_address_is_noted_once_per_window(): void
    {
        config(['leap.redirects.capture.enabled' => true]);

        $this->get('/kwijt');
        $this->get('/kwijt');

        $this->assertSame(1, NotFound::firstWhere('path', 'kwijt')->hits);
    }

    public function test_answering_a_missing_address_turns_it_into_a_rule(): void
    {
        // Filling in the destination is the whole promotion: the rule is created and
        // the question stops being asked.
        config(['leap.redirects.capture.enabled' => true]);
        $this->get('/kwijt');

        NotFound::firstWhere('path', 'kwijt')->update(['destination' => '/gevonden']);

        $this->assertNull(NotFound::firstWhere('path', 'kwijt'));
        $this->get('/kwijt')->assertRedirect('/gevonden');
    }

    public function test_writing_a_rule_by_hand_clears_it_from_the_worklist(): void
    {
        config(['leap.redirects.capture.enabled' => true]);
        $this->get('/kwijt');

        $this->rule('kwijt', '/gevonden');

        $this->assertNull(NotFound::firstWhere('path', 'kwijt'));
    }

    public function test_it_notes_which_pages_carried_the_dead_link(): void
    {
        config(['leap.redirects.capture.enabled' => true, 'leap.redirects.capture.throttle_minutes' => 0]);

        $this->get('/kwijt', ['referer' => 'https://example.com/een']);
        $this->get('/kwijt', ['referer' => 'https://example.com/een']);
        $this->get('/kwijt', ['referer' => 'https://example.com/twee']);

        $this->assertSame([
            'https://example.com/een' => 2,
            'https://example.com/twee' => 1,
        ], NotFound::firstWhere('path', 'kwijt')->referers);
    }

    public function test_the_newest_referer_is_never_crowded_out_by_older_ones(): void
    {
        config(['leap.redirects.capture.enabled' => true, 'leap.redirects.capture.throttle_minutes' => 0]);
        config(['leap.redirects.capture.values_max' => 2]);

        // Two established ones, then a link that broke today. Keeping purely the most
        // followed would drop the third on arrival and it could never climb.
        foreach (['een', 'een', 'twee', 'twee', 'drie'] as $page) {
            $this->get('/kwijt', ['referer' => 'https://example.com/'.$page]);
        }

        $this->assertSame([
            'https://example.com/een' => 2,
            'https://example.com/drie' => 1,
        ], NotFound::firstWhere('path', 'kwijt')->referers);
    }

    public function test_it_says_nothing_about_the_visitor_unless_a_project_asks(): void
    {
        // The user agent and the address describe whoever asked rather than the site,
        // and this table is open to everyone with panel access. Off is the default a
        // project inherits without reading the config, so it is the one worth pinning.
        config(['leap.redirects.capture.enabled' => true]);

        $this->get('/kwijt', ['user-agent' => 'SomeBot/2.1']);

        $redirect = NotFound::firstWhere('path', 'kwijt');

        $this->assertNull($redirect->user_agents);
        $this->assertNull($redirect->ip_addresses);
    }

    public function test_it_can_note_what_asked_and_from_where(): void
    {
        config([
            'leap.redirects.capture.enabled' => true,
            'leap.redirects.capture.user_agent' => true,
            'leap.redirects.capture.ip_address' => true,
        ]);

        $this->get('/kwijt', ['user-agent' => 'SomeBot/2.1']);

        $redirect = NotFound::firstWhere('path', 'kwijt');

        $this->assertSame(['SomeBot/2.1' => 1], $redirect->user_agents);
        $this->assertSame(['127.0.0.xxx' => 1], $redirect->ip_addresses);
    }

    public function test_the_whole_address_takes_deliberately_switching_that_off(): void
    {
        config([
            'leap.redirects.capture.enabled' => true,
            'leap.redirects.capture.ip_address' => true,
            'leap.redirects.capture.ip_address_anonymized' => false,
        ]);

        $this->get('/kwijt');

        $this->assertSame(['127.0.0.1' => 1], NotFound::firstWhere('path', 'kwijt')->ip_addresses);
    }

    public function test_the_panel_reads_the_sets_back_as_lines(): void
    {
        $notFound = NotFound::create(['path' => 'kwijt']);
        $notFound->forceFill(['referers' => ['https://example.com/een' => 3, 'https://example.com/twee' => 1]])->saveQuietly();

        $this->assertSame(
            "https://example.com/een (3x)\nhttps://example.com/twee",
            $notFound->refresh()->referer_list
        );
    }

    public function test_a_path_that_normalizes_onto_an_existing_one_is_a_field_error(): void
    {
        // The panel validates uniqueness against what was typed, which is not yet what
        // gets stored. Without this these two pass validation separately and then meet
        // on the unique index, which is a 500 rather than something to correct.
        $this->rule('praktijk', '/');

        $this->expectException(ValidationException::class);

        $this->rule('/Praktijk/', '/ergens');
    }

    public function test_two_addresses_differing_only_in_case_cannot_both_be_rules(): void
    {
        // A real limitation of matching without regard to case, and one that has to
        // fail where someone can see it rather than by one of them quietly winning.
        // The way out is a route in the host's own web.php, which matches case
        // sensitively and runs before anything 404s.
        $this->rule('test', '/een');

        $this->expectException(ValidationException::class);

        $this->rule('TEST', '/twee');
    }

    public function test_a_rule_can_still_be_saved_over_itself(): void
    {
        $rule = $this->rule('praktijk', '/');

        $rule->update(['destination' => '/elders']);

        $this->assertSame('/elders', $rule->refresh()->destination);
    }

    public function test_it_ignores_the_addresses_nobody_will_redirect(): void
    {
        config(['leap.redirects.capture.enabled' => true, 'leap.redirects.capture.throttle_minutes' => 0]);

        foreach (['/wp-login.php', '/.env', '/favicon.ico', '/xmlrpc.php'] as $path) {
            $this->get($path);
        }

        $this->assertSame(0, NotFound::count());
    }

    public function test_it_writes_down_nothing_for_a_path_carrying_control_characters(): void
    {
        // A scanner probing for /kinderfysiotherapie%00sftp-config.json, which is the
        // real one this came from. Decoding is what makes an accented address match
        // whether it arrived encoded or not, and it is also how a null byte gets in.
        config(['leap.redirects.capture.enabled' => true, 'leap.redirects.capture.throttle_minutes' => 0]);

        $this->get('/kinderfysiotherapie%00sftp-config.json');
        $this->get('/oud%0Apad');

        $this->assertSame(0, NotFound::count());
    }

    public function test_a_rule_typed_with_control_characters_keeps_none_of_them(): void
    {
        $rule = $this->rule("oud\x00pad", "/nieuw\x0Apad");

        $this->assertSame('oudpad', $rule->path);
        $this->assertSame('/nieuwpad', $rule->destination);
    }

    public function test_it_never_writes_down_the_panels_own_addresses(): void
    {
        // A module answers a role without read permission with a 404 rather than a
        // 403, so its existence stays hidden. Capturing those would fill the table
        // with the panel's own screens and undo that.
        config(['leap.redirects.capture.enabled' => true, 'leap.redirects.capture.throttle_minutes' => 0]);

        $this->get('/'.config('leap.route_prefix').'/iets-dat-niet-bestaat');

        $this->assertSame(0, NotFound::count());
    }

    /**
     * @return array<int, mixed>
     */
    private function captureLog(callable $work): array
    {
        $written = [];

        Log::listen(function ($message) use (&$written): void {
            $written[] = $message;
        });

        $work();

        return $written;
    }

    public function test_what_redirects_is_not_a_broken_link(): void
    {
        // Both hang off the same render hook and the redirect is registered first,
        // so Laravel stops there and the 404 log never sees the request. Registering
        // them the other way round would quietly fill the log with addresses that
        // are already dealt with.
        config(['leap.not_found_log.enabled' => true]);
        $this->rule('oud', '/nieuw');

        $written = $this->captureLog(fn () => $this->get('/oud')->assertRedirect('/nieuw'));

        $this->assertSame([], $written);
    }

    public function test_an_address_with_no_rule_is_still_logged_as_a_broken_link(): void
    {
        config(['leap.not_found_log.enabled' => true]);

        $written = $this->captureLog(fn () => $this->get('/kwijt')->assertNotFound());

        $this->assertCount(1, $written);
        $this->assertSame('404 /kwijt', $written[0]->message);
    }

    private function superuser(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->roles()->attach(Role::find(1));
        Gate::before(fn () => true);

        return $user;
    }

    public function test_the_panel_lists_the_rules_and_offers_the_import(): void
    {
        $this->rule('mogelijkheden/fysiotherapie', '/behandelingen/fysiotherapie');

        $html = Livewire::actingAs($this->superuser())->test(RedirectsScreen::class)->html();

        $this->assertStringContainsString('mogelijkheden/fysiotherapie', $html);
        // A redirect map arrives as a spreadsheet far more often than it is typed.
        $this->assertStringContainsString('x-ref="importCSV"', $html);
    }

    public function test_the_ceiling_holds_but_makes_room_for_the_newest(): void
    {
        // Refusing instead would let a scanner with a long wordlist switch the capture
        // off for good, which is the wrong way to fail: the addresses this exists to
        // find would be the ones turned away.
        config([
            'leap.redirects.capture.enabled' => true,
            'leap.redirects.capture.throttle_minutes' => 0,
            'leap.redirects.capture.max' => 2,
        ]);

        foreach (['/een', '/twee', '/drie'] as $path) {
            $this->get($path);
        }

        $this->assertSame(2, NotFound::count());
        $this->assertNotNull(NotFound::firstWhere('path', 'drie'));
    }

    public function test_room_is_made_by_dropping_the_least_asked_for(): void
    {
        config([
            'leap.redirects.capture.enabled' => true,
            'leap.redirects.capture.throttle_minutes' => 0,
            'leap.redirects.capture.max' => 2,
        ]);

        $this->get('/populair');
        $this->get('/populair');
        $this->get('/eenmalig');
        $this->get('/nieuw');

        $this->assertNotNull(NotFound::firstWhere('path', 'populair'));
        $this->assertNull(NotFound::firstWhere('path', 'eenmalig'));
        $this->assertNotNull(NotFound::firstWhere('path', 'nieuw'));
    }

    public function test_a_rule_is_never_touched_to_make_room(): void
    {
        // The ceiling is the worklist's; rules are not part of it however many there
        // are, and however quiet.
        config([
            'leap.redirects.capture.enabled' => true,
            'leap.redirects.capture.throttle_minutes' => 0,
            'leap.redirects.capture.max' => 1,
        ]);

        $this->rule('stil', '/ergens');
        $this->get('/een');
        $this->get('/twee');

        $this->assertNotNull(Redirect::firstWhere('path', 'stil'));
        $this->assertSame(1, NotFound::count());
    }

    public function test_a_captured_address_gone_quiet_for_long_enough_is_dropped(): void
    {
        config([
            'leap.redirects.capture.enabled' => true,
            'leap.redirects.capture.throttle_minutes' => 0,
            'leap.redirects.capture.max' => 2,
            'leap.redirects.capture.retention_days' => 90,
        ]);

        $this->get('/oud');
        $this->get('/recent');
        NotFound::firstWhere('path', 'oud')->forceFill(['last_used_at' => now()->subDays(91)])->saveQuietly();

        $this->get('/nieuw');

        $this->assertNull(NotFound::firstWhere('path', 'oud'));
        $this->assertNotNull(NotFound::firstWhere('path', 'recent'));
        $this->assertNotNull(NotFound::firstWhere('path', 'nieuw'));
    }

    public function test_a_switched_off_rule_is_shown_struck_through(): void
    {
        // Not an index column of its own: the column is fetched for this alone, so the
        // row can say it does nothing without spending a column on saying so.
        $this->rule('uit', '/ergens', ['active' => false]);

        $html = Livewire::actingAs($this->superuser())->test(RedirectsScreen::class)->html();

        $this->assertStringContainsString('leap-index-row-inactive', $html);
    }

    public function test_answering_from_the_panel_closes_the_editor_it_emptied(): void
    {
        // Saving takes the row away, and the editor used to reload the record it had
        // just saved: a ModelNotFoundException in the panel, on the one action the
        // screen exists for.
        $notFound = NotFound::create(['path' => 'kwijt']);

        $this->actingAs($this->superuser());
        Leap::context()->setModule(NotFoundsScreen::class);
        Leap::context()->setPermissions([
            NotFoundsScreen::class => ['read' => true, 'update' => true, 'delete' => true],
        ]);

        $editor = Livewire::test(Editor::class)
            ->call('openEditor', $notFound->id)
            ->set('data.destination', '/gevonden')
            ->call('save');

        $this->assertNull($editor->get('editing'));
        $this->assertNull(NotFound::find($notFound->id));
        $this->assertSame('/gevonden', Redirect::firstWhere('path', 'kwijt')->destination);
    }

    public function test_a_counter_of_zero_survives_a_save(): void
    {
        // ?: caught 0 as well as "", so an integer column holding zero was nulled on
        // every save, and a NOT NULL one refused the write outright.
        $notFound = NotFound::create(['path' => 'kwijt', 'hits' => 0]);

        $this->actingAs($this->superuser());
        Leap::context()->setModule(NotFoundsScreen::class);
        Leap::context()->setPermissions([
            NotFoundsScreen::class => ['read' => true, 'update' => true, 'delete' => true],
        ]);

        Livewire::test(Editor::class)
            ->call('openEditor', $notFound->id)
            ->set('data.destination', '/gevonden')
            ->call('save');

        $this->assertSame('/gevonden', Redirect::firstWhere('path', 'kwijt')->destination);
    }

    public function test_the_worklist_will_not_let_the_new_address_be_left_empty(): void
    {
        // The button says it creates a redirect, and there is nothing else on the screen
        // to save, so pressing it with this empty has to say so rather than do nothing.
        $notFound = NotFound::create(['path' => 'kwijt']);

        $this->actingAs($this->superuser());
        Leap::context()->setModule(NotFoundsScreen::class);
        Leap::context()->setPermissions([
            NotFoundsScreen::class => ['read' => true, 'update' => true, 'delete' => true],
        ]);

        Livewire::test(Editor::class)
            ->call('openEditor', $notFound->id)
            ->set('data.destination', '')
            ->call('save')
            ->assertHasErrors(['data.destination' => 'required']);

        $this->assertNotNull(NotFound::find($notFound->id));
        $this->assertSame(0, Redirect::count());
    }

    public function test_the_worklist_says_what_its_button_does(): void
    {
        // Saving there creates the rule and takes the row away, which is not what
        // "Save" leads anyone to expect.
        $notFound = NotFound::create(['path' => 'kwijt']);

        $this->actingAs($this->superuser());
        Leap::context()->setModule(NotFoundsScreen::class);
        Leap::context()->setPermissions([
            NotFoundsScreen::class => ['read' => true, 'update' => true, 'delete' => true],
        ]);

        $html = Livewire::test(Editor::class)->call('openEditor', $notFound->id)->html();

        $this->assertStringContainsString(__('leap::redirects.make_redirect'), $html);
        $this->assertStringNotContainsString('>'.__('leap::resource.save').'<', $html);

        // And no "save as copy": the screen offers no create, so a second row for the
        // same address — which the unique path would refuse anyway — cannot be made.
        $this->assertStringNotContainsString(__('leap::resource.save_copy'), $html);
    }

    public function test_the_panel_keeps_the_rules_and_the_worklist_apart(): void
    {
        $this->rule('werkt', '/ergens');
        config(['leap.redirects.capture.enabled' => true]);
        $this->get('/gevangen');

        // Two screens, two lists: the rules that work and the questions still open.
        $rules = Livewire::actingAs($this->superuser())->test(RedirectsScreen::class)->html();
        $this->assertStringContainsString('werkt', $rules);
        $this->assertStringNotContainsString('gevangen', $rules);

        $worklist = Livewire::actingAs($this->superuser())->test(NotFoundsScreen::class)->html();
        $this->assertStringContainsString('gevangen', $worklist);
        $this->assertStringNotContainsString('werkt', $worklist);
    }
}
