<?php

use App\Facades\Tenancy;
use App\Http\Controllers\Admin\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\PlaceSearchController;
use App\Http\Controllers\Internal\DomainCheckController;
use App\Http\Controllers\Payments\AmarPayCallbackController;
use App\Http\Controllers\Payments\BkashCallbackController;
use App\Http\Controllers\Payments\SslCommerzCallbackController;
use App\Http\Controllers\Payments\SslCommerzIpnController;
use App\Http\Controllers\Payments\StripeCallbackController;
use App\Http\Controllers\Payments\StripeWebhookController;
use App\Http\Controllers\Storefront\BasketController;
use App\Http\Controllers\Storefront\BrowseController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\LocationController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Super\LoginController;
use App\Http\Middleware\CountVisit;
use App\Http\Middleware\EnsureCentralDomain;
use App\Http\Middleware\EnsureStoreDomain;
use App\Livewire\Admin\BrandIndex;
use App\Livewire\Admin\CategoryIndex;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\DeliveryAreaForm;
use App\Livewire\Admin\DomainIndex;
use App\Livewire\Admin\FooterSettings;
use App\Livewire\Admin\MailSettingsForm;
use App\Livewire\Admin\OrderIndex;
use App\Livewire\Admin\OrderShow;
use App\Livewire\Admin\PaymentMethodsIndex;
use App\Livewire\Admin\ProductForm;
use App\Livewire\Admin\ProductIndex;
use App\Livewire\Admin\StockIndex;
use App\Livewire\Admin\TemplateIndex;
use App\Livewire\Super\Dashboard;
use App\Livewire\Super\GatewayMatrix;
use App\Livewire\Super\MapAccess;
use App\Livewire\Super\PackageForm as SuperPackageForm;
use App\Livewire\Super\PackageIndex as SuperPackageIndex;
use App\Livewire\Super\ShopPayments;
use App\Livewire\Super\StoreIndex;
use App\Livewire\Super\TemplateMatrix;
use Illuminate\Support\Facades\Route;

Route::get('/', function (HomeController $home) {
    if (Tenancy::check()) {
        return app()->call($home);
    }

    return view('welcome');
})->middleware(CountVisit::class)->name('storefront.home');

/*
 * The shop itself.
 */
Route::middleware([EnsureStoreDomain::class, CountVisit::class])->group(function () {
    Route::get('/browse', BrowseController::class)->name('storefront.browse');
    Route::get('/products/{slug}', [ProductController::class, 'show'])->name('storefront.product');
    Route::get('/basket', [BasketController::class, 'show'])->name('storefront.basket');
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('storefront.checkout');
    Route::get('/orders/{reference}', [OrderController::class, 'show'])->name('storefront.order');

    // The shop's own written pages — privacy, refunds, delivery, terms.
    // The list of addresses is fixed in StorefrontFooter::PAGES; a page the
    // shopkeeper has not written is simply not there.
    Route::get('/pages/{slug}', PageController::class)
        ->whereIn('slug', array_keys(App\Models\StorefrontFooter::PAGES))
        ->name('storefront.page');
});

/*
 * Picking things up and putting them back. Not counted as visits.
 */
