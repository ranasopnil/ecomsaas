<?php

use App\Facades\Tenancy;
use App\Http\Controllers\Admin\LoginController as AdminLoginController;
use App\Http\Controllers\Internal\DomainCheckController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Super\LoginController;
use App\Http\Middleware\EnsureCentralDomain;
use App\Http\Middleware\EnsureStoreDomain;
use App\Livewire\Admin\BrandIndex;
use App\Livewire\Admin\CategoryIndex;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\DomainIndex;
use App\Livewire\Admin\MailSettingsForm;
use App\Livewire\Admin\PaymentMethodsIndex;
use App\Livewire\Admin\ProductForm;
use App\Livewire\Admin\ProductIndex;
use App\Livewire\Admin\StockIndex;
use App\Livewire\Super\Dashboard;
use App\Livewire\Super\GatewayMatrix;
use App\Livewire\Super\PackageForm as SuperPackageForm;
use App\Livewire\Super\PackageIndex as SuperPackageIndex;
use App\Livewire\Super\ShopPayments;
use App\Livewire\Super\StoreIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Tenancy::check()) {
        return view('storefront.placeholder', ['store' => Tenancy::current()]);
    }

    return view('welcome');
});

/*
 * The shop itself.
 */
Route::middleware(EnsureStoreDomain::class)->group(function () {
    Route::get('/products/{slug}', [ProductController::class, 'show'])->name('storefront.product');
});

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

        Route::get('products', ProductIndex::class)->name('products.index');
        Route::get('products/new', ProductForm::class)->name('products.create');
        Route::get('products/{product}/edit', ProductForm::class)->name('products.edit');

        Route::get('stock', StockIndex::class)->name('stock.index');
        Route::get('categories', CategoryIndex::class)->name('categories.index');
        Route::get('brands', BrandIndex::class)->name('brands.index');
        Route::get('web-address', DomainIndex::class)->name('domains.index');
        Route::get('email', MailSettingsForm::class)->name('mail.edit');
        Route::get('payments', PaymentMethodsIndex::class)->name('payments.index');
    });
});
