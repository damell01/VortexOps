<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every admin page carried ~230 KB of the same inline <script> and <style>
 * blocks (the Messages drawer, scanner helpers, mobile fixes...), downloaded
 * and parsed again on each page. This moves each large, unchanging block into
 * a file named by its content, served (behind login) with a one-year cache, so
 * the browser fetches it once and reuses it everywhere.
 *
 * Safe by construction:
 *  - only plain JavaScript and CSS blocks of 1.5 KB or more; JSON/data
 *    blocks, modules and anything carrying the session's CSRF token stay;
 *  - a block is only moved once the same content has been seen three times,
 *    so blocks with per-request values (timestamps, one-off data) never
 *    become files;
 *  - the replacement sits exactly where the block was, so scripts still run
 *    in the same order (a plain external script blocks like an inline one);
 *  - wire:navigate visits keep everything inline (see handle()).
 */
class ExternalizeInlineAssets
{
    public const MIN_BYTES = 1536;

    private const SEEN_BEFORE_MOVING = 3;

    public static function directory(): string
    {
        return storage_path('app/inline-assets');
    }

    /** Off in the test suite, so page tests read the markup a page actually renders. */
    public static bool $enabledInTests = false;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ((app()->runningUnitTests() && ! static::$enabledInTests) || ! $request->isMethod('GET') || $response->getStatusCode() !== 200 || ! $request->user()
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            || ! method_exists($response, 'getContent') || $response->getContent() === false) {
            return $response;
        }

        try {
            // A wire:navigate visit swaps the page in with JavaScript, and a <script src>
            // inserted that way runs asynchronously, after Alpine has started the
            // components that need it. Those pages keep their blocks inline; only
            // full page loads, where an external script blocks like an inline one,
            // get the cached files.
            $html = (string) $response->getContent();
            if (! $request->headers->has('X-Livewire-Navigate')) $html = $this->rewrite($html);
            $response->setContent($this->dedupeSvgs($html));
        } catch (\Throwable $e) {
            report($e); // never break a page over an optimisation
        }

        return $response;
    }

    /**
     * The menus draw the same icons again and again (273 icons, 84 shapes, on a
     * typical page). Each shape that repeats is drawn once in a hidden sprite at
     * the end of the page and every copy becomes <use href="#…">, keeping its
     * own class, size and colour. Icons inside scripts, or with their own ids
     * (gradients, clip paths), are left as they are.
     */
    public function dedupeSvgs(string $html): string
    {
        if (! str_contains($html, '</body>')) return $html;
        // Work only on markup outside <script>/<style> blocks.
        $parts = preg_split('#(<script\b[^>]*>.*?</script>|<style\b[^>]*>.*?</style>)#s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $pattern = '#<svg(\s[^>]*)?>(.*?)</svg>#s';
        $count = [];
        foreach ($parts as $i => $part) {
            if ($i % 2) continue;
            preg_match_all($pattern, $part, $found, PREG_SET_ORDER);
            foreach ($found as $svg) {
                $key = $this->svgKey($svg[1] ?? '', $svg[2]);
                if ($key !== null) $count[$key] = ($count[$key] ?? 0) + 1;
            }
        }
        $repeated = array_filter($count, fn ($n) => $n > 1);
        if (! $repeated) return $html;

        $symbols = [];
        foreach ($parts as $i => &$part) {
            if ($i % 2) continue;
            $part = preg_replace_callback($pattern, function (array $svg) use ($repeated, &$symbols) {
                $attrs = $svg[1] ?? '';
                $key = $this->svgKey($attrs, $svg[2]);
                if ($key === null || ! isset($repeated[$key])) return $svg[0];
                $id = 'vxi-'.substr(md5($key), 0, 10);
                if (! isset($symbols[$id])) {
                    preg_match('/\bviewBox\s*=\s*"([^"]*)"/i', $attrs, $vb);
                    $symbols[$id] = '<symbol id="'.$id.'"'.(isset($vb[1]) ? ' viewBox="'.$vb[1].'"' : '').'>'.$svg[2].'</symbol>';
                }

                return '<svg'.$attrs.'><use href="#'.$id.'"></use></svg>';
            }, $part) ?? $part;
        }
        unset($part);
        if (! $symbols) return $html;

        $sprite = '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden" id="vx-icon-sprite">'.implode('', $symbols).'</svg>';
        $out = implode('', $parts);
        $at = strrpos($out, '</body>');

        return $at === false ? $html : substr($out, 0, $at).$sprite.substr($out, $at);
    }

    /** What makes two icons the same picture; null for icons that must stay inline. */
    private function svgKey(string $attrs, string $inner): ?string
    {
        if (strlen($inner) < 60 || preg_match('/\bid\s*=|<(style|script|text|foreignObject|use)\b|url\(#/i', $inner)) return null;
        preg_match('/\bviewBox\s*=\s*"([^"]*)"/i', $attrs, $vb);

        return ($vb[1] ?? '').'|'.trim($inner);
    }

    public function rewrite(string $html): string
    {
        $token = csrf_token();
        $dir = self::directory();
        if (! is_dir($dir)) @mkdir($dir, 0775, true);

        return preg_replace_callback('#<(script|style)(\s[^>]*)?>(.*?)</\1>#s', function (array $m) use ($token, $dir) {
            [$whole, $tag, $attrs, $body] = [$m[0], $m[1], $m[2] ?? '', $m[3]];
            if (strlen($body) < self::MIN_BYTES) return $whole;
            if (preg_match('/\bsrc\s*=/i', $attrs)) return $whole;
            if ($tag === 'script' && preg_match('/\btype\s*=\s*["\']?(?!text\/javascript|application\/javascript)[^"\'\s>]+/i', $attrs)) return $whole;
            if ($token !== '' && str_contains($body, $token)) return $whole;

            $ext = $tag === 'script' ? 'js' : 'css';
            $hash = substr(hash('sha256', $body), 0, 32);
            $file = $dir.'/'.$hash.'.'.$ext;

            if (! is_file($file)) {
                $seen = Cache::increment('vx-inline-seen:'.$hash);
                if ($seen === false || $seen < self::SEEN_BEFORE_MOVING) {
                    if ($seen === 1) Cache::put('vx-inline-seen:'.$hash, 1, now()->addDay());
                    return $whole;
                }
                if (@file_put_contents($file.'.tmp', $body) === false || ! @rename($file.'.tmp', $file)) return $whole;
            }

            $url = route('inline-asset', ['file' => $hash.'.'.$ext]);
            $kept = trim(preg_replace('/\btype\s*=\s*["\'][^"\']*["\']/i', '', $attrs));

            return $tag === 'script'
                ? '<script src="'.e($url).'"'.($kept !== '' ? ' '.$kept : '').'></script>'
                : '<link rel="stylesheet" href="'.e($url).'"'.($kept !== '' ? ' '.$kept : '').'>';
        }, $html) ?? $html;
    }
}
