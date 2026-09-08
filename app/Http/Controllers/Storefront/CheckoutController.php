<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\GatewayFailed;
use App\Exceptions\OrderRefused;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Orders\DeliveryQuote;
use App\Services\Orders\PlaceOrder;
use App\Services\Payments\GatewayFactory;
use App\Services\Payments\PaymentProcessor;
use App\Services\Storefront\Basket;
use App\Services\Storefront\CustomerLocation;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/**
 * Checkout: who the customer is, where it goes, and how they are paying.
 *
 * Nothing here decides a price. The order is priced by PlaceOrder, from the
 * shop's own prices at the moment it is placed, so nothing typed into this
 * form can change what anything costs.
 */
class CheckoutController extends Controller
{
    public function show(
        Basket $basket,
        CustomerLocation $location,
        DeliveryQuote $quote,
        TemplateCatalogue $templates,
    ): View|RedirectResponse {
        if ($basket->isEmpty()) {
            return redirect()->route('storefront.basket');
        }

        $shop = Tenancy::current();
        $lines = $basket->lines();
        $area = $quote->areaFor($location->point());

        return view('storefront.checkout', [
            'store' => $shop,
            'template' => config('templates.'.$templates->activeFor($shop)),
            'location' => $location,
            'searchUrl' => route('storefront.places'),
            'lines' => $lines,
            'goods' => $basket->subtotal(),
            'areas' => $quote->choices(),
            'area' => $area,
            'delivery' => $quote->for($lines, $area),
            'breakdown' => $quote->breakdown($lines),
            'ways' => $this->waysToPay(),
        ]);
    }

    public function store(
        Request $request,
        Basket $basket,
        CustomerLocation $location,
        PlaceOrder $orders,
        PaymentProcessor $payments,
    ): RedirectResponse {
        $ways = $this->waysToPay();

        if ($ways->isEmpty()) {
            return back()->withErrors(['payment' => 'This shop cannot take payments yet.'])->withInput();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
            'delivery_area_id' => ['nullable', 'integer'],
            'payment' => ['required', Rule::in($ways->pluck('gateway')->all())],
        ]);

        // Only this shop's own areas, so an id from anywhere else is ignored.
        $area = $data['delivery_area_id'] ?? null
            ? DeliveryArea::find((int) $data['delivery_area_id'])
            : null;

        try {
            $order = $orders->place(
                $basket,
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'address' => $data['address'],
                    'note' => $data['note'] ?? null,
                ],
                $data['payment'],
                $area,
                $location->point(),
            );
        } catch (OrderRefused $e) {
            return back()->withErrors(['basket' => $e->getMessage()])->withInput();
        }

        // Cash on delivery: nothing more to do, the order stands.
        if ($order->payment_gateway === 'cod') {
            $basket->clear();

            return redirect()->to($this->orderUrl($order));
        }

        $method = $ways->firstWhere('gateway', $order->payment_gateway);

        try {
            $started = $payments->start(
                $method,
                $order->total,
                $this->callbackUrl($order->payment_gateway),
                ['order_id' => $order->id, 'payer_reference' => $order->reference],
            );
        } catch (GatewayFailed $e) {
            // The order exists but cannot be paid for. Put the stock back
            // rather than leaving it held by an order nobody can complete.
            app(\App\Services\Orders\SettleOrder::class)->cancelled($order, $e->getMessage());

            return back()->withErrors(['payment' => $e->getMessage()])->withInput();
        }

        $order->forceFill(['payment_id' => $started['payment']->id])->save();

        // The basket is only emptied once there is an order holding the stock.
        $basket->clear();

        return redirect()->away($started['redirect_url']);
    }

    /**
     * The ways this shop can actually take money right now.
     *
     * A gateway the shop has switched on but not finished setting up is not
     * offered: showing it would send a customer to a page that cannot work.
     *
     * @return \Illuminate\Support\Collection<int, PaymentMethod>
     */
    protected function waysToPay()
    {
        $drivers = app(GatewayFactory::class);

        return PaymentMethod::orderBy('position')->orderBy('id')->get()
            ->filter(fn (PaymentMethod $method) => $method->isReady())
            ->filter(fn (PaymentMethod $method) => ($method->definition()['kind'] ?? null) === 'offline'
                || $drivers->isDriven($method->gateway))
            ->values();
    }

    /**
     * Where this gateway sends the customer back to.
     *
     * Every driven gateway has its own return address. A gateway without one
     * cannot be paid through, and saying so here stops a customer being sent
     * somewhere that could never bring them back.
     */
    protected function callbackUrl(string $gateway): string
    {
        $name = "payments.{$gateway}.callback";

        if (! Route::has($name)) {
            throw GatewayFailed::notConfigured($gateway);
        }

        return route($name);
    }

    protected function orderUrl(Order $order): string
    {
        return route('storefront.order', ['reference' => $order->reference]).'?token='.$order->view_token;
    }
}
