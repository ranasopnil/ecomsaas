<?php

namespace App\Http\Controllers\Storefront;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\Storefront\Basket;
use App\Services\Storefront\CustomerLocation;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The shopper's basket: what they have picked up, and changing their mind.
 *
 * Every variant is found through the shop's own scope, so a shopper can only
 * ever put this shop's things in this shop's basket.
 */
class BasketController extends Controller
{
    public function show(Basket $basket, CustomerLocation $location, TemplateCatalogue $templates): View
    {
        $shop = Tenancy::current();

        return view('storefront.basket', [
            'store' => $shop,
            'template' => config('templates.'.$templates->activeFor($shop)),
            'location' => $location,
            'searchUrl' => route('storefront.places'),
            'lines' => $basket->lines(),
            'subtotal' => $basket->subtotal(),
        ]);
    }

    public function add(Request $request, Basket $basket): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.Basket::MAX_PER_LINE],
            // "Buy now": put it in and go straight to the till.
            'then' => ['nullable', 'in:checkout'],
        ]);

        $variant = $this->sellable((int) $data['variant_id']);

        if ($variant === null) {
            $refused = 'That is not available right now.';

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $refused, 'count' => $basket->count()], 422)
                : back()->with('basket.refused', $refused);
        }

        $basket->add($variant, (int) ($data['quantity'] ?? 1));

        // Asked for by the page itself: answer with the new state and let it
        // update in place, rather than sending the shopper back to the top of
        // a reloaded page.
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'name' => $variant->product->name,
                'count' => $basket->count(),
                'checkout' => ($data['then'] ?? null) === 'checkout' ? route('storefront.checkout') : null,
            ]);
        }

        if (($data['then'] ?? null) === 'checkout') {
            return redirect()->route('storefront.checkout');
        }

        return back()->with('basket.added', $variant->product->name);
    }

    public function update(Request $request, Basket $basket): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:0', 'max:'.Basket::MAX_PER_LINE],
        ]);

        $variant = ProductVariant::query()->find((int) $data['variant_id']);

        if ($variant !== null) {
            $basket->set($variant, (int) $data['quantity']);
        }

        return back();
    }

    public function remove(Request $request, Basket $basket): RedirectResponse
    {
        $data = $request->validate(['variant_id' => ['required', 'integer']]);

        $basket->remove((int) $data['variant_id']);

        return back();
    }

    /**
     * The variant, only if this shop is selling it right now and has it.
     */
    protected function sellable(int $variantId): ?ProductVariant
    {
        $variant = ProductVariant::query()->with(['product', 'inventory'])->find($variantId);

        if ($variant === null || ! $variant->product?->isOnSale() || ! Basket::canSell($variant)) {
            return null;
        }

        return $variant;
    }
}