Route::middleware(EnsureStoreDomain::class)->group(function () {
    Route::post('/basket/add', [BasketController::class, 'add'])->name('storefront.basket.add');
    Route::post('/basket/update', [BasketController::class, 'update'])->name('storefront.basket.update');
    Route::post('/basket/remove', [BasketController::class, 'remove'])->name('storefront.basket.remove');

    // Placing the order. Throttled: this takes stock off the shelf.
    Route::post('/checkout', [CheckoutController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('storefront.checkout.place');
});

/*
 * The shopper saying where they are. Not counted as a visit, and the place
 * search is throttled because it stands in front of a free service.
 */
Route::middleware(EnsureStoreDomain::class)->group(function () {
    Route::post('/where-i-am', [LocationController::class, 'store'])->name('storefront.location.store');
    Route::post('/where-i-am/forget', [LocationController::class, 'destroy'])->name('storefront.location.forget');
    Route::get('/places', [LocationController::class, 'search'])
        ->middleware('throttle:30,1')
        ->name('storefront.places');

    // What to offer while somebody is still typing. Throttled: it is asked
    // once every few keystrokes and answers from this shop only.
    Route::get('/search/suggestions', [SearchController::class, 'suggest'])
        ->middleware('throttle:120,1')
        ->name('storefront.search.suggest');
});

/*
 * Where a gateway sends the customer back to. On the shop's own address, so
 * the shop is already known, and never counted as a visit.
 */
Route::middleware(EnsureStoreDomain::class)->group(function () {
    Route::get('/payments/bkash/callback', BkashCallbackController::class)->name('payments.bkash.callback');
    Route::get('/payments/stripe/callback', StripeCallbackController::class)->name('payments.stripe.callback');

    // SSLCommerz and AmarPay post the customer back rather than sending them.
    Route::post('/payments/sslcommerz/callback', SslCommerzCallbackController::class)
        ->name('payments.sslcommerz.callback');
    Route::post('/payments/amarpay/callback', AmarPayCallbackController::class)
        ->name('payments.amarpay.callback');
});

/*
 * What Stripe tells the shop directly, without a customer in the middle.
 * Signed by the shop's own signing secret and checked before anything is
 * believed; see StripeWebhookController.
 */
Route::post('/payments/stripe/webhook', StripeWebhookController::class)
    ->middleware(EnsureStoreDomain::class)
    ->name('payments.stripe.webhook');

/*
 * The same thing from SSLCommerz, signed with the shop's own store password
 * and checked before anything is believed; see SslCommerzIpnController.
 */
Route::post('/payments/sslcommerz/ipn', SslCommerzIpnController::class)
    ->middleware(EnsureStoreDomain::class)
    ->name('payments.sslcommerz.ipn');

/*
 * Caddy asks this before issuing a certificate for a hostname.
 * Local callers only; see config/tenancy.php.
 */
Route::get('/internal/domain-check', DomainCheckController::class)
    ->name('internal.domain-check');

/*
 * The super admin. Platform staff only, and only on the platform's own
 * address — never on a merchant's shop address.
 */
Route::prefix('super')->name('super.')->middleware(EnsureCentralDomain::class)->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/', Dashboard::class)->name('dashboard');

        Route::get('plans', SuperPackageIndex::class)->name('packages.index');
        Route::get('plans/new', SuperPackageForm::class)->name('packages.create');
        Route::get('plans/{package}/edit', SuperPackageForm::class)->name('packages.edit');

        Route::get('shops', StoreIndex::class)->name('stores.index');
        Route::get('shops/{tenant}/payments', ShopPayments::class)->name('stores.payments');
        Route::get('payment-gateways', GatewayMatrix::class)->name('gateways.index');
        Route::get('templates', TemplateMatrix::class)->name('templates.index');
        Route::get('maps', MapAccess::class)->name('maps.index');
    });
});

/*
 * A merchant's own admin, on their shop address only.
 */
Route::prefix('admin')->name('admin.')->middleware(EnsureStoreDomain::class)->group(function () {
    Route::middleware('guest:web')->group(function () {
        Route::get('login', [AdminLoginController::class, 'show'])->name('login');
        Route::post('login', [AdminLoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:web')->group(function () {
        Route::post('logout', [AdminLoginController::class, 'destroy'])->name('logout');

        Route::get('/', AdminDashboard::class)->name('dashboard');

        Route::get('orders', OrderIndex::class)->name('orders.index');
        Route::get('orders/{order}', OrderShow::class)->name('orders.show');

        Route::get('products', ProductIndex::class)->name('products.index');
        Route::get('products/new', ProductForm::class)->name('products.create');
        Route::get('products/{product}/edit', ProductForm::class)->name('products.edit');

        Route::get('stock', StockIndex::class)->name('stock.index');
        Route::get('categories', CategoryIndex::class)->name('categories.index');
        Route::get('brands', BrandIndex::class)->name('brands.index');
        Route::get('web-address', DomainIndex::class)->name('domains.index');
        Route::get('email', MailSettingsForm::class)->name('mail.edit');
        Route::get('payments', PaymentMethodsIndex::class)->name('payments.index');
        Route::get('shop-look', TemplateIndex::class)->name('templates.index');
        Route::get('delivery-area', DeliveryAreaForm::class)->name('delivery.edit');
        Route::get('footer', FooterSettings::class)->name('footer.edit');

        // Asked by the map picker as the shopkeeper types.
        Route::get('places', PlaceSearchController::class)->name('places.search');
    });
});
