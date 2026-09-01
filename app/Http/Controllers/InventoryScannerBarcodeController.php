<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\ProductIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryScannerBarcodeController extends Controller
{
    private function authorizeInventory(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $user->isOwner()), 403);
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorizeInventory($request);

        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json(['items' => []]);
        }

        $items = InventoryItem::query()
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
                $q->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('upc', 'like', $like)
                    ->orWhereHas('identities', fn ($identity) => $identity
                        ->whereIn('type', ProductIdentity::SCANNABLE_TYPES)
                        ->where('value', 'like', $like));
            })
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'sku', 'barcode', 'upc'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'upc' => $item->upc,
            ]);

        return response()->json(['items' => $items]);
    }

    public function attach(Request $request): JsonResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'barcode' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'in:barcode,upc'],
        ]);

        $barcode = trim($data['barcode']);
        $type = $data['type'] ?? 'barcode';
        $item = InventoryItem::findOrFail($data['product_id']);

        $this->assertBarcodeAvailable($barcode, $item->id);

        DB::transaction(function () use ($item, $barcode, $type, $request): void {
            if (blank($item->barcode) && blank($item->upc)) {
                $item->update([$type === 'upc' ? 'upc' : 'barcode' => $barcode]);
            }

            ProductIdentity::firstOrCreate(
                [
                    'product_id' => $item->id,
                    'vendor_id' => null,
                    'type' => $type,
                    'value' => $barcode,
                ],
                [
                    'times_confirmed' => 1,
                    'last_confirmed_at' => now(),
                    'auto_confidence' => 1,
                    'confirmed_by' => $request->user()->id,
                    'confirmed_at' => now(),
                ],
            );
        });

        return response()->json([
            'ok' => true,
            'message' => "{$barcode} added to {$item->name}",
            'item' => ['id' => $item->id, 'name' => $item->name, 'sku' => $item->sku],
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'in:barcode,upc'],
        ]);

        $barcode = trim($data['barcode']);
        $type = $data['type'] ?? 'barcode';
        $this->assertBarcodeAvailable($barcode);

        $item = DB::transaction(function () use ($data, $barcode, $type, $request) {
            $item = InventoryItem::create([
                'name' => trim($data['name']),
                $type === 'upc' ? 'upc' : 'barcode' => $barcode,
                'is_active' => true,
            ]);

            ProductIdentity::create([
                'product_id' => $item->id,
                'vendor_id' => null,
                'type' => $type,
                'value' => $barcode,
                'times_confirmed' => 1,
                'last_confirmed_at' => now(),
                'auto_confidence' => 1,
                'confirmed_by' => $request->user()->id,
                'confirmed_at' => now(),
            ]);

            return $item;
        });

        return response()->json([
            'ok' => true,
            'message' => "{$item->name} created and {$barcode} saved",
            'item' => ['id' => $item->id, 'name' => $item->name, 'sku' => $item->sku],
        ]);
    }

    public function listForItem(Request $request, int $item): JsonResponse
    {
        $this->authorizeInventory($request);
        $product = InventoryItem::findOrFail($item);

        $codes = collect();
        if (filled($product->barcode)) {
            $codes->push(['id' => null, 'type' => 'barcode', 'value' => $product->barcode, 'primary' => true]);
        }
        if (filled($product->upc)) {
            $codes->push(['id' => null, 'type' => 'upc', 'value' => $product->upc, 'primary' => true]);
        }

        $product->identities()
            ->whereIn('type', [ProductIdentity::TYPE_BARCODE, ProductIdentity::TYPE_UPC])
            ->orderBy('type')
            ->orderBy('value')
            ->get(['id', 'type', 'value'])
            ->each(function ($identity) use ($codes): void {
                if (! $codes->contains(fn ($code) => $code['type'] === $identity->type && $code['value'] === $identity->value)) {
                    $codes->push(['id' => $identity->id, 'type' => $identity->type, 'value' => $identity->value, 'primary' => false]);
                }
            });

        return response()->json([
            'item' => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku],
            'codes' => $codes->values(),
        ]);
    }

    public function remove(Request $request, int $identity): JsonResponse
    {
        $this->authorizeInventory($request);

        $record = ProductIdentity::query()
            ->whereIn('type', [ProductIdentity::TYPE_BARCODE, ProductIdentity::TYPE_UPC])
            ->findOrFail($identity);

        $record->delete();

        return response()->json(['ok' => true]);
    }

    private function assertBarcodeAvailable(string $barcode, ?int $allowedProductId = null): void
    {
        if ($barcode === '') {
            throw ValidationException::withMessages(['barcode' => 'Barcode cannot be empty.']);
        }

        $columnOwner = InventoryItem::query()
            ->where(function ($q) use ($barcode) {
                $q->where('barcode', $barcode)->orWhere('upc', $barcode);
            })
            ->when($allowedProductId, fn ($q) => $q->whereKeyNot($allowedProductId))
            ->first(['id', 'name']);

        if ($columnOwner) {
            throw ValidationException::withMessages([
                'barcode' => "This barcode already belongs to {$columnOwner->name}.",
            ]);
        }

        $identityOwner = ProductIdentity::query()
            ->whereIn('type', ProductIdentity::SCANNABLE_TYPES)
            ->where('value', $barcode)
            ->when($allowedProductId, fn ($q) => $q->where('product_id', '!=', $allowedProductId))
            ->with('product:id,name')
            ->first();

        if ($identityOwner) {
            throw ValidationException::withMessages([
                'barcode' => 'This barcode already belongs to ' . ($identityOwner->product?->name ?? 'another inventory item') . '.',
            ]);
        }
    }
}
