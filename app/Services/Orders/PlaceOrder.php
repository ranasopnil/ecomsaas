<?php

namespace App\Services\Orders;

use App\Exceptions\OrderRefused;
use App\Facades\Tenancy;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OutboxEvent;
use App\Services\Catalogue\InventoryService;
use App\Services\Storefront\Basket;
use App\Services\Storefront\BasketLine;
use App\Support\GeoPoint;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turning a basket into an order.
 *
 * Everything happens in one transaction: the order, its lines, and the stock
 * coming off the shelf. If any one of them cannot be done, none of them is.
 * There is no state in which a customer has an order for something the shop
 * does not have.
 *
 * Prices are read from the shop at this moment and copied onto the lines. From
 * then on the order says what was agreed, whatever anybody changes afterwards.
 */
class PlaceOrder
{
    public function __construct(
        protected DeliveryQuote $delivery,
        protected InventoryService $stock,
    ) {}

    /**
     * @param  array{name: string, phone: string, address: string, note?: string|null}  $customer
     * @param  string  $gateway  which way they are paying: 'cod', 'bkash', …
     */
    public function place(
        Basket $basket,
        array $customer,
        string $gateway,
        ?DeliveryArea $area,
        ?GeoPoint $at = null,
    ): Order {
        $shop = Tenancy::current();
        $lines = $basket->lines();

        if ($lines->isEmpty()) {
            throw OrderRefused::emptyBasket();
        }

        $goods = $this->goodsTotal($lines);
        $charge = $this->delivery->for($lines, $area);

        // Cash on delivery is a real order the moment it is placed. Anything
        // paid online is not an order until the money is there.
        $paysNow = $gateway !== 'cod';

        return DB::transaction(function () use ($shop, $lines, $goods, $charge, $customer, $gateway, $area, $at, $paysNow) {
            $order = Order::create([
                'tenant_id' => $shop->id,
                'reference' => Order::newReference(),
                'view_token' => Str::random(40),
                'customer_name' => $customer['name'],
                'customer_phone' => $customer['phone'],
                'customer_address' => $customer['address'],
                'customer_note' => $customer['note'] ?? null,
                'delivery_area_id' => $area?->id,
                'delivery_area_name' => $area?->name,
                'latitude' => $at?->latitude,
                'longitude' => $at?->longitude,
                'payment_gateway' => $gateway,
                'goods_minor' => $goods->minor,
                'delivery_minor' => $charge->minor,
                'total_minor' => $goods->plus($charge)->minor,
                'currency' => $shop->currency,
                'currency_exponent' => $shop->currency_exponent,
                'status' => $paysNow ? Order::STATUS_PENDING_PAYMENT : Order::STATUS_PLACED,
                'payment_status' => $paysNow ? Order::PAYMENT_UNPAID : Order::PAYMENT_ON_DELIVERY,
                'placed_at' => $paysNow ? null : now(),
            ]);

            foreach ($lines as $line) {
                OrderLine::create([
                    'tenant_id' => $shop->id,
                    'order_id' => $order->id,
                    'product_id' => $line->product->id,
                    'product_variant_id' => $line->variant->id,
                    'name' => $line->product->name,
                    'variant_name' => $line->product->has_variants ? $line->variant->name : null,
                    'unit' => $line->product->unit,
                    'unit_price_minor' => $line->unit()->minor,
                    'quantity' => $line->quantity,
                    'line_total_minor' => $line->total()->minor,
                    'currency' => $shop->currency,
                    'currency_exponent' => $shop->currency_exponent,
                ]);

                // The check and the subtraction are one statement in the
                // database, so two shoppers cannot both take the last one.
                if (! $this->stock->reserve($line->variant, $line->quantity, $order->reference)) {
                    throw OrderRefused::outOfStock($line->product->name);
                }
            }

            // Written with the order, not after it: a queue can lose a job,
            // this table cannot.
            OutboxEvent::create([
                'tenant_id' => $shop->id,
                'type' => $paysNow ? 'order.awaiting_payment' : 'order.placed',
                'payload' => ['order_id' => $order->id, 'reference' => $order->reference],
                'available_at' => now(),
            ]);

            return $order->refresh();
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, BasketLine>  $lines
     */
    protected function goodsTotal($lines): Money
    {
        return $lines->skip(1)->reduce(
            fn (Money $sum, BasketLine $line) => $sum->plus($line->total()),
            $lines->first()->total(),
        );
    }
}
