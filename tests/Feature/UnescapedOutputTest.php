<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Controllers\AssetController;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Editor;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\SectionsModel;
use NickDeKruijk\Leap\Tests\Fixtures\SectionsResource;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * What reaches the panel without being escaped.
 *
 * InlineScriptStringsTest watches what goes into JavaScript. This watches what goes
 * into HTML: @lang, {!! !!} and x-html print a value as it is, which is right for a
 * translation line or markup leap wrote itself, and a stored XSS for anything an
 * administrator typed. A role named "><img src=x onerror=alert(1)> ran script on the
 * dashboard of everyone who had that role, because @lang does not escape what it
 * fills into a line.
 */
class UnescapedOutputTest extends TestCase
{
    private const PAYLOAD = '"><img src=x onerror=alert(1)><x="';

    private function actingAsPanelUser(): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->roles()->attach(Role::find(1));
        $this->actingAs($user);

        return $user;
    }

    /**
     * Through the real route, so the name comes out of the database the way it
     * does in the panel: RequireRole reads it, the dashboard prints it.
     */
    public function test_a_role_name_is_text_on_the_dashboard(): void
    {
        Role::find(1)->update(['name' => self::PAYLOAD]);
        $this->actingAsPanelUser();

        $html = $this->followingRedirects()->get(route('leap.home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString(e(self::PAYLOAD), $html);
    }

    /**
     * The click-to-edit preview shows stored HTML. PHPUnit cannot run the script
     * that cleans it, so this holds the two ends: the raw x-html is gone, and what
     * replaced it goes through DOMPurify.
     */
    public function test_the_richtext_preview_does_not_print_the_stored_html_as_it_is(): void
    {
        Schema::create('sections_models', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->json('sections')->nullable();
            $table->timestamps();
        });
        $this->actingAsPanelUser();
        Leap::context()->setModule(SectionsResource::class);
        Leap::context()->setPermissions([
            SectionsResource::class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);
        $record = SectionsModel::create([
            'title' => 'Over ons',
            'sections' => [['_name' => 'block', 'head' => 'Partners', 'body' => '<p>a<img src=x onerror=alert(1)></p>']],
        ]);

        $html = html_entity_decode(Livewire::test(Editor::class)->call('openEditor', $record->id)->html(), ENT_QUOTES);

        $this->assertStringContainsString('class="leap-richtext-preview tinymce"', $html);
        $this->assertStringNotContainsString('x-html', $html);
        $this->assertStringContainsString('x-effect="renderPreview($el)"', $html);
        $this->assertStringContainsString('DOMPurify.sanitize(html', $html);
        $this->assertStringContainsString(str_replace('/', '\/', AssetController::DOMPURIFY_CDN), $html);
        $this->assertStringContainsString(str_replace('/', '\/', AssetController::TINYMCE_CDN), $html);
        // Never the raw value on the way to DOMPurify: the text only.
        $this->assertStringContainsString('preview.textContent =', $html);
        $this->assertSame(2, substr_count($html, 'preview.innerHTML = clean()'));
        // Nothing else is ever assigned as HTML; sameContent() only reads it back
        // out of a document of its own.
        $this->assertSame(2, substr_count($html, 'innerHTML ='));
    }

    public function test_the_attributes_of_an_input_are_quoted(): void
    {
        $this->assertSame(
            ' aria-label="Email Address" type="email"',
            Attribute::make('email_address')->email()->inputAttributes()->toHtml(),
        );
    }

    /**
     * A label is HTML where it is shown, so its text is what an aria-label wants,
     * and whatever is in it has to stay inside the quotes.
     */
    public function test_a_label_cannot_leave_its_aria_label(): void
    {
        $this->assertSame(
            ' aria-label="Naam &quot; onfocus=&quot;alert(1) &amp; co" type="text"',
            Attribute::make('name')->label('Naam <em>"</em> onfocus="alert(1) &amp; co')->inputAttributes()->toHtml(),
        );
    }

    /**
     * Raw output a view may hold, each with the reason it is not data.
     * Anything else is escaped ({{ }}), or added here with its reason.
     */
    private const RAW = [
        // Markup leap builds itself.
        '{!! \NickDeKruijk\Leap\Controllers\AssetController::cssLink() !!}',
        '{!! \NickDeKruijk\Leap\Controllers\AssetController::tinymceContentCssLink() !!}',
        '{!! $this->qrCodeSvg !!}',
        '{!! $rules !!}',
        // Escaped before nl2br adds its <br>.
        '{!! nl2br(e($value)) !!}',
        // Written by the developer in app/Leap: label() and hint() take HTML, and a
        // module's getOutput() is its own navigation markup.
        '{!! $attribute->label ?? ($label ?? $name) !!}',
        '{!! $attribute->hint !!}',
        '{!! $module->getOutput() !!}',
        // A translation key built from code or config, never from a record.
        '@lang($label)',
        "@lang(\$attribute->options['multiple'] ? 'leap::resource.add_files' : 'leap::resource.add_file')",
        "@lang('leap::consent.'.\$key)",
        "@lang('leap::consent.'.\$key.'_body')",
        "@lang('leap::resource.image_orientation_'.\$orientation)",
        "@lang('leap::filemanager.delete_file' . (count(\$selectedFiles) > 1 ? 's' : '') . '_confirm')",
        "@lang('leap::filemanager.delete_file' . (count(\$selectedFiles) > 1 ? 's' : ''))",
        "@lang('leap::filemanager.' . \$key)",
        // The line may hold a link the site wrote; what is filled into it is escaped.
        "@lang('leap::consent.no_cookies', [ 'services' => e(collect(\$category['services'] ?? [])->pluck('name')->join(', ', ' ' . __('leap::consent.and') . ' ')), ])",
    ];

    public function test_every_raw_output_in_a_view_is_known_to_be_safe(): void
    {
        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../../resources/views', \FilesystemIterator::SKIP_DOTS));
        $offenders = [];
        $checked = 0;
        $used = [];

        foreach ($views as $view) {
            if (! str_ends_with($view->getFilename(), '.blade.php')) {
                continue;
            }
            $source = (string) file_get_contents($view->getPathname());
            $at = fn (int $offset) => basename($view->getPathname()).':'.(substr_count($source, "\n", 0, $offset) + 1);

            foreach ($this->rawOutputs($source) as [$expression, $offset]) {
                $checked++;
                // A plain translation line: @lang with one string and nothing to fill in.
                if (preg_match('/^@(?:lang|choice)\(\'[^\'$]*\'\)$/', $expression)) {
                    continue;
                }
                if (in_array($expression, self::RAW, true)) {
                    $used[$expression] = true;

                    continue;
                }
                $offenders[] = $at($offset).' '.$expression;
            }
        }

        // A scan that finds no views at all would pass without checking anything.
        $this->assertGreaterThan(50, $checked);
        $this->assertSame([], $offenders, 'Escape these with {{ }} (for a translation with replacements: {{ __() }}), or add them to RAW with the reason they are safe.');
        // An entry nothing uses any more is a door left open for the next view.
        $this->assertSame([], array_values(array_diff(self::RAW, array_keys($used))), 'These RAW entries match nothing any more: remove them.');
    }

    /**
     * Every place a view prints without escaping: {!! !!}, @lang(), @choice() and
     * x-html, with whitespace collapsed so an entry reads the same on one line.
     *
     * @return list<array{string, int}>
     */
    private function rawOutputs(string $source): array
    {
        $found = [];

        preg_match_all('/\{!!.*?!!\}|\bx-html\b/s', $source, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as [$match, $offset]) {
            $found[] = [(string) preg_replace('/\s+/', ' ', $match), $offset];
        }

        preg_match_all('/@(?:lang|choice)\(/', $source, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as [$match, $offset]) {
            // Up to the bracket that closes the directive, strings skipped over.
            $depth = 0;
            $quote = null;
            for ($i = $offset + strlen($match) - 1, $length = strlen($source); $i < $length; $i++) {
                $char = $source[$i];
                if ($quote !== null) {
                    if ($char === '\\') {
                        $i++;
                    } elseif ($char === $quote) {
                        $quote = null;
                    }
                } elseif ($char === "'" || $char === '"') {
                    $quote = $char;
                } elseif ($char === '(') {
                    $depth++;
                } elseif ($char === ')' && --$depth === 0) {
                    break;
                }
            }
            $found[] = [(string) preg_replace('/\s+/', ' ', substr($source, $offset, $i - $offset + 1)), $offset];
        }

        return $found;
    }
}
