<?php

namespace App\Http\Controllers\Storefront;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Storefront\CustomerLocation;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * A customer looking at their own order.
 *
 * Most shoppers never sign in, so an order is opened with its reference and a
 * secret handed out when it was placed. The reference on its own is not
 * enough: order numbers run in sequence and would otherwise let anybody read
 * everybody's.
 */
class OrderController extends Controller
{
    public function show(
        Request $request,
        string $reference,
        CustomerLocation $location,
        TemplateCatalogue $templates,
    ): View {
        $order = Order::with(['lines'])->where('reference', $reference)->firstOrFail();

        abort_unless(
            hash_equals($order->view_token, (string) $request->query('token', '')),
            404,
        );

        $shop = Tenancy::current();

        return view('storefront.order', [
            'store' => $shop,
            'template' => config('templates.'.$templates->activeFor($shop)),
            'location' => $location,
            'searchUrl' => route('storefront.places'),
            'order' => $order,
        ]);
    }
}
