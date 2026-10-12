<?php

namespace App\Services;

/**
 * Turns a cookie export into the scraper's saved Whatnot sign-in.
 *
 * Shared by `whatnot:login --paste/--cookie-file` and the System Health
 * "Refresh Whatnot sign-in" form, so both accept the same exports and write
 * the same file. Accepts Cookie-Editor's bare array or Playwright's
 * storageState; keeps only whatnot.com cookies and drops Cloudflare's
 * connection-specific challenge cookies, which are not the account session.
 */
class WhatnotCookieImport
{
    private const CLOUDFLARE = [
        'cf_clearance', '__cf_bm', '__cfwaitingroom',
        'cf_chl_2', 'cf_chl_prog', 'cf_chl_rc_i', 'cf_chl_rc_ni', 'cf_chl_rc_m',
    ];

    /**
     * @return array{ok: bool, error: ?string, count: int, looks_signed_in: bool}
     */
    public function import(string $json, string $cookiesFile): array
    {
        $raw = json_decode(trim($json), true);

        if (is_array($raw) && isset($raw['cookies']) && is_array($raw['cookies'])) {
            $raw = $raw['cookies'];
        }

        if (! is_array($raw) || $raw === []) {
            return $this->fail('That is not a valid cookie JSON export. Export cookies as JSON from Cookie-Editor.');
        }

        $sameSiteMap = ['no_restriction' => 'None', 'strict' => 'Strict', 'lax' => 'Lax', 'unspecified' => 'Lax'];
        $cookies = array_values(array_filter(array_map(function ($c) use ($sameSiteMap) {
            if (! is_array($c) || ! isset($c['name'], $c['value'])) return null;
            $domain = $c['domain'] ?? '.whatnot.com';
            if (! str_contains($domain, 'whatnot.com')) return null;
            if (in_array(strtolower($c['name']), self::CLOUDFLARE, true)) return null;

            return [
                'name'     => $c['name'],
                'value'    => $c['value'],
                'domain'   => $domain,
                'path'     => $c['path'] ?? '/',
                'expires'  => $c['expirationDate'] ?? $c['expires'] ?? -1,
                'httpOnly' => (bool) ($c['httpOnly'] ?? false),
                'secure'   => (bool) ($c['secure'] ?? true),
                'sameSite' => $sameSiteMap[strtolower($c['sameSite'] ?? '')] ?? 'Lax',
            ];
        }, $raw)));

        if ($cookies === []) {
            return $this->fail('No whatnot.com cookies found in the file. Make sure you exported from whatnot.com.');
        }

        file_put_contents($cookiesFile, json_encode($cookies, JSON_PRETTY_PRINT));

        // The persistent browser profile only re-reads this file when it is
        // newer than the last load; dropping the marker forces the next run
        // to pick the new sign-in up.
        if (is_file($marker = $cookiesFile.'.loaded-mtime')) {
            @unlink($marker);
        }

        return [
            'ok' => true,
            'error' => null,
            'count' => count($cookies),
            // A session cookie is the point of the export; document.cookie
            // cannot see HttpOnly ones, which is the usual way to miss it.
            'looks_signed_in' => (bool) array_filter($cookies, fn ($c) => (bool) preg_match('/session|token|auth/i', $c['name'])),
        ];
    }

    private function fail(string $error): array
    {
        return ['ok' => false, 'error' => $error, 'count' => 0, 'looks_signed_in' => false];
    }
}
