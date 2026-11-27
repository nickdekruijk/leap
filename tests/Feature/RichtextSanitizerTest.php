<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\RecordDraft;
use NickDeKruijk\Leap\Classes\RichtextSanitizer;
use NickDeKruijk\Leap\Tests\Fixtures\SectionsModel;
use NickDeKruijk\Leap\Tests\Fixtures\SectionsResource;
use NickDeKruijk\Leap\Tests\TestCase;
use RuntimeException;

/**
 * Rich text filtered on the server, where it is stored.
 *
 * TinyMCE cleans what is typed into it, in the browser. A request sent straight to
 * the panel skips that, and the site prints rich text as it is stored, on the same
 * origin as the panel. With leap.tinymce.sanitize on the value is filtered before
 * it reaches the model. Off, which is the default, nothing changes at all.
 */
class RichtextSanitizerTest extends TestCase
{
    /** What TinyMCE writes with leap's default options: none of it may go. */
    private const TINYMCE = '<h2 class="k" style="text-align: center;">Kop &amp; meer</h2>'
        .'<p>Tekst&nbsp;met <a class="button" href="/contact?a=1&amp;b=2" target="_blank" rel="noopener">een link</a><br>'
        .'<img src="/storage/foto.png" alt="" width="320" height="200"></p>'
        .'<table style="border-collapse: collapse; width: 100%;" border="1"><colgroup><col style="width: 50%;"></colgroup><tbody><tr><td>1</td></tr></tbody></table>'
        .'<details class="mce-accordion" open="open"><summary>Vraag</summary><p>Antwoord</p></details>'
        .'<ul><li>a</li></ul><blockquote><p>q</p></blockquote><p><a id="anker"></a><a href="#anker">naar anker</a> <a href="mailto:info@example.com">mail</a></p>'
        .'<p><iframe title="YouTube" src="https://www.youtube.com/embed/abc" width="560" height="314" allowfullscreen="allowfullscreen"></iframe></p>'
        .'<video controls="controls" width="300" height="150"><source src="/v.mp4" type="video/mp4"></video>';

