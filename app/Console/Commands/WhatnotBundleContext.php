<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WhatnotBundleContext extends Command
{
    protected $signature = 'whatnot:bundle-context
                            {chunk : Whatnot _next/static/chunks filename, e.g. 62319-....js}
                            {needle : Literal text to find in the bundle}
                            {--before=8000 : Characters to print before the match}
                            {--after=16000 : Characters to print after the match}
                            {--save= : Optional file path to save the extracted context}';

    protected $description = 'Fetch one public Whatnot JS chunk and print a large context window around a GraphQL operation/fragment';

    public function handle(): int
    {
        $chunk = basename(trim((string) $this->argument('chunk')));
        $needle = (string) $this->argument('needle');
        $before = max(0, (int) $this->option('before'));
        $after = max(0, (int) $this->option('after'));

        if ($chunk === '' || ! str_ends_with($chunk, '.js')) {
            $this->error('chunk must be a .js filename.');
            return self::FAILURE;
        }

        if ($needle === '') {
            $this->error('needle cannot be empty.');
            return self::FAILURE;
        }

        $urls = [
            "https://www.whatnot.com/_next/static/chunks/{$chunk}",
            "https://www.whatnot.com/_next/static/chunks/app/{$chunk}",
        ];

        $body = null;
        $usedUrl = null;
        foreach ($urls as $url) {
            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128 Safari/537.36',
                        'Accept' => '*/*',
                    ])
                    ->get($url);

                if ($response->successful() && strlen($response->body()) > 100) {
                    $body = $response->body();
                    $usedUrl = $url;
                    break;
                }
            } catch (\Throwable) {
                // Try the next known Next.js chunk layout.
            }
        }

        if ($body === null) {
            $this->error("Could not fetch chunk {$chunk} from the known Whatnot Next.js chunk paths.");
            return self::FAILURE;
        }

        $offset = strpos($body, $needle);
        if ($offset === false) {
            $this->error("Needle '{$needle}' was not found in {$chunk}.");
            $this->line("Fetched: {$usedUrl}");
            return self::FAILURE;
        }

        $start = max(0, $offset - $before);
        $length = min(strlen($body) - $start, $before + strlen($needle) + $after);
        $context = substr($body, $start, $length);

        $this->info("Found '{$needle}' in {$chunk}");
        $this->line("Source: {$usedUrl}");
        $this->line("Bundle bytes: " . strlen($body) . "; match offset: {$offset}");
        $this->newLine();
        $this->line($context);

        if ($path = $this->option('save')) {
            file_put_contents($path, $context);
            $this->newLine();
            $this->info("Saved context to {$path}");
        }

        return self::SUCCESS;
    }
}
