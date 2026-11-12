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

        $response = Http::timeout(20)->withOptions(['allow_redirects' => ['max' => 3, 'protocols' => ['https']]])->get($url);
        abort_unless($response->successful(), 502, 'Whatnot did not return the label. Try again, or open it on Shipping & Labels.');

        $type = (string) $response->header('Content-Type');
        $type = str_contains($type, 'pdf') ? 'application/pdf' : (str_starts_with($type, 'image/') ? explode(';', $type)[0] : 'application/pdf');

        return response($response->body(), 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'inline; filename="label-' . $row->id . '.' . ($type === 'application/pdf' ? 'pdf' : 'png') . '"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
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
