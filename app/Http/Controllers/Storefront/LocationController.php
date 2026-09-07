<?php

namespace App\Http\Controllers\Storefront;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Services\Storefront\CustomerLocation;
use App\Services\Storefront\MapProviders;
use App\Services\Storefront\PlaceSearch;
use App\Support\GeoPoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The shopper saying where they are, so the shop can show only what actually
 * reaches them.
 */
class LocationController extends Controller
{
    public function store(Request $request, CustomerLocation $location): RedirectResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'label' => ['nullable', 'string', 'max:160'],
        ]);

        $point = GeoPoint::tryFrom($data['latitude'], $data['longitude']);

        if ($point === null) {
            return back();
        }

        $location->remember($point, $data['label'] ?? null);

        return back();
    }

    public function destroy(CustomerLocation $location): RedirectResponse
    {
        $location->forget();

        return back();
    }

    /**
     * Place names for the shopper's own location box.
     */
    public function search(Request $request, PlaceSearch $places, MapProviders $maps): JsonResponse
    {
        $shop = Tenancy::current();

        return response()->json($places->find(
            (string) $request->query('q', ''),
            $maps->forShop($shop),
            $shop->country_code,
        ));
    }
}
