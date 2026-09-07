<?php

namespace App\Http\Controllers\Admin;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Services\Storefront\MapProviders;
use App\Services\Storefront\PlaceSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Turning what a shopkeeper typed into places on a map.
 *
 * Asked from our own server rather than the browser, so the free map service
 * sees one polite caller and the same search is not repeated all day.
 */
class PlaceSearchController extends Controller
{
    public function __invoke(Request $request, PlaceSearch $places, MapProviders $maps): JsonResponse
    {
        $shop = Tenancy::current();

        $results = $places->find(
            (string) $request->query('q', ''),
            $maps->forShop($shop),
            $shop->country_code,
        );

        return response()->json($results);
    }
}
