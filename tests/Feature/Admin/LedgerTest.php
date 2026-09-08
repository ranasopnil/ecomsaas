<?php

namespace Tests\Feature\Admin;

use App\Exceptions\OrderStepRefused;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\LedgerIndex;
use App\Livewire\Admin\OrderIndex;
use App\Livewire\Admin\OrderShow;
use App\Models\Courier;
use App\Models\DeliveryArea;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OutboxEvent;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Accounts\Ledger;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Catalogue\ProductService;
use App\Services\Orders\OrderFlow;
use App\Services\Orders\PlaceOrder;
use App\Services\Payments\PaymentProcessor;
use App\Services\Storefront\Basket;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The shop's own book of money, and the cash a courier collects on its behalf.
 *
 * Two things are being proved. That money the shop takes is written into the
 * book once, whoever tells us about it and however many times. And that cash
 * a courier is holding is never counted as the shop's until somebody says it
 * arrived and how much of it there was.
 */
class LedgerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected User $shopkeeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create([
            'currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD',
        ]);

        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing([
            'products' => 50, 'online_payments' => true,
        ])->create());

        Entitlements::forget();
        Tenancy::set($this->store);

        PaymentMethod::create([
            'tenant_id' => $this->store->id, 'gateway' => 'cod', 'is_enabled' => true, 'position' => 0,
        ]);

        $this->shopkeeper = User::factory()->create([
            'tenant_id' => $this->store->id, 'name' => 'Jewel Rana',
        ]);

        $this->actingAs($this->shopkeeper);
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function ledger(): Ledger
    {
        return app(Ledger::class);
    }

    protected function flow(): OrderFlow
    {
        return app(OrderFlow::class);
    }

    protected function taka(string $amount): Money
    {
        return Money::fromDecimal($amount, 'BDT', 2);
    }

    /**
     * An order for 260 Taka: two bags of rice at 100, plus 60 delivery.
     */
    protected function anOrder(): Order
    {
        $created = app(ProductService::class)->create([
            'name' => 'Basmati rice '.uniqid(), 'regular_price' => '100', 'stock' => 50,
            'status' => Product::STATUS_ACTIVE, 'unit' => 'per kg',
        ]);

        $product = $created instanceof Product ? $created : $created->product;

        $area = DeliveryArea::firstOrCreate(
            ['tenant_id' => $this->store->id, 'name' => 'Mirpur'],
            ['latitude' => 23.8, 'longitude' => 90.4, 'radius_km' => 10, 'delivery_charge_minor' => 6000],
        );

        request()->setLaravelSession(app('session.store'));

        $basket = app(Basket::class);
        $basket->add($product->fresh()->variants->first(), 2);

        return app(PlaceOrder::class)->place(
            $basket,
            ['name' => 'A Shopper', 'phone' => '01711223344', 'address' => 'House 4, Mirpur'],
            'cod',
            $area,
        );
    }

    /**
     * An order taken all the way to the customer's door.
     */
    protected function aDeliveredOrder(): Order
    {
        $courier = Courier::create(['tenant_id' => $this->store->id, 'name' => 'Pathao Courier']);

        return $this->flow()->apply(
            $this->flow()->apply(
                $this->flow()->apply(
                    $this->flow()->apply($this->anOrder(), Order::STATUS_APPROVED),
                    Order::STATUS_PROCESSING,
                ),
                Order::STATUS_HANDED_OVER,
                ['courier_id' => $courier->id],
            ),
            Order::STATUS_DELIVERED,
        );
    }

    /*
     * ---------------------------------------------------------------
     * Cash a courier collected
     * ---------------------------------------------------------------
     */

    public function test_a_delivered_cash_order_is_waiting_on_the_courier(): void
    {
        $order = $this->aDeliveredOrder();

        $this->assertTrue($this->flow()->isWaitingForCash($order));
        $this->assertSame(Order::PAYMENT_ON_DELIVERY, $order->payment_status);

        // Delivered is not paid. The courier is holding the shop's money.
        $this->assertFalse($order->isPaid());
        $this->assertSame(26000, $this->ledger()->owedByCouriers()->minor);
        $this->assertSame(0, $this->ledger()->balance()->minor);
    }

    public function test_cash_handed_over_is_written_on_the_order_and_in_the_book(): void
    {
        $order = $this->aDeliveredOrder();

        $order = $this->flow()->cashFromCourier($order, $this->taka('260'), 'Friday settlement');

        $this->assertSame(26000, $order->cod_received_minor);
        $this->assertNotNull($order->cod_received_at);

        // Every taka of it is in, so the order is paid for.
        $this->assertTrue($order->isPaid());
        $this->assertNotNull($order->paid_at);
        $this->assertFalse($this->flow()->isWaitingForCash($order));

        $entry = LedgerEntry::where('kind', LedgerEntry::KIND_COD)->firstOrFail();

        $this->assertSame(LedgerEntry::IN, $entry->direction);
        $this->assertSame(26000, $entry->amount_minor);
        $this->assertSame('BDT', $entry->currency);
        $this->assertSame($order->id, $entry->order_id);
        $this->assertStringContainsString('Pathao Courier', $entry->description);
        $this->assertStringContainsString('Friday settlement', $entry->description);
        $this->assertSame('Jewel Rana', $entry->user_name);

        $this->assertSame(26000, $this->ledger()->balance()->minor);
        $this->assertSame(0, $this->ledger()->owedByCouriers()->minor);
    }

    public function test_the_history_says_the_cash_came_in(): void
    {
        $order = $this->flow()->cashFromCourier($this->aDeliveredOrder(), $this->taka('260'));

        $event = $order->events()->get()->last();

        $this->assertSame(OrderEvent::CASH_RECEIVED, $event->to_status);
        $this->assertSame('Cash received from Pathao Courier', $event->title());
        $this->assertStringContainsString('260.00', $event->note);

        // The order's own status is untouched: the parcel already arrived.
        $this->assertSame(Order::STATUS_DELIVERED, $order->status);
    }

    public function test_a_courier_that_hands_over_part_of_it_leaves_the_rest_owed(): void
    {
        $order = $this->aDeliveredOrder();

        $order = $this->flow()->cashFromCourier($order, $this->taka('200'), 'Rest to come');

        $this->assertSame(20000, $order->cod_received_minor);
        $this->assertSame(Order::PAYMENT_ON_DELIVERY, $order->payment_status, 'Part of the money is not paid.');
        $this->assertTrue($this->flow()->isWaitingForCash($order));
        $this->assertSame(6000, $this->ledger()->owedByCouriers()->minor);

        // And when the rest comes.
        $order = $this->flow()->cashFromCourier($order, $this->taka('60'));

        $this->assertSame(26000, $order->cod_received_minor);
        $this->assertTrue($order->isPaid());
        $this->assertSame(0, $this->ledger()->owedByCouriers()->minor);

        // Two lots of cash, two lines in the book.
        $this->assertSame(2, LedgerEntry::where('kind', LedgerEntry::KIND_COD)->count());
        $this->assertSame(26000, $this->ledger()->balance()->minor);
    }

    public function test_more_than_the_order_is_owed_is_refused(): void
    {
        $order = $this->aDeliveredOrder();

        $this->expectExceptionMessage('more than this order is owed');

        $this->flow()->cashFromCourier($order, $this->taka('500'));
    }

    public function test_cash_cannot_be_entered_before_the_parcel_has_arrived(): void
    {
        $order = $this->flow()->apply($this->anOrder(), Order::STATUS_APPROVED);

        $this->expectExceptionMessage('Mark it delivered first');

        $this->flow()->cashFromCourier($order, $this->taka('260'));
    }

    public function test_an_order_that_was_paid_online_has_no_courier_cash(): void
    {
        $order = $this->aDeliveredOrder();
        $order->forceFill(['payment_status' => Order::PAYMENT_PAID])->save();

        $this->expectException(OrderStepRefused::class);

        $this->flow()->cashFromCourier($order->fresh(), $this->taka('260'));
    }

    public function test_entering_the_cash_is_written_to_the_outbox_with_it(): void
    {
        $this->flow()->cashFromCourier($this->aDeliveredOrder(), $this->taka('260'));

        $this->assertSame(1, OutboxEvent::where('type', 'order.cash_received')->count());
    }

    /*
     * ---------------------------------------------------------------
     * Money the shop took online
     * ---------------------------------------------------------------
     */

    public function test_a_payment_that_completes_is_written_into_the_book_once(): void
    {
        Http::fake([
            '*/token/grant' => Http::response(['statusCode' => '0000', 'id_token' => 'stand-in']),
            '*/execute' => Http::response([
                'statusCode' => '0000', 'statusMessage' => 'Successful', 'paymentID' => 'TR0011abcdef',
                'trxID' => 'AAB12CD34E', 'transactionStatus' => 'Completed', 'customerMsisdn' => '01770618567',
            ]),
        ]);

        $method = new PaymentMethod([
            'tenant_id' => $this->store->id, 'gateway' => 'bkash', 'is_enabled' => true,
            'settings' => ['username' => 'tester', 'sandbox' => true],
        ]);
        $method->credentials = ['app_key' => 'key', 'app_secret' => 'secret', 'password' => 'password'];
        $method->save();

        $payment = Payment::factory()->initiated()->create(['tenant_id' => $this->store->id]);

        $settled = app(PaymentProcessor::class)->settle($payment, $method, 'success');
        $this->assertTrue($settled->isPaid());

        $entry = LedgerEntry::where('kind', LedgerEntry::KIND_PAYMENT)->firstOrFail();

        $this->assertSame(LedgerEntry::IN, $entry->direction);
        $this->assertSame($payment->amount_minor, $entry->amount_minor);
        $this->assertSame($payment->id, $entry->payment_id);
        $this->assertStringContainsString('bKash', $entry->description);

        // Told about it again — a reload, a second callback — and the book
        // still shows it once.
        app(PaymentProcessor::class)->settle($settled->fresh(), $method, 'success');

        $this->assertSame(1, LedgerEntry::where('kind', LedgerEntry::KIND_PAYMENT)->count());
        $this->assertSame($payment->amount_minor, $this->ledger()->balance()->minor);
    }

    /*
     * ---------------------------------------------------------------
     * The book itself
     * ---------------------------------------------------------------
     */

    public function test_a_shopkeeper_can_write_down_what_they_spent(): void
    {
        Livewire::test(LedgerIndex::class)
            ->call('add')
            ->set('direction', LedgerEntry::OUT)
            ->set('amount', '1500')
            ->set('description', 'Packing boxes')
            ->call('save')
            ->assertHasNoErrors();

        $entry = LedgerEntry::firstOrFail();

        $this->assertSame(LedgerEntry::OUT, $entry->direction);
        $this->assertSame(150000, $entry->amount_minor);
        $this->assertSame('Packing boxes', $entry->description);
        $this->assertSame(-150000, $this->ledger()->balance()->minor);
    }

    public function test_a_line_for_nothing_is_not_written(): void
    {
        Livewire::test(LedgerIndex::class)
            ->call('add')
            ->set('amount', '0')
            ->set('description', 'Nothing at all')
            ->call('save')
            ->assertHasErrors('amount');

        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_a_line_is_put_right_by_writing_its_opposite_not_by_changing_it(): void
    {
        $wrong = $this->ledger()->record($this->taka('1500'), [
            'direction' => LedgerEntry::OUT,
            'kind' => LedgerEntry::KIND_EXPENSE,
            'description' => 'Packing boxes',
        ]);

        Livewire::test(LedgerIndex::class)
            ->call('startReversing', $wrong->id)
            ->set('why', 'Entered twice by mistake')
            ->call('reverse')
            ->assertDispatched('toast');

        $correction = LedgerEntry::where('reverses_id', $wrong->id)->firstOrFail();

        // The original is exactly as it was.
        $this->assertSame('Packing boxes', $wrong->fresh()->description);
        $this->assertSame(150000, $wrong->fresh()->amount_minor);
        $this->assertSame(LedgerEntry::OUT, $wrong->fresh()->direction);

        // And the opposite sits beside it.
        $this->assertSame(LedgerEntry::IN, $correction->direction);
        $this->assertSame(150000, $correction->amount_minor);
        $this->assertStringContainsString('Entered twice by mistake', $correction->description);

        // Which brings the book back to nothing.
        $this->assertSame(0, $this->ledger()->balance()->minor);
        $this->assertSame(2, LedgerEntry::count());
    }

    public function test_a_line_is_never_put_right_twice(): void
    {
        $entry = $this->ledger()->record($this->taka('100'), [
            'direction' => LedgerEntry::OUT,
            'kind' => LedgerEntry::KIND_EXPENSE,
            'description' => 'Tea for the shop',
        ]);

        $this->assertNotNull($this->ledger()->reverse($entry, 'Wrong'));
        $this->assertNull($this->ledger()->reverse($entry, 'Wrong again'));
        $this->assertSame(2, LedgerEntry::count());
    }

    public function test_money_in_and_out_add_up_over_a_stretch_of_days(): void
    {
        $this->ledger()->record($this->taka('1000'), [
            'direction' => LedgerEntry::IN, 'kind' => LedgerEntry::KIND_OTHER_IN,
            'description' => 'Walk-in sale', 'occurred_on' => now()->toDateString(),
        ]);

        $this->ledger()->record($this->taka('400'), [
            'direction' => LedgerEntry::OUT, 'kind' => LedgerEntry::KIND_EXPENSE,
            'description' => 'Rickshaw fare', 'occurred_on' => now()->toDateString(),
        ]);

        // Last month's, which this month's figures must not include.
        $this->ledger()->record($this->taka('9999'), [
            'direction' => LedgerEntry::IN, 'kind' => LedgerEntry::KIND_OTHER_IN,
            'description' => 'Old money', 'occurred_on' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
        ]);

        $totals = $this->ledger()->totals(now()->startOfMonth(), now());

        $this->assertSame(100000, $totals['in']->minor);
        $this->assertSame(40000, $totals['out']->minor);
        $this->assertSame(60000, $totals['net']->minor);

        // The balance is everything, whenever it happened.
        $this->assertSame(100000 - 40000 + 999900, $this->ledger()->balance()->minor);
    }

    /*
     * ---------------------------------------------------------------
     * The screens
     * ---------------------------------------------------------------
     */

    public function test_the_order_screen_records_the_cash_the_courier_brought(): void
    {
        $order = $this->aDeliveredOrder();

        Livewire::test(OrderShow::class, ['order' => $order])
            ->assertSee('Waiting for the cash')
            ->assertSee('Pathao Courier')
            ->call('collect')
            // It opens with what is owed already filled in.
            ->assertSet('cash', '260.00')
            ->call('recordCash')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame(26000, (int) LedgerEntry::where('kind', LedgerEntry::KIND_COD)->sum('amount_minor'));
    }

    public function test_the_order_screen_refuses_more_than_is_owed(): void
    {
        $order = $this->aDeliveredOrder();

        Livewire::test(OrderShow::class, ['order' => $order])
            ->call('collect')
            ->set('cash', '5000')
            ->call('recordCash')
            ->assertHasErrors('cash');

        $this->assertSame(0, LedgerEntry::count());
        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_the_orders_list_shows_what_the_couriers_are_holding(): void
    {
        $order = $this->aDeliveredOrder();

        Livewire::test(OrderIndex::class)
            ->call('showing', 'cash')
            ->assertSee($order->reference)
            ->assertSee('Cash to collect');

        // Once the money is in, it drops off the list.
        $this->flow()->cashFromCourier($order, $this->taka('260'));

        Livewire::test(OrderIndex::class)
            ->call('showing', 'cash')
            ->assertDontSee($order->reference);
    }

    public function test_the_accounts_screen_shows_the_book(): void
    {
        $this->flow()->cashFromCourier($this->aDeliveredOrder(), $this->taka('260'));

        Livewire::test(LedgerIndex::class)
            ->assertSee('Money in')
            ->assertSee('Couriers are holding')
            ->assertSee('Cash from Pathao Courier')
            ->assertSee('260.00');
    }

    public function test_one_shop_never_sees_another_shops_book(): void
    {
        $this->ledger()->record($this->taka('1000'), [
            'direction' => LedgerEntry::IN, 'kind' => LedgerEntry::KIND_OTHER_IN,
            'description' => 'Mine alone',
        ]);

        $other = Tenant::factory()->create(['currency' => 'BDT', 'currency_exponent' => 2]);

        Tenancy::run($other, function () {
            $this->assertSame(0, LedgerEntry::count());
            $this->assertSame(0, app(Ledger::class)->balance()->minor);
        });
    }
}
