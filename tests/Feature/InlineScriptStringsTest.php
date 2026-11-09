<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Livewire\Dashboard;
use NickDeKruijk\Leap\Livewire\FileManager;
use NickDeKruijk\Leap\Models\Role;
use NickDeKruijk\Leap\Tests\Fixtures\User;
use NickDeKruijk\Leap\Tests\TestCase;

/**
 * Text that goes into inline JavaScript must go in through Js::from(). Blade's
 * {{ }} turns an apostrophe into &#039;, which the browser decodes back into a
 * quote inside an attribute (ending the JS string) and shows literally inside a
 * <script> block. A host translation such as "l'accès" hits both.
 */
class InlineScriptStringsTest extends TestCase
{
    private function actingAsPanelUser(string $name = 'Test User'): User
    {
        $user = User::create([
            'name' => $name,
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->roles()->attach(Role::find(1));
        $this->actingAs($user);

        return $user;
    }

    /**
     * Expressions that may be echoed into inline JavaScript with {{ }}, because a
     * developer defines them (attribute names, configured locales), they are
     * numbers, or they are encoded already. Anything else goes in through
     * Js::from(), or is added here with the reason it is safe.
     */
    private const SAFE_IN_JS = [
        // Defined in app/Leap by the developer, or derived from config('leap.locales').
        '$attribute->name', '$attribute->dataName', '$attribute->sectionName',
        '$attribute->showIfExpression', '$code', '$slugLocale', '$localeKey', '$translateFrom',
        '$key', '$show', '$close', "config('leap.tinymce.cdn')",
        "\$lazy ? 'true' : 'false'",
        "\$this->twoFactorEmailEnrolling ? 'confirmTwoFactorEmail' : 'confirmTwoFactor'",
        // Numbers.
        '$id', '$index', '$i', '$loop->index', '$depth', '$depth - 1', '$depth + 1',
        '$passkey->id', "\$row['id']", '$parent ?? 0',
        // Encoded: urlencode() leaves no quote, and json_encode() stays valid JS once
        // the browser decodes the attribute.
        'urlencode($name)', 'json_encode($attribute->options)', 'json_encode($options)',
    ];

    /** Attributes Livewire reads as plain text or a property name, not as JavaScript. */
    private const NOT_JS = ['wire:key', 'wire:confirm', 'wire:target', 'wire:model'];

    public function test_every_value_echoed_into_inline_javascript_is_known_to_be_safe(): void
    {
        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../../resources/views', \FilesystemIterator::SKIP_DOTS));
        $echo = '/\{\{(.*?)\}\}|\{!!(.*?)!!\}|(@(?:lang|choice)\()/s';
        $offenders = [];
        $checked = 0;

        foreach ($views as $view) {
            if (! str_ends_with($view->getFilename(), '.blade.php')) {
                continue;
            }
            $source = file_get_contents($view->getPathname());
            $at = fn (int $offset) => basename($view->getPathname()).':'.(substr_count($source, "\n", 0, $offset) + 1);
            $code = [];

            // Alpine (x-*, @*, :*), Livewire (wire:*) and DOM (on*) attributes, and <script> blocks.
            preg_match_all('/(?<![\w-])(x-[\w:.-]+|@[\w.:-]+|:[\w.-]+|wire:[\w.-]+|on\w+)\s*=\s*"([^"]*)"/s', $source, $attributes, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($attributes as $attribute) {
                $name = $attribute[1][0];
                if (! in_array(preg_replace('/^(wire:[\w-]+).*/', '$1', $name), self::NOT_JS, true)) {
                    $code[] = $attribute[2];
                }
            }
            preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $source, $scripts, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($scripts as $script) {
                $code[] = $script[1];
            }

            foreach ($code as [$js, $offset]) {
                preg_match_all($echo, $js, $echoes, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
                foreach ($echoes as $match) {
                    $checked++;
                    $expression = trim($match[1][0] ?? '') ?: trim($match[2][0] ?? '') ?: $match[3][0];
                    if (! str_starts_with($expression, 'Js::from(') && ! in_array($expression, self::SAFE_IN_JS, true)) {
                        $offenders[] = $at($offset + $match[0][1]).' '.$expression;
                    }
                }
            }
        }

        // A scan that finds no views at all would pass without checking anything.
        $this->assertGreaterThan(50, $checked);
        $this->assertSame([], $offenders, 'Put these through Js::from(), or add them to SAFE_IN_JS with the reason they are safe.');
    }

    public function test_the_dashboard_greeting_keeps_an_apostrophe_and_shows_the_name_as_text(): void
    {
        foreach (['good_morning', 'good_afternoon', 'good_evening'] as $key) {
            app('translator')->addLines(["dashboard.{$key}" => "Salut l'ami"], app()->getLocale(), 'leap');
        }
        $this->actingAsPanelUser("O'Brien <b>x</b>");
        Leap::context()->setPermissions([Dashboard::class => ['read' => true]]);

        $html = html_entity_decode(Livewire::test(Dashboard::class)->html(), ENT_QUOTES);
        $bs = chr(92);

        $this->assertStringContainsString("greeting = 'Salut l{$bs}u0027ami'", $html);
        $this->assertStringContainsString("' ' + 'O{$bs}u0027Brien {$bs}u003Cb{$bs}u003Ex{$bs}u003C{$bs}/b{$bs}u003E'", $html);
        // The name now reaches the script as real text, so it must not be parsed as HTML.
        $this->assertStringContainsString('$el.textContent = greeting()', $html);
        $this->assertStringNotContainsString('innerHTML = greeting()', $html);
    }

    public function test_the_page_expired_confirm_keeps_an_apostrophe(): void
    {
        app('translator')->addLines(['auth.page_expired' => "Page perdue, l'actualiser ?"], app()->getLocale(), 'leap');
        $this->actingAsPanelUser();

        $html = $this->followingRedirects()->get(route('leap.home'))->assertOk()->getContent();

        $this->assertStringContainsString("confirm('Page perdue, l\\u0027actualiser ?')", $html);
        $this->assertStringNotContainsString('l&#039;actualiser', $html);
    }

    /**
     * The default name of a crop comes from the file name, and a file can be put
     * in storage outside the panel (FTP, slug_uploads off), so it can hold a
     * quote. In the mouseup handler that ended the JS string and ran the rest as
     * script for whoever cropped the file.
     */
    public function test_the_crop_name_of_a_file_with_a_quote_stays_a_js_string(): void
    {
        Storage::fake('public');
        $name = "x');alert(1);x.png";
        $gd = imagecreatetruecolor(20, 20);
        ob_start();
        imagepng($gd);
        Storage::disk('public')->put($name, ob_get_clean());
        config(['leap.filemanager.image_crop_enabled' => true]);

        $this->actingAsPanelUser();
        Leap::context()->setModule(FileManager::class)->setPermissions([
            FileManager::class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);

        $html = Livewire::test(FileManager::class)
            ->call('selectFile', urlencode($name), 0)
            ->html();
        $html = html_entity_decode($html, ENT_QUOTES);
        $bs = chr(92);

        $this->assertStringContainsString("cropNewName = 'x{$bs}u0027);alert(1);x-crop.png'", $html);
        $this->assertStringNotContainsString("'x');alert(1)", $html);
    }

    /**
     * The id of an upload comes from the browser through uploadStart(), so it is
     * outside input too, and it went into wire:click without quotes.
     */
    public function test_an_upload_id_from_the_browser_stays_a_js_value(): void
    {
        Storage::fake('public');
        $this->actingAsPanelUser();
        Leap::context()->setModule(FileManager::class)->setPermissions([
            FileManager::class => ['read' => true, 'create' => true, 'update' => true, 'delete' => true],
        ]);

        $html = Livewire::test(FileManager::class)
            ->call('uploadStart', '1);alert(1);(', 'a.png', 10)
            ->html();

        $this->assertStringContainsString("uploadClear('1);alert(1);(')", html_entity_decode($html, ENT_QUOTES));
    }
}
