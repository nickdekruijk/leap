<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Tests\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * No x-on handler reaches for "this".
 *
 * Alpine evaluates an event handler with the data stack as its scope, so a method
 * declared in x-data is called by name. "this" is not the component there — it is
 * whatever the surrounding function was bound to, which is to say nothing useful.
 *
 * The failure is the quiet kind and only at runtime: "this.clean is not a function" in
 * the console, and a field that silently does nothing. It shipped once, in the slug
 * input, which is why this reads the markup instead of trusting a review.
 *
 * Inside x-data a method may of course use "this" — Alpine binds those to the data
 * proxy. Only the handler expressions are checked.
 */
class AlpineHandlerScopeTest extends TestCase
{
    public function test_no_event_handler_calls_a_method_on_this(): void
    {
        $offenders = [];

        $files = Finder::create()
            ->files()
            ->in(__DIR__.'/../../resources/views')
            ->name('*.blade.php');

        foreach ($files as $file) {
            $contents = $file->getContents();

            if (! preg_match_all('/x-on:[\w.\-]+="([^"]*)"/s', $contents, $matches)) {
                continue;
            }

            foreach ($matches[1] as $expression) {
                if (str_contains($expression, 'this.')) {
                    $offenders[] = $file->getRelativePathname().': '.trim(preg_replace('/\s+/', ' ', $expression));
                }
            }
        }

        $this->assertSame([], $offenders, "An x-on expression cannot reach the component through \"this\"; call the x-data method by name.\n".implode("\n", $offenders));
    }
}
