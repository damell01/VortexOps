<?php

namespace App\Http\Controllers;

use App\Filament\Resources\FulfillmentResource\Pages\ShippingDesk;
use App\Models\FulfillmentRunPackage;
use App\Models\Shipment;
use App\Support\FulfillmentAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * A package's Whatnot shipping label, served from this site so the packing
 * screen can show it in a window and print it without leaving the app (a
 * browser will not print a file loaded straight from another site). Only for
 * someone who may work that show; only the https label link Whatnot gave us,
 * never an address on this machine or the local network.
 */
class FulfillmentLabelController
{
    /** How a host name is resolved to addresses (replaceable in tests, which have no DNS). */
    public static ?\Closure $resolve = null;

    public function __invoke(Request $request, int $package): Response
    {
        $row = FulfillmentRunPackage::query()->find($package);
        abort_unless($row && FulfillmentAccess::moduleEnabled() && FulfillmentAccess::canWorkRun($request->user(), (int) $row->run_id), 404);

        $payload = $row->shipment_id ? Shipment::query()->whereKey($row->shipment_id)->value('raw_payload') : null;
        $payload = is_array($payload) ? $payload : (is_string($payload) ? (json_decode($payload, true) ?: []) : []);
        $url = ShippingDesk::safeDocumentUrl(($payload['documents'] ?? [])['label_url'] ?? null);
        abort_unless($url && $this->isPublicHost((string) parse_url($url, PHP_URL_HOST)), 404);

        $response = Http::timeout(20)->withOptions(['allow_redirects' => ['max' => 0]])->get($url);
        abort_unless($response->successful(), 502, 'Whatnot did not return the label. Try again, or open it on Shipping & Labels.');

        $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        abort_unless(in_array($type, ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'], true), 502, 'Unsupported label format.');
        abort_unless(strlen($response->body()) <= 15 * 1024 * 1024, 502, 'Label exceeds preview size limit.');

        return response($response->body(), 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'inline; filename="label-' . $row->id . '.' . ($type === 'application/pdf' ? 'pdf' : 'png') . '"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Preview a shipment label from Shipment Center or Shipping & Labels. */
    public function shipment(Request $request, int $shipment): Response
    {
        $row = Shipment::query()->findOrFail($shipment);
        $user = $request->user();
        abort_unless($user && FulfillmentAccess::moduleEnabled(), 404);
        $show = \App\Filament\Resources\FulfillmentResource::getEloquentQuery()->findOrFail($row->show_id);
        abort_unless(\App\Filament\Resources\FulfillmentResource::canView($show), 403);

        $payload = is_array($row->raw_payload) ? $row->raw_payload : (json_decode((string) $row->raw_payload, true) ?: []);
        $url = ShippingDesk::safeDocumentUrl(($payload['documents'] ?? [])['label_url'] ?? null);
        abort_unless($url && $this->isPublicHost((string) parse_url($url, PHP_URL_HOST)), 404);
        $response = Http::timeout(20)->withOptions(['allow_redirects' => ['max' => 0]])->get($url);
        abort_unless($response->successful(), 502, 'The Whatnot label is currently unavailable.');
        $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        abort_unless($type === 'application/pdf' || in_array($type, ['image/png', 'image/jpeg', 'image/webp'], true), 502, 'Unsupported label document format.');
        abort_unless(strlen($response->body()) <= 15 * 1024 * 1024, 502, 'Label exceeds preview size limit.');

        return response($response->body(), 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'inline; filename="shipment-label-' . $row->id . '.' . ($type === 'application/pdf' ? 'pdf' : 'png') . '"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    /** Refuse anything that resolves to this machine or a private network. */
    private function isPublicHost(string $host): bool
    {
        if ($host === '' || in_array(strtolower($host), ['localhost'], true)) return false;
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ((self::$resolve ?? fn ($h) => gethostbynamel($h) ?: [])($host));
        if ($ips === []) return false;
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        }

        return true;
    }
}
