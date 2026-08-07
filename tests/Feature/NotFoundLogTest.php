<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use NickDeKruijk\Leap\Classes\NotFoundLog;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * What a missing page writes down, and about whom.
 *
 * Everything here is off unless a project asks for it, and asking for the IP gives an
 * anonymized one unless that is switched off too. Those are the defaults a site inherits
 * without reading the config, so they are the ones worth pinning: a template that shipped
 * a Log::warning with the visitor's IP and user agent in it is exactly how this goes
 * wrong, and nobody notices until someone asks what is in the logs.
 */
class NotFoundLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['leap.not_found_log.enabled' => true]);
    }

    /**
     * @return array<int, Log>
     */
    private function capture(callable $work): array
    {
        $written = [];

        Log::listen(function ($message) use (&$written): void {
            $written[] = $message;
        });

        $work();

        return $written;
    }

    private function request(string $path = '/gone', array $headers = []): Request
    {
        return Request::create('https://example.com'.$path, 'GET', [], [], [], array_combine(
            array_map(fn (string $key): string => 'HTTP_'.strtoupper(str_replace('-', '_', $key)), array_keys($headers)),
            array_values($headers),
        ) + ['REMOTE_ADDR' => '198.51.100.42']);
    }

    public function test_it_says_nothing_at_all_unless_a_project_asks(): void
    {
        config(['leap.not_found_log.enabled' => false]);

        $written = $this->capture(fn () => NotFoundLog::record($this->request()));

        $this->assertSame([], $written);
        $this->assertFalse(NotFoundLog::enabled());
    }

    public function test_it_writes_the_path_and_the_referer_that_led_there(): void
    {
        $written = $this->capture(fn () => NotFoundLog::record(
            $this->request('/oude-pagina', ['referer' => 'https://elders.example/artikel'])
        ));

        $this->assertCount(1, $written);
        $this->assertSame('404 /oude-pagina', $written[0]->message);
        $this->assertSame('https://elders.example/artikel', $written[0]->context['referer']);
        $this->assertSame('info', $written[0]->level);
    }

    /**
     * A bare path cannot tell a visitor who followed a dead link from a machine working
     * through a wordlist, and that answer decides whether there is anything to fix. The
     * anonymized address and the user agent are what tell them apart, so both are on.
     */
    public function test_it_records_enough_to_tell_a_bot_from_a_visitor(): void
    {
        $written = $this->capture(fn () => NotFoundLog::record(
            $this->request('/gone', ['user-agent' => 'Mozilla/5.0 (compatible; SomeBot/2.1)'])
        ));

        $this->assertSame('198.51.100.xxx', $written[0]->context['ip']);
        $this->assertSame('Mozilla/5.0 (compatible; SomeBot/2.1)', $written[0]->context['user_agent']);
    }

    /**
     * Which network, never which person. The whole address takes saying so.
     */
    public function test_the_address_is_anonymized_without_being_asked(): void
    {
        $written = $this->capture(fn () => NotFoundLog::record($this->request()));

        $this->assertSame('198.51.100.xxx', $written[0]->context['ip']);
        $this->assertStringNotContainsString('198.51.100.42', json_encode($written[0]->context));
    }

    public function test_either_can_be_left_out(): void
    {
        config([
            'leap.not_found_log.ip_address' => false,
            'leap.not_found_log.user_agent' => false,
        ]);

        $written = $this->capture(fn () => NotFoundLog::record(
            $this->request('/gone', ['user-agent' => 'EenHeelHerkenbareBrowser/1.0'])
        ));

        $this->assertArrayNotHasKey('ip', $written[0]->context);
        $this->assertArrayNotHasKey('user_agent', $written[0]->context);
    }

    public function test_the_whole_address_takes_switching_anonymizing_off(): void
    {
        config([
            'leap.not_found_log.ip_address' => true,
            'leap.not_found_log.ip_address_anonymized' => false,
        ]);

        $written = $this->capture(fn () => NotFoundLog::record($this->request()));

        $this->assertSame('198.51.100.42', $written[0]->context['ip']);
    }

    /**
     * They are two switches, not one: a site that wants to know which networks are
     * knocking without keeping browser strings can have exactly that.
     */
    public function test_the_two_are_separate_switches(): void
    {
        config(['leap.not_found_log.user_agent' => false]);

        $written = $this->capture(fn () => NotFoundLog::record(
            $this->request('/gone', ['user-agent' => 'Mozilla/5.0 (compatible; SomeBot/2.1)'])
        ));

        $this->assertSame('198.51.100.xxx', $written[0]->context['ip']);
        $this->assertArrayNotHasKey('user_agent', $written[0]->context);
    }

    /**
     * Most 404s are a scanner working through a wordlist. One line per guess is a log
     * nobody can read, on a disk that fills at a rate a stranger chooses.
     */
    public function test_the_same_path_is_written_once_a_window(): void
    {
        $written = $this->capture(function (): void {
            foreach (range(1, 5) as $ignored) {
                NotFoundLog::record($this->request('/wp-admin'));
            }
        });

        $this->assertCount(1, $written);
    }

    public function test_two_different_paths_are_two_different_things_to_fix(): void
    {
        $written = $this->capture(function (): void {
            NotFoundLog::record($this->request('/eerste'));
            NotFoundLog::record($this->request('/tweede'));
        });

        $this->assertCount(2, $written);
    }

    public function test_a_throttle_of_zero_writes_every_time(): void
    {
        config(['leap.not_found_log.throttle_minutes' => 0]);

        $written = $this->capture(function (): void {
            NotFoundLog::record($this->request('/gone'));
            NotFoundLog::record($this->request('/gone'));
        });

        $this->assertCount(2, $written);
    }

    /**
     * The query string is usually part of the answer: "?page=3" says which page of a
     * listing carried the dead link, "?utm_source=..." says the newsletter did. And it is
     * nearly always one of your own URLs — browsers have defaulted to
     * strict-origin-when-cross-origin for years, so a referer from elsewhere arrives as a
     * bare origin with nothing after it.
     */
    public function test_a_referer_keeps_its_query_string(): void
    {
        $written = $this->capture(fn () => NotFoundLog::record(
            $this->request('/gone', ['referer' => 'https://example.com/blog?page=3&utm_source=nieuwsbrief'])
        ));

        $this->assertSame('https://example.com/blog?page=3&utm_source=nieuwsbrief', $written[0]->context['referer']);
    }

    /**
     * For a site whose own URLs carry something it would rather not have in a log.
     */
    public function test_the_query_string_can_be_dropped(): void
    {
        config(['leap.not_found_log.referer_query_string' => false]);

        $written = $this->capture(fn () => NotFoundLog::record(
            $this->request('/gone', ['referer' => 'https://example.com/zoek?email=iemand@example.com'])
        ));

        $this->assertSame('https://example.com/zoek', $written[0]->context['referer']);
    }

    public function test_the_referer_can_be_left_out_altogether(): void
    {
        config(['leap.not_found_log.referer' => false]);

        $written = $this->capture(fn () => NotFoundLog::record(
            $this->request('/gone', ['referer' => 'https://elders.example/artikel'])
        ));

        $this->assertCount(1, $written);
        $this->assertArrayNotHasKey('referer', $written[0]->context);
    }

    /**
     * A site that wants these somewhere other than the middle of everything else.
     *
     * Asserted against the file rather than the MessageLogged event: that event carries
     * the level, message and context but not the channel, so listening to it would pass
     * just as happily when the line went to the default log.
     */
    public function test_it_can_be_sent_to_a_channel_of_its_own(): void
    {
        $ours = tempnam(sys_get_temp_dir(), 'notfound').'.log';
        $default = tempnam(sys_get_temp_dir(), 'default').'.log';

        config([
            'logging.channels.notfound' => ['driver' => 'single', 'path' => $ours],
            'logging.channels.single' => ['driver' => 'single', 'path' => $default],
            'logging.default' => 'single',
            'leap.not_found_log.channel' => 'notfound',
        ]);

        NotFoundLog::record($this->request('/gone'));

        // @ on both: a channel that was ignored leaves its file uncreated, and a missing
        // file should read as "the line is not there" rather than as an ErrorException
        // from the assertion itself.
        $written = (string) @file_get_contents($ours);
        $elsewhere = (string) @file_get_contents($default);

        @unlink($ours);
        @unlink($default);

        $this->assertStringContainsString('404 /gone', $written, 'The named channel got nothing.');
        $this->assertSame('', trim($elsewhere), 'It went to the default channel as well.');
    }

    /**
     * The referer is written by whoever made the request, so it goes in trimmed rather
     * than trusted into the log at whatever length they chose.
     */
    public function test_a_referer_cannot_fill_a_line_on_its_own(): void
    {
        $written = $this->capture(fn () => NotFoundLog::record(
            $this->request('/gone', ['referer' => 'https://example.com/'.str_repeat('a', 500)])
        ));

        $referer = $written[0]->context['referer'];

        // 200 of the address plus the marker Str::limit appends, which is worth keeping:
        // it is what tells a reader the line was cut rather than the link being short.
        $this->assertSame(203, strlen($referer));
        $this->assertStringEndsWith('...', $referer);
    }

    /**
     * The class doing the right thing proves nothing if it is never called.
     *
     * The wiring is the part that took the reading: a report() callback never sees a 404,
     * because Symfony's HttpException is on Laravel's internal do-not-report list, and
     * taking it off there to reach one would hand every 403 and abort() to Sentry as
     * well. So it hangs off render() and returns null, leaving the error page alone.
     */
    public function test_a_real_missing_page_reaches_the_log(): void
    {
        $written = $this->capture(function (): void {
            $this->get('/deze-route-bestaat-niet')
                ->assertNotFound();
        });

        $lines = array_values(array_filter($written, fn ($m): bool => str_starts_with($m->message, '404 ')));

        $this->assertCount(1, $lines);
        $this->assertSame('404 /deze-route-bestaat-niet', $lines[0]->message);
    }

    public function test_it_anonymizes_both_kinds_of_address(): void
    {
        $this->assertSame('192.168.1.xxx', Leap::anonymizeIp('192.168.1.42'));
        $this->assertSame('2001:db8:0:0:0:0:xxxx:xxxx', Leap::anonymizeIp('2001:db8:0:0:0:0:1234:5678'));
        $this->assertNull(Leap::anonymizeIp(null));
    }
}
