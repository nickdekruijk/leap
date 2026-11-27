<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Dom\HTMLDocument;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\RecordDraft;
use NickDeKruijk\Leap\Classes\RichtextSanitizer;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\Article;
use NickDeKruijk\Leap\Tests\Fixtures\RichtextImportResource;
use NickDeKruijk\Leap\Tests\Fixtures\SectionsModel;
use NickDeKruijk\Leap\Tests\Fixtures\SectionsResource;
use NickDeKruijk\Leap\Tests\Fixtures\User;
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

    /**
     * What is stored is always what the filter wrote, also when nothing had to go.
     * It says the same as what came in, and filtering it again leaves it alone, so
     * a record is rewritten once and not with every save.
     */
    public function test_on_what_tinymce_writes_loses_nothing_and_settles(): void
    {
        $this->needsTheFilter();
        config(['leap.tinymce.sanitize' => true]);
        $sanitizer = app(RichtextSanitizer::class);

        $this->assertSame(['dropped' => [], 'rewritten' => [], 'unclosed' => false], $sanitizer->changes(self::TINYMCE));

        $stored = $sanitizer->filter(self::TINYMCE);
        $this->assertSame($sanitizer->clean(self::TINYMCE), $stored);
        $this->assertSame($stored, $sanitizer->filter($stored));
        $this->assertSame(['dropped' => [], 'rewritten' => [], 'unclosed' => false], $sanitizer->changes($stored));

        $model = new SectionsModel;
        $data = $this->apply($model, ['title' => ['nl' => self::TINYMCE]]);

        $this->assertSame($stored, $model->getTranslation('title', 'nl'));
        // The form holds what was stored.
        $this->assertSame($stored, $data['title']['nl']);
    }

    public function test_on_a_script_a_handler_and_a_javascript_link_go(): void
    {
        $this->needsTheFilter();
        config(['leap.tinymce.sanitize' => true]);

        $model = new SectionsModel;
        $data = $this->apply($model, ['title' => ['nl' => self::EVIL, 'en' => '<p>ok</p>']]);

        $stored = $model->getTranslation('title', 'nl');
        $this->assertSame('<p>a<img src="x" /><a>j</a></p>', $stored);
        $this->assertSame($stored, $data['title']['nl']);
        $this->assertSame('<p>ok</p>', $data['title']['en']);
    }

    public function test_on_rich_text_in_a_section_is_filtered_per_locale(): void
    {
        $this->needsTheFilter();
        config(['leap.tinymce.sanitize' => true]);

        $model = new SectionsModel;
        $data = $this->apply($model, ['sections' => [
            ['_name' => 'block', 'head' => ['nl' => '<script>x</script>'], 'body' => ['nl' => self::EVIL, 'en' => '<p>ok</p>']],
            ['_name' => 'gone', 'body' => self::EVIL],
        ]]);

        $this->assertSame('<p>a<img src="x" /><a>j</a></p>', $data['sections'][0]['body']['nl']);
        $this->assertSame('<p>ok</p>', $data['sections'][0]['body']['en']);
        // Not a rich-text field: escaped where it is shown, and not this filter's to touch.
        $this->assertSame('<script>x</script>', $data['sections'][0]['head']['nl']);
        // A section the module no longer defines has no fields to tell rich text by.
        $this->assertSame(self::EVIL, $data['sections'][1]['body']);
        $this->assertSame($data['sections'][0]['body'], $model->sections[0]['body']);
    }

    /**
     * Each of these is read differently on a page than by a parser that gets the
     * value on its own. The attributes of a stray body tag land on the real body.
     * A tag or a comment left open at the end is finished by the markup after it.
     * A table cell outside a table is set aside, and is a cell again once the value
     * is printed inside one. None of it is in the tree, so a filter that kept a
     * value in which it found nothing to remove kept every one of these.
     */
    public function test_what_a_parser_sets_aside_is_never_stored(): void
    {
        $this->needsTheFilter();
        config(['leap.tinymce.sanitize' => true]);
        $sanitizer = app(RichtextSanitizer::class);

        foreach ([
            '<p>hi</p><body onload="alert(1)">',
            '<p>hi</p><html onclick="alert(1)">',
            '<p>hi</p></td><td onmouseover="alert(1)">',
            '<p>hi</p><tr onclick="alert(1)">',
            '<p>hi</p><caption onclick="alert(1)">',
            '<p>hi</p><img src=x onerror=alert(1) x="',
            '<p>hi</p><script src=//evil.example/x.js x="',
            '<p>hi</p><a href="javascript:alert(1)',
            '<p>hi</p><!--',
            '<p>hi</p><xmp>',
            '<p>hi</p></div></div>',
        ] as $html) {
            $this->assertSame('<p>hi</p>', $sanitizer->filter($html), $html);

            $model = new SectionsModel;
            $this->apply($model, ['title' => ['nl' => $html]]);
            $this->assertSame('<p>hi</p>', $model->getTranslation('title', 'nl'), $html);
        }
    }

    public function test_the_report_names_what_a_stray_body_tag_carries_and_what_is_left_open(): void
    {
        $this->needsTheFilter();
        $sanitizer = app(RichtextSanitizer::class);

        $this->assertSame(['body[onload]' => 1], $sanitizer->dropped('<p>hi</p><body onload="alert(1)">'));
        $this->assertSame(['html[onclick]' => 1], $sanitizer->dropped('<p>hi</p><html onclick="alert(1)">'));

        foreach (['<p>hi</p><img src=x onerror=alert(1) x="', '<p>hi</p><!--', '<p>hi</p><a href="javascript:alert(1)', '<div>open'] as $html) {
            $this->assertTrue($sanitizer->changes($html)['unclosed'], $html);
        }

        // A paragraph left open is closed by whatever follows it, on a page too.
        foreach (['<p>hi</p>', '<p>open alinea', '<ul><li>a</li><li>b</ul>', ''] as $html) {
            $this->assertFalse($sanitizer->changes($html)['unclosed'], $html);
        }
    }

    public function test_the_report_says_what_goes_and_how_often(): void
    {
        $this->needsTheFilter();

        $this->assertSame(
            ['a[href]' => 1, 'iframe' => 1, 'iframe[src]' => 1, 'img[onerror]' => 1, 'p[onclick]' => 1, 'script' => 1],
            app(RichtextSanitizer::class)->dropped(self::EVIL),
        );
    }

    public function test_what_a_browser_would_read_differently_does_not_pass(): void
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
        }
    }

    /**
     * The first save rewrites a value, and the second must find nothing to do. An
     * element whose content is text that a browser does not decode broke that: the
     * filter escaped what was in an iframe, and escaped those entities again with
     * every save after it.
     */
    public function test_filtering_what_was_filtered_changes_nothing(): void
    {
        $this->needsTheFilter();
        $sanitizer = app(RichtextSanitizer::class);

        foreach ([
            self::TINYMCE,
            self::EVIL,
            '<p>a &amp; b &lt; c "d" \'e\' &nbsp;f é € 😀</p><p><a href="/a b.pdf?x=1&amp;y=2">x</a><br></p>',
            '<p>hi</p><iframe src="https://www.youtube.com/embed/a">tekst <b>x</b></iframe><p>na</p>',
            '<p>hi</p><iframe src="https://www.youtube.com/embed/a">',
            '<xmp><b>x</b></xmp><p>na</p>',
            '<pre><code>&lt;?php echo 1;</code></pre>',
            '<p>hi</p><img src=x onerror=alert(1) x="',
            '<p>Mail info@x.nl, 1 + 1 = 2, "a" \'b\' `c` &amp;#64;</p><p><a href="mailto:info@x.nl?subject=a+b" title="a=b @ `c`">m</a></p>',
            '<a href="/x" class="a&quot; onmouseover=&quot;alert(1)">x</a>',
        ] as $html) {
            $once = $sanitizer->filter($html);

            $this->assertSame($once, $sanitizer->filter($once), $html);
        }

        // What is inside an iframe goes, and with it the reason it kept growing.
        $this->assertSame(
            '<iframe src="https://www.youtube.com/embed/a"></iframe>',
            $sanitizer->filter('<iframe src="https://www.youtube.com/embed/a">tekst <b>x</b></iframe>'),
        );
        $this->assertSame('<p>na</p>', $sanitizer->filter('<xmp><b>x</b></xmp><p>na</p>'));
    }

    /**
     * The sanitizer writes @ + = and the backtick as a code, in text and in values.
     * In a page that shows nowhere. A site that takes the text out of rich text
     * with strip_tags(), for a meta description or a feed, prints the code, and a
     * search for a mail address finds nothing. With every value stored the
     * filter's way that is every value, so those four are written as themselves.
     */
    public function test_an_at_a_plus_an_equals_and_a_backtick_stay_readable(): void
    {
        $this->needsTheFilter();
        $sanitizer = app(RichtextSanitizer::class);

        $stored = $sanitizer->filter(
            '<p>Mail info@dec-utrecht.nl, 1 + 1 = 2, "dubbel" \'enkel\' `x`</p>'
            .'<p><a href="mailto:info@dec-utrecht.nl">mail</a></p>'
        );

        $this->assertSame(
            '<p>Mail info@dec-utrecht.nl, 1 + 1 = 2, &#34;dubbel&#34; &#039;enkel&#039; `x`</p>'
            .'<p><a href="mailto:info@dec-utrecht.nl">mail</a></p>',
            $stored,
        );
        // What a site that takes the text out sees, and what a search looks through.
        $this->assertStringContainsString('info@dec-utrecht.nl, 1 + 1 = 2', strip_tags($stored));
        $this->assertSame(
            'Mail info@dec-utrecht.nl, 1 + 1 = 2, "dubbel" \'enkel\' `x`mail',
            html_entity_decode(strip_tags($stored), ENT_QUOTES | ENT_HTML5),
        );
        $this->assertSame($stored, $sanitizer->filter($stored));
    }

    /**
     * Putting = back must not put a quote back with it: the quote is what keeps a
     * value inside its attribute.
     */
    public function test_a_value_cannot_leave_its_attribute(): void
    {
        $this->needsTheFilter();
        $sanitizer = app(RichtextSanitizer::class);

        foreach ([
            '<a href="/x" class="a&quot; onmouseover=&quot;alert(1)">x</a>',
            '<a href="/x" title="a&#39; onmouseover=&#39;alert(1)">x</a>',
            '<a href="/x" title="a` onmouseover=`alert(1)">x</a>',
            '<a href="/x?a=&quot;&gt;&lt;img src=x onerror=alert(1)&gt;" title="b=c">x</a>',
            '<a href="/x" title="&gt;&lt;script&gt;alert(1)&lt;/script&gt;=@+`">x</a>',
        ] as $html) {
            $stored = $sanitizer->filter($html);

            $document = HTMLDocument::createFromString('<!DOCTYPE html><body>'.$stored.'</body>', LIBXML_NOERROR);
            $this->assertSame(1, $document->body->getElementsByTagName('*')->length, $stored);
            $link = $document->body->firstElementChild;
            $this->assertSame('a', $link->localName, $stored);
            $this->assertFalse($link->hasAttribute('onmouseover'), $stored);
            $this->assertEqualsCanonicalizing(
                array_map(fn ($attribute) => $attribute->name, iterator_to_array(HTMLDocument::createFromString('<!DOCTYPE html><body>'.$html.'</body>', LIBXML_NOERROR)->body->firstElementChild->attributes)),
                array_map(fn ($attribute) => $attribute->name, iterator_to_array($link->attributes)),
                $stored,
            );
            $this->assertSame($stored, $sanitizer->filter($stored), $html);
        }

        $this->assertSame(
            '<a href="/x" class="a&#34; onmouseover=&#34;alert(1)">x</a>',
            $sanitizer->filter('<a href="/x" class="a&quot; onmouseover=&quot;alert(1)">x</a>'),
        );
    }

    /**
     * Whoever types "&#64;" wants to see those five characters, not an @. The
     * sanitizer writes the & of a text as &amp; first, so a code it wrote itself
     * is never mistaken for one that was typed.
     */
    public function test_a_code_that_is_text_stays_text(): void
    {
        $this->needsTheFilter();
        $sanitizer = app(RichtextSanitizer::class);

        foreach ([
            '<p>&amp;#64; &amp;#43; &amp;#61; &amp;#96;</p>',
            '<p>&amp;amp;#64;</p>',
            '<p title="&amp;#64; &amp;#61;">t</p>',
        ] as $html) {
            $this->assertSame($html, $sanitizer->filter($html));
            $this->assertSame($html, $sanitizer->filter($sanitizer->filter($html)));
        }

        // Typed as a code for the character, it is that character, as in any browser.
        $this->assertSame('<p>@ @ @ = +</p>', $sanitizer->filter('<p>&#64; &#x40; &commat; &equals; &plus;</p>'));
    }

    /**
     * The characters go back in text and in values, not in the name of an
     * attribute, where the sanitizer wrote them as a code as well.
     */
    public function test_no_attribute_name_is_made_out_of_a_code(): void
    {
        $this->needsTheFilter();
        config(['leap.tinymce.sanitize_allow' => ['*' => ['@click']]]);

        $stored = (new RichtextSanitizer)->filter('<p @click="alert(1)" title="a@b">x</p>');

        $this->assertStringNotContainsString('@click', $stored);
        $this->assertStringContainsString('title="a@b"', $stored);
    }

    /**
     * The sanitizer adds a space behind a value that holds a backtick, a defence
     * against Internet Explorer 8. With what it writes stored every time, the
     * value grew by a space with every save.
     */
    public function test_a_value_with_a_backtick_does_not_grow_with_every_save(): void
    {
        $this->needsTheFilter();
        $sanitizer = app(RichtextSanitizer::class);

        foreach ([
            '<p title="a `b` c">x</p>',
            '<p title="ends with a space and has a ` ">x</p>',
            '<p title="`">x</p>',
            '<a href="/a`b.pdf" title="geen backtick ">x</a>',
        ] as $html) {
            $stored = $sanitizer->filter($html);

            $this->assertSame($html, $stored);
            $this->assertSame($stored, $sanitizer->filter($sanitizer->filter($stored)));
        }
    }

    public function test_an_iframe_is_still_cleaned_up_with_an_equals_in_its_values(): void
    {
        $this->needsTheFilter();
        $sanitizer = app(RichtextSanitizer::class);

        $kept = '<iframe title="x = y src=z" src="https://www.youtube.com/embed/x?v=1&amp;t=2" allow="a=b; c"></iframe>';
        $this->assertSame($kept, $sanitizer->filter($kept.'<iframe src="https://evil.example/?a=b" title="a=b"></iframe>'));
        $this->assertSame($kept, $sanitizer->filter($sanitizer->filter($kept)));

        // What is inside goes, and a host that is not on the list takes the frame along.
        $this->assertSame($kept, $sanitizer->filter(str_replace('></iframe>', '>a = b + c @ `d`</iframe>', $kept)));
        $this->assertSame('', $sanitizer->filter('<iframe title="src=https://www.youtube.com/embed/x" src="https://evil.example/">a=b</iframe>'));
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
            '<a href="mailto: info@x.nl">x</a>' => '<a href="mailto:info@x.nl">x</a>',
            "<a href=\"/storage/files/a\u{00A0}b.pdf\">x</a>" => '<a href="/storage/files/a%C2%A0b.pdf">x</a>',
            '<a href="/storage/files/a%c2%a0b%C2%A0c.pdf">x</a>' => '<a href="/storage/files/a%c2%a0b%C2%A0c.pdf">x</a>',
            '<img src=" /storage/foto 1.png " alt="a">' => '<img src="/storage/foto%201.png" alt="a" />',
            '<a href="mailto:x@y.nl?subject=Hallo daar">x</a>' => '<a href="mailto:x@y.nl?subject=Hallo%20daar">x</a>',
            '<a href="tel:+31 20 123 45 67">x</a>' => '<a href="tel:+31%2020%20123%2045%2067">x</a>',
        ] as $html => $clean) {
            $this->assertSame($clean, $sanitizer->filter($html), $html);
            $this->assertSame([], $sanitizer->dropped($html), $html);
        }
    }

    /**
     * A file name with a space in it is everywhere on an older site. The filter
     * writes the space as %20, which is the same address, so the report does not
     * name it: nothing goes and nothing leads anywhere else.
     */
    public function test_an_address_the_filter_only_encodes_is_not_reported(): void
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
            '<p><a href="/files/a%c2%a0b.pdf">x</a></p>',
            '<p><a href="tel:+31 20 123 45 67">bel</a> <a href="mailto:x@y.nl?subject=Hallo daar">mail</a></p>',
        ] as $html) {
            $this->assertSame(['dropped' => [], 'rewritten' => [], 'unclosed' => false], $sanitizer->changes($html), $html);
            $this->assertStringContainsString('href="', $sanitizer->filter($html), $html);
        }
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
            $this->assertSame(['dropped' => [], 'rewritten' => ['a[href]' => 1], 'unclosed' => false], $sanitizer->changes($html), $html);
        }

        // One link gone and one repaired, of the same kind, are told apart.
        $this->assertSame(
            ['dropped' => ['a[href]' => 1], 'rewritten' => ['a[href]' => 1], 'unclosed' => false],
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
        $this->assertSame(
            '<iframe src="https://www.youtube.com/embed/abc"></iframe>',
            $sanitizer->filter('<iframe src=" https://www.youtube.com/embed/abc "></iframe>'),
        );
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
        $this->assertSame([], (new RichtextSanitizer)->dropped($own));
        $this->assertSame('', (new RichtextSanitizer)->filter($vimeo));
    }

    /**
     * The embed code YouTube hands out, as TinyMCE keeps it when it is pasted into
     * the media dialog. All of it is what TinyMCE writes by default.
     */
    public function test_the_embed_code_of_youtube_loses_nothing(): void
    {
        $this->needsTheFilter();

        $embed = '<iframe title="YouTube video player" src="https://www.youtube.com/embed/x" width="560" height="315" frameborder="0" allow="accelerometer; autoplay; clipboard-write" allowfullscreen="allowfullscreen" referrerpolicy="strict-origin-when-cross-origin" class="video" style="border: 0;"></iframe>';

        $this->assertSame(['dropped' => [], 'rewritten' => [], 'unclosed' => false], app(RichtextSanitizer::class)->changes($embed));
    }

    /**
     * The sanitizer takes an attribute away from every element it is not told about
     * when one is added to a single element, and its '*' only reaches the elements
     * allowed so far. Adding the site's list to it step by step took width off every
     * image for a site that allowed it on a table cell.
     */
    public function test_what_a_site_allows_takes_nothing_from_what_was_allowed(): void
    {
        $this->needsTheFilter();

        $html = '<p><img src="/a.png" width="320" title="t" alt=""></p><table><tbody><tr><td width="50" bgcolor="#eeeeee">x</td></tr></tbody></table>'
            .'<iframe title="t" src="https://www.youtube.com/embed/x" loading="lazy"></iframe><p title="t"><abbr title="t">a</abbr></p>';
        $this->assertSame(['td[bgcolor]' => 1], app(RichtextSanitizer::class)->dropped($html));

        // width on a cell, which every image has too; loading and title, which an
        // iframe, a link and an abbreviation carry already.
        config(['leap.tinymce.sanitize_allow' => ['td' => ['width', 'bgcolor'], 'img' => ['loading'], 'div' => ['title']]]);
        $this->assertSame([], (new RichtextSanitizer)->dropped($html));

        config(['leap.tinymce.sanitize_allow' => ['video' => ['autoplay'], 'audio' => ['autoplay']]]);
        $this->assertSame([], (new RichtextSanitizer)->dropped('<video autoplay="autoplay" src="/v.mp4"></video><audio autoplay="autoplay" src="/a.mp3"></audio>'));
    }

    /**
     * The example in the docs: an attribute on every element, and an element that
     * is not allowed otherwise. The new element carries class, style and what is
     * allowed everywhere like any other, whichever of the two is listed first.
     */
    public function test_an_element_a_site_adds_carries_what_every_element_carries(): void
    {
        $this->needsTheFilter();

        $html = '<p data-aos="fade">x</p><video autoplay="autoplay" src="/v.mp4"></video><button type="button" class="btn" style="color: red;" data-aos="fade">b</button>';
        $this->assertSame(['button' => 1, 'button[class]' => 1, 'button[data-aos]' => 1, 'button[style]' => 1, 'button[type]' => 1, 'p[data-aos]' => 1, 'video[autoplay]' => 1], app(RichtextSanitizer::class)->dropped($html));

        foreach ([
            ['*' => ['data-aos'], 'video' => ['autoplay'], 'button' => ['type']],
            ['button' => ['type'], 'video' => ['autoplay'], '*' => ['data-aos']],
        ] as $allow) {
            config(['leap.tinymce.sanitize_allow' => $allow]);
            $sanitizer = new RichtextSanitizer;

            $this->assertSame([], $sanitizer->dropped($html));
            // Allowing an attribute on video did not take its other attributes away.
            $this->assertSame([], $sanitizer->dropped('<video controls="controls" src="/v.mp4" width="300"></video>'));
        }
    }

    /**
     * The switch of a security filter. An env value arrives as a string, and a
     * filter that stayed off for "1" did so without a word.
     */
    public function test_the_filter_is_on_for_every_way_of_writing_on(): void
    {
        foreach ([true, 1, '1', 'true', 'on', 'yes'] as $on) {
            config(['leap.tinymce.sanitize' => $on]);
            $this->assertTrue(RichtextSanitizer::enabled(), var_export($on, true));
        }

        foreach ([false, 0, '0', '', null, 'false', 'off', 'no', []] as $off) {
            config(['leap.tinymce.sanitize' => $off]);
            $this->assertFalse(RichtextSanitizer::enabled(), var_export($off, true));
        }
    }

    /**
     * An import writes to the model itself, past the editor, so it is filtered on
     * its own. Off, a CSV file is stored as it came, as before.
     */
    public function test_an_import_filters_a_rich_text_column(): void
    {
        $this->needsTheFilter();
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->boolean('active')->default(true);
            $table->text('title')->nullable();
            $table->timestamps();
        });
        $user = User::create(['name' => 'Test User', 'email' => 'test'.uniqid().'@example.com', 'password' => bcrypt('password')]);
        $user->roles()->attach(Role::find(1));
        Gate::before(fn () => true);

        $import = fn () => Livewire::actingAs($user)->test(RichtextImportResource::class)
            ->set('importData', [['<p>een</p><script>alert(1)</script>'], ['<p onclick="x()">twee</p><body onload="alert(1)">']])
            ->set('importColumns', [0 => 'title'])
            ->set('importRows', [true, true])
            ->call('import');

        $import();
        $this->assertSame(
            ['<p>een</p><script>alert(1)</script>', '<p onclick="x()">twee</p><body onload="alert(1)">'],
            Article::orderBy('id')->pluck('title')->all(),
        );

        Article::query()->delete();
        config(['leap.tinymce.sanitize' => 'on']);
        $import();
        $this->assertSame(['<p>een</p>', '<p>twee</p>'], Article::orderBy('id')->pluck('title')->all());
    }

    /**
     * The sanitizer stops reading at 20,000 bytes unless told otherwise, and a long
     * page would lose its end.
     */
    public function test_a_long_text_is_not_cut_short(): void
    {
        $this->needsTheFilter();

        $long = '<p>'.str_repeat('woord ', 8000).'</p>';
        $this->assertGreaterThan(40000, strlen($long));

        $this->assertSame($long, app(RichtextSanitizer::class)->filter($long));
        $this->assertStringEndsWith('woord </p>', app(RichtextSanitizer::class)->filter('<script>x</script>'.$long));
    }

    public function test_an_html_comment_goes_without_being_reported_as_a_loss(): void
    {
        $this->needsTheFilter();
        $sanitizer = app(RichtextSanitizer::class);

        $this->assertSame('<p>a &amp; b</p><p>c</p>', $sanitizer->filter('<p>a &amp; b</p><!-- more --><p>c</p>'));
        $this->assertSame(['dropped' => [], 'rewritten' => [], 'unclosed' => false], $sanitizer->changes('<p>a &amp; b</p><!-- more --><p>c</p>'));
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
        $this->assertStringContainsString('2 of 4 rich-text values in 2 records would lose or change something', $output);
        // The clean one is only written another way, and is counted apart from a loss.
        $this->assertStringContainsString('1 other value is not written the filter\'s way yet', $output);
        $this->assertStringContainsString('1 section has no definition in the module any more', $output);
        $this->assertStringContainsString('The filter is off', $output);
        $this->assertSame($before, DB::table('sections_models')->get()->toJson());
    }

    public function test_the_command_names_what_a_parser_can_still_see_of_a_hidden_script(): void
    {
        $this->needsTheFilter();

        SectionsModel::create(['title' => 'Body', 'sections' => [['_name' => 'block', 'body' => ['nl' => '<p>hi</p><body onload="alert(1)">']]]]);
        SectionsModel::create(['title' => 'Open', 'sections' => [['_name' => 'block', 'body' => ['nl' => '<p>hi</p><img src=x onerror=alert(1) x="']]]]);

        Artisan::call('leap:sanitize');
        $output = Artisan::output();

        $this->assertMatchesRegularExpression('/body\[onload\]\s+\|\s+1\s+\|\s+SectionsResource 1/', $output);
        $this->assertStringContainsString('Left open at the end of a value, and closed or cut off by the filter: SectionsResource 2', $output);
        $this->assertStringContainsString('2 of 2 rich-text values in 2 records would lose or change something', $output);
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
        $this->assertStringContainsString('2 of 2 rich-text values in 1 record would lose or change something', $output);
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