    private const EVIL = '<p onclick="x()">a<img src="x" onerror="alert(1)"><a href="javascript:alert(1)">j</a></p>'
        .'<script>alert(1)</script><iframe src="https://evil.example/"></iframe>';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('leap.default_modules', [SectionsResource::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sections_models', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->json('sections')->nullable();
            $table->timestamps();
        });
    }

    /**
     * The filter reads HTML with PHP's own HTML5 parser, which PHP 8.3 does not have.
     */
    private function needsTheFilter(): void
    {
        if (RichtextSanitizer::unavailable() !== null) {
            $this->markTestSkipped(RichtextSanitizer::unavailable());
        }
    }

    /**
     * Save $data the way the editor does, on a model with a rich-text title and
     * the sections of the fixture.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> The form as apply() left it
     */
    private function apply(SectionsModel $model, array $data): array
    {
        $attributes = collect((new SectionsResource)->attributes())
            ->where('indexOnly', false)
            ->reject(fn (Attribute $attribute) => $attribute->name === 'title')
            ->push(Attribute::make('title')->richtext());

        RecordDraft::apply($model, $attributes->only(array_keys($attributes->filter(fn (Attribute $a) => array_key_exists($a->name, $data))->all())), $data);

        return $data;
    }

    public function test_off_a_script_is_stored_as_it_was_sent(): void
    {
        $this->assertFalse(config('leap.tinymce.sanitize'));

        $model = new SectionsModel;
        $data = $this->apply($model, [
            'title' => ['nl' => self::EVIL],
            'sections' => [['_name' => 'block', 'body' => ['nl' => self::EVIL]]],
        ]);

        $this->assertSame(self::EVIL, $data['title']['nl']);
        $this->assertSame(self::EVIL, $model->getTranslation('title', 'nl'));
        $this->assertSame(self::EVIL, $data['sections'][0]['body']['nl']);
    }

    public function test_on_what_tinymce_writes_is_stored_byte_for_byte(): void
    {
        $this->needsTheFilter();
        config(['leap.tinymce.sanitize' => true]);

        $this->assertSame([], app(RichtextSanitizer::class)->dropped(self::TINYMCE));
        $this->assertSame(self::TINYMCE, app(RichtextSanitizer::class)->filter(self::TINYMCE));

        $model = new SectionsModel;
        $data = $this->apply($model, ['title' => ['nl' => self::TINYMCE]]);

        $this->assertSame(self::TINYMCE, $data['title']['nl']);
        $this->assertSame(self::TINYMCE, $model->getTranslation('title', 'nl'));
    }

    public function test_on_a_script_a_handler_and_a_javascript_link_go(): void
    {
        $this->needsTheFilter();
        config(['leap.tinymce.sanitize' => true]);

        $model = new SectionsModel;
        $data = $this->apply($model, ['title' => ['nl' => self::EVIL, 'en' => self::TINYMCE]]);

        $stored = $model->getTranslation('title', 'nl');
        $this->assertSame('<p>a<img src="x" /><a>j</a></p>', $stored);
        // The form holds what was stored, so the editor shows it too.
        $this->assertSame($stored, $data['title']['nl']);
        // One locale losing something is no reason to rewrite the other.
        $this->assertSame(self::TINYMCE, $data['title']['en']);
    }

    public function test_on_rich_text_in_a_section_is_filtered_per_locale(): void
    {
        $this->needsTheFilter();
        config(['leap.tinymce.sanitize' => true]);

        $model = new SectionsModel;
        $data = $this->apply($model, ['sections' => [
            ['_name' => 'block', 'head' => ['nl' => '<script>x</script>'], 'body' => ['nl' => self::EVIL, 'en' => self::TINYMCE]],
            ['_name' => 'gone', 'body' => self::EVIL],
        ]]);

        $this->assertSame('<p>a<img src="x" /><a>j</a></p>', $data['sections'][0]['body']['nl']);
        $this->assertSame(self::TINYMCE, $data['sections'][0]['body']['en']);
        // Not a rich-text field: escaped where it is shown, and not this filter's to touch.
        $this->assertSame('<script>x</script>', $data['sections'][0]['head']['nl']);
        // A section the module no longer defines has no fields to tell rich text by.
        $this->assertSame(self::EVIL, $data['sections'][1]['body']);
        $this->assertSame($data['sections'][0]['body'], $model->sections[0]['body']);
    }

    public function test_the_report_says_what_goes_and_how_often(): void
    {
        $this->needsTheFilter();

        $this->assertSame(
            ['a[href]' => 1, 'iframe' => 1, 'iframe[src]' => 1, 'img[onerror]' => 1, 'p[onclick]' => 1, 'script' => 1],
            app(RichtextSanitizer::class)->dropped(self::EVIL),
        );
    }

    /**
     * A browser with scripting on reads the content of noscript as text, and ends
     * it at the first closing tag, wherever that is. The filter must not keep a
     * value because its own parser read an element where the browser reads none.
     */
    public function test_what_a_browser_would_read_differently_is_not_left_as_it_is(): void
    {
        $this->needsTheFilter();
        $sanitizer = app(RichtextSanitizer::class);

        foreach ([
            '<noscript><p title="</noscript><img src=x onerror=alert(1)>">x</p></noscript>',
            '<template><img src=x onerror=alert(1)></template>',
            '<svg><a xlink:href="javascript:alert(1)"><text>t</text></a></svg>',
            '<math><mtext><img src=x onerror=alert(1)></mtext></math>',
            '<form action="/x"><input name="a"><button formaction="javascript:alert(1)">b</button></form>',
            '<iframe srcdoc="&lt;script&gt;alert(1)&lt;/script&gt;"></iframe>',
            '<iframe src="/storage/upload.html"></iframe>',
            '<base href="//evil.example/"><p>a</p>',
        ] as $html) {
            $filtered = $sanitizer->filter($html);

            $this->assertNotSame($html, $filtered, $html);
            $this->assertStringNotContainsStringIgnoringCase('onerror', $filtered, $html);
            $this->assertStringNotContainsStringIgnoringCase('javascript:', $filtered, $html);
            $this->assertStringNotContainsString('<iframe', $filtered, $html);
            // Filtering what was filtered leaves it alone: it settles.
            $this->assertSame($filtered, $sanitizer->filter($filtered), $html);
        }
    }

    /**
     * A browser ignores the space around an address and follows these links today.
     * The sanitizer's own check refuses each of them, which left a link without a
     * href the first time its text was filtered.
     */
    public function test_a_link_keeps_its_address_when_there_is_whitespace_around_it(): void
    {
        $this->needsTheFilter();
        $sanitizer = app(RichtextSanitizer::class);

        foreach ([
            '<a href="http://www.nvdec.org " target="_blank" rel="noopener">x</a>' => '<a href="http://www.nvdec.org" target="_blank" rel="noopener">x</a>',
            '<a href=" https://x.nl/ ">x</a>' => '<a href="https://x.nl/">x</a>',
            '<a href="mailto: info@x.nl">x</a>' => '<a href="mailto:info&#64;x.nl">x</a>',
            "<a href=\"/storage/files/a\u{00A0}b.pdf\">x</a>" => '<a href="/storage/files/a%C2%A0b.pdf">x</a>',
            '<img src=" /storage/foto 1.png " alt="a">' => '<img src="/storage/foto%201.png" alt="a" />',
            '<a href="mailto:x@y.nl?subject=Hallo daar">x</a>' => '<a href="mailto:x&#64;y.nl?subject&#61;Hallo%20daar">x</a>',
            '<a href="tel:+31 20 123 45 67">x</a>' => '<a href="tel:&#43;31%2020%20123%2045%2067">x</a>',
        ] as $html => $clean) {
            $this->assertSame($clean, $sanitizer->clean($html), $html);
            $this->assertSame([], $sanitizer->dropped($html), $html);
        }
    }

    /**
     * A file name with a space in it is everywhere on an older site. The filter
     * writes the space as %20, which is the same address, so nothing was removed:
     * the value stays as it is and the report does not name it.
     */
    public function test_an_address_the_filter_only_encodes_is_stored_byte_for_byte(): void
    {
        $this->needsTheFilter();
        $sanitizer = app(RichtextSanitizer::class);

        foreach ([
            '<p><a href="/storage/files/Reglement DEC Utrecht - 30 mei 2015.pdf">x</a></p>',
            '<p><a href="/storage/files/Reglement DEC Utrecht - 30 mei 2015.pdf" target="_blank" rel="noopener">x</a></p>',
            '<p><a href="https://x.nl/a b?c=d e#f g">x</a> <img src="/storage/foto 1.png" alt=""></p>',
            // What a browser reads the same way: the space around it, a no-break space in a path
            '<p><a href="http://www.nvdec.org " target="_blank" rel="noopener">x</a></p>',
            "<p><a href=\"/storage/files/a\u{00A0}b.pdf\">x</a></p>",
            '<p><a href="tel:+31 20 123 45 67">bel</a> <a href="mailto:x@y.nl?subject=Hallo daar">mail</a></p>',
        ] as $html) {
            $this->assertSame(['dropped' => [], 'rewritten' => []], $sanitizer->changes($html), $html);
            $this->assertSame($html, $sanitizer->filter($html), $html);
        }

        $model = new SectionsModel;
        config(['leap.tinymce.sanitize' => true]);
        $html = '<p><a href="/storage/files/Reglement DEC Utrecht - 30 mei 2015.pdf" target="_blank" rel="noopener">x</a></p>';
        $data = $this->apply($model, ['title' => ['nl' => $html]]);

        $this->assertSame($html, $data['title']['nl']);
        $this->assertSame($html, $model->getTranslation('title', 'nl'));
    }

    /**
     * An address that stays but is written differently than a browser reads it is
     * neither untouched nor gone, and the report says which of the two it is.
     */
    public function test_an_address_that_is_repaired_counts_as_rewritten_not_as_removed(): void
    {
        $this->needsTheFilter();
        $sanitizer = app(RichtextSanitizer::class);

        foreach (['<a href="mailto: info@x.nl">x</a>', '<a href="HTTP://X.NL/Pad">x</a>'] as $html) {
            $this->assertSame(['dropped' => [], 'rewritten' => ['a[href]' => 1]], $sanitizer->changes($html), $html);
            $this->assertNotSame($html, $sanitizer->filter($html), $html);
        }

        // One link gone and one repaired, of the same kind, are told apart.
        $this->assertSame(
            ['dropped' => ['a[href]' => 1], 'rewritten' => ['a[href]' => 1]],
            $sanitizer->changes('<a href="javascript:alert(1)">j</a><a href="mailto: info@x.nl">m</a>'),
        );
    }

    /**
     * Reading an address the way a browser does must not let through what a
     * browser would run, nor decide anything about the scheme or the host.
     */
    public function test_whitespace_does_not_carry_a_script_or_another_host_past_the_filter(): void
    {
        $this->needsTheFilter();
        $sanitizer = app(RichtextSanitizer::class);

        foreach ([
            '<a href=" javascript:alert(1)">x</a>',
            "<a href=\"\x01javascript:alert(1)\">x</a>",
            "<a href=\"java\tscript:alert(1)\">x</a>",
            "<a href=\"\n javascript:alert(1) \">x</a>",
            "<a href=\"\u{00A0}javascript:alert(1)\">x</a>",
            '<a href="javascript :alert(1)">x</a>',
            '<a href=" data:text/html,<script>alert(1)</script>">x</a>',
            "<a href=\"http://evil\u{00A0}.example/\">x</a>",
            '<a href="http://evil%C2%A0.example/a">x</a>',
            '<a href="http://x.nl /a">x</a>',
            '<img src=" javascript:alert(1)" alt="">',
        ] as $html) {
            $this->assertArrayHasKey(str_starts_with($html, '<img') ? 'img[src]' : 'a[href]', $sanitizer->dropped($html), $html);
            $this->assertStringNotContainsString('href', $sanitizer->filter($html), $html);
            $this->assertStringNotContainsString('src=', $sanitizer->filter($html), $html);
        }

        // An iframe is still judged by the host a browser would open.
        $this->assertSame('', $sanitizer->filter('<iframe src=" https://evil.example/ "></iframe>'));
        $padded = '<iframe src=" https://www.youtube.com/embed/abc "></iframe>';
        $this->assertSame($padded, $sanitizer->filter($padded));
        $this->assertSame('<iframe src="https://www.youtube.com/embed/abc"></iframe>', $sanitizer->clean($padded));
    }

    public function test_an_iframe_keeps_its_src_only_for_a_host_on_the_list(): void
    {
        $this->needsTheFilter();
        $sanitizer = app(RichtextSanitizer::class);

        $vimeo = '<iframe src="https://player.vimeo.com/video/1" width="640" height="360"></iframe>';
        $this->assertSame($vimeo, $sanitizer->filter($vimeo));

        $own = '<iframe src="https://maps.example.com/embed?x=1"></iframe>';
        $this->assertSame('', $sanitizer->filter($own));

        config(['leap.tinymce.sanitize_iframe_hosts' => ['maps.example.com']]);
        $this->assertSame($own, (new RichtextSanitizer)->filter($own));
        $this->assertSame('', (new RichtextSanitizer)->filter($vimeo));
    }

    public function test_a_site_allows_more_than_tinymce_writes_by_default(): void
    {
        $this->needsTheFilter();

        $html = '<p data-aos="fade">x</p><video autoplay="autoplay" src="/v.mp4"></video><button type="button">b</button>';
        $this->assertSame(['button' => 1, 'button[type]' => 1, 'p[data-aos]' => 1, 'video[autoplay]' => 1], app(RichtextSanitizer::class)->dropped($html));

        config(['leap.tinymce.sanitize_allow' => ['*' => ['data-aos'], 'video' => ['autoplay'], 'button' => ['type']]]);

        $sanitizer = new RichtextSanitizer;
        $this->assertSame($html, $sanitizer->filter($html));
        // Allowing an attribute on video did not take its other attributes away.
        $this->assertSame([], $sanitizer->dropped('<video controls="controls" src="/v.mp4" width="300"></video>'));
    }

    /**
     * The sanitizer stops reading at 20,000 bytes unless told otherwise, and a long
     * page would lose its end the first time it lost anything.
     */
    public function test_a_long_text_is_not_cut_short(): void
    {
        $this->needsTheFilter();

        $long = '<p>'.str_repeat('woord ', 8000).'</p>';
        $this->assertGreaterThan(40000, strlen($long));

        $this->assertSame($long, app(RichtextSanitizer::class)->filter($long));
        $this->assertStringEndsWith('woord </p>', app(RichtextSanitizer::class)->filter('<script>x</script>'.$long));
    }

    public function test_an_html_comment_is_no_reason_to_rewrite_a_value(): void
    {
        $this->needsTheFilter();

        $html = '<p>a &amp; b</p><!-- more --><p>c</p>';

        $this->assertSame($html, app(RichtextSanitizer::class)->filter($html));
    }

    public function test_the_command_lists_what_would_go_and_writes_nothing(): void
    {
        $this->needsTheFilter();

        SectionsModel::create(['title' => 'Schoon', 'sections' => [['_name' => 'block', 'body' => ['nl' => self::TINYMCE]]]]);
        SectionsModel::create(['title' => 'Vuil', 'sections' => [
            ['_name' => 'block', 'body' => ['nl' => self::EVIL, 'en' => '<p>ok</p>']],
            ['_name' => 'block', 'body' => '<script>x</script>'],
            ['_name' => 'gone', 'body' => self::EVIL],
        ]]);
        $before = DB::table('sections_models')->get()->toJson();

        $this->assertSame(0, Artisan::call('leap:sanitize'));
        $output = Artisan::output();

        $this->assertMatchesRegularExpression('/script\s+\|\s+2\s+\|\s+SectionsResource 2/', $output);
        $this->assertMatchesRegularExpression('/img\[onerror\]\s+\|\s+1\s+\|\s+SectionsResource 2/', $output);
        $this->assertStringContainsString('2 of 4 rich-text values in 2 records would change', $output);
        $this->assertStringContainsString('1 section has no definition in the module any more', $output);
        $this->assertStringContainsString('The filter is off', $output);
        $this->assertSame($before, DB::table('sections_models')->get()->toJson());
    }

    public function test_the_command_does_not_name_a_record_for_a_space_in_a_path(): void
    {
        $this->needsTheFilter();

        SectionsModel::create(['title' => 'Reglement', 'sections' => [
            ['_name' => 'block', 'body' => ['nl' => '<p><a href="/storage/files/Reglement DEC Utrecht - 30 mei 2015.pdf" target="_blank" rel="noopener">x</a> <a href="http://www.nvdec.org ">y</a></p>']],
        ]]);

        $this->assertSame(0, Artisan::call('leap:sanitize'));
        $output = Artisan::output();

        $this->assertStringContainsString('Nothing to remove. 1 rich-text value in 1 record', $output);
        $this->assertStringNotContainsString('a[href]', $output);
    }

    public function test_the_command_lists_what_is_rewritten_apart_from_what_goes(): void
    {
        $this->needsTheFilter();

        SectionsModel::create(['title' => 'Contact', 'sections' => [
            ['_name' => 'block', 'body' => ['nl' => '<p><a href="mailto: info@x.nl">mail</a></p>']],
            ['_name' => 'block', 'body' => ['nl' => '<p><a href="javascript:alert(1)">j</a></p>']],
        ]]);

        Artisan::call('leap:sanitize');
        $output = Artisan::output();

        $this->assertMatchesRegularExpression('/Would go\s+\|.*?a\[href\]\s+\|\s+1\s+\|\s+SectionsResource 1/s', $output);
        $this->assertMatchesRegularExpression('/Would stay, rewritten\s+\|.*?a\[href\]\s+\|\s+1\s+\|\s+SectionsResource 1/s', $output);
        $this->assertStringContainsString('2 of 2 rich-text values in 1 record would change', $output);
    }

    public function test_the_command_prints_one_record_before_and_after(): void
    {
        $this->needsTheFilter();

        SectionsModel::create(['title' => 'Vuil', 'sections' => [['_name' => 'block', 'body' => ['nl' => self::EVIL]]]]);

        $this->assertSame(0, Artisan::call('leap:sanitize', ['--show' => 'SectionsResource:1']));
        $output = Artisan::output();

        $this->assertStringContainsString('SectionsResource 1, sections 0 body (nl)', $output);
        $this->assertStringContainsString(self::EVIL, $output);
        $this->assertStringContainsString('<p>a<img src="x" /><a>j</a></p>', $output);

        $this->assertSame(2, Artisan::call('leap:sanitize', ['--show' => 'nonsense']));
    }

    public function test_the_command_says_so_when_there_is_nothing_to_remove(): void
    {
        $this->needsTheFilter();

        SectionsModel::create(['title' => 'Schoon', 'sections' => [['_name' => 'block', 'body' => ['nl' => self::TINYMCE]]]]);

        Artisan::call('leap:sanitize');

        $this->assertStringContainsString('Nothing to remove. 1 rich-text value in 1 record', Artisan::output());
    }

    /**
     * On PHP 8.3 the filter cannot run. Turning it on there must fail loudly on the
     * first save, not store unfiltered what the setting says is filtered.
     */
    public function test_where_the_filter_cannot_run_saving_fails_instead_of_storing_unfiltered(): void
    {
        if (RichtextSanitizer::unavailable() === null) {
            $this->markTestSkipped('The filter can run on this PHP.');
        }
        config(['leap.tinymce.sanitize' => true]);

        $this->assertStringContainsString('PHP 8.4', (string) RichtextSanitizer::unavailable());
        $this->assertSame(1, Artisan::call('leap:sanitize'));

        $this->expectException(RuntimeException::class);
        $this->apply(new SectionsModel, ['title' => ['nl' => self::EVIL]]);
    }
}
