<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Compile every Blade view on its own and syntax-check the PHP it becomes.
 *
 * A view that does not compile only fails when someone opens that page, and
 * the bare-metal deploy does not cache views up front, so nothing else catches
 * it before it is live. CI runs this before the deploy is allowed.
 */
class CheckViews extends Command
{
    protected $signature = 'views:check {--path=resources/views : Folder of Blade views to check}';

    protected $description = 'Compile each Blade view and lint the result, failing on any broken page';

    public function handle(): int
    {
        // The largest views exceed PHP's default regex limits while compiling.
        ini_set('pcre.backtrack_limit', '10000000');
        ini_set('pcre.recursion_limit', '1000000');

        $compiler = app('blade.compiler');
        $tmp = tempnam(sys_get_temp_dir(), 'vxview');
        $files = Finder::create()->files()->in(base_path($this->option('path')))->name('*.blade.php')->sortByName();
        $broken = [];
        $checked = 0;

        foreach ($files as $file) {
            $checked++;
            $name = ltrim(substr($file->getRealPath(), strlen(base_path())), '/');

            try {
                file_put_contents($tmp, $compiler->compileString($file->getContents()));
            } catch (\Throwable $e) {
                $broken[] = [$name, 'does not compile: '.$e->getMessage()];
                continue;
            }

            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($tmp).' 2>&1', $out, $code);
            if ($code !== 0) {
                $message = collect($out)->first(fn ($line) => str_contains($line, 'error')) ?? implode(' ', $out);
                $broken[] = [$name, trim(str_replace([$tmp, 'PHP Parse error:  ', 'Parse error: '], ['', '', ''], $message))];
            }
            $out = [];
        }

        @unlink($tmp);

        if ($broken) {
            $this->table(['View', 'Problem'], $broken);
            $this->error(count($broken).' of '.$checked.' views are broken. Those pages would crash when opened.');

            return self::FAILURE;
        }

        $this->info("All {$checked} views compile.");

        return self::SUCCESS;
    }
}
