<?php

namespace App\Support;

use Illuminate\Support\Facades\Vite;

/**
 * The Vortex Colosseum's downloads: the 3D engine chunk and the fighter
 * models. The first page after login warms the browser with them (see
 * resources/js/app.js), so the leaderboard never waits on a download.
 * Model URLs carry a version from the files themselves, so a re-exported
 * model is fetched again while an unchanged one stays cached.
 */
class ColosseumAssets
{
    private static ?array $memo = null;

    /** @return array{v: string, models: list<string>, chunk: ?string} */
    public static function warm(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $files = glob(public_path('models/colosseum/*.glb')) ?: [];
        sort($files);
        $v = substr(md5(implode('|', array_map(fn ($f) => basename($f).':'.filesize($f).':'.filemtime($f), $files))), 0, 10);

        try {
            $chunk = Vite::asset('resources/js/colosseum/arena.js');
        } catch (\Throwable) {
            $chunk = null; // no build yet (tests, first deploy)
        }

        return self::$memo = [
            'v' => $v,
            'models' => array_map(fn ($f) => asset('models/colosseum/'.basename($f)).'?v='.$v, $files),
            'chunk' => $chunk,
        ];
    }

    public static function version(): string
    {
        return self::warm()['v'];
    }
}
