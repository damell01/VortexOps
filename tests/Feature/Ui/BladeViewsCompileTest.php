<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Every Blade view must compile to valid PHP.
 *
 * The receiving page shipped broken and nothing caught it. A view is compiled
 * the first time it is rendered, so a syntax error in one sits silent through
 * the whole suite and every deploy, then throws the moment a person opens that
 * page — and Laravel's own error renderer was in no state to explain it.
 *
 * What broke it is worth naming because it is invisible on inspection: the
 * inline php() directive is collected by the same raw-block pass as the block
 * form, so an inline one written above a block one pairs with the *block's*
 * closing tag and swallows every line between them. Directive counts balance,
 * the file reads correctly, and the compiled output has an unclosed if.
 *
 * Compiling every view is cheap and catches all of it.
 *
 * @coversNothing
 */
class BladeViewsCompileTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function views(): iterable
    {
        $root = dirname(__DIR__, 3) . '/resources/views';

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace($root . '/', '', $file->getPathname());

            yield $relative => [$file->getPathname()];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('views')]
    public function test_the_view_compiles_to_valid_php(string $path): void
    {
        $compiled = Blade::compileString(file_get_contents($path));

        // token_get_all raises the parse error as a PHP warning rather than
        // returning anything useful, so read it off the error handler.
        $error = null;
        set_error_handler(function (int $no, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            token_get_all($compiled, TOKEN_PARSE);
        } catch (\ParseError $e) {
            $error = $e->getMessage();
        } finally {
            restore_error_handler();
        }

        $this->assertNull(
            $error,
            basename($path) . ' does not compile: ' . $error,
        );
    }
}
