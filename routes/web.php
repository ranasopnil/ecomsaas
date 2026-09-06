<?php

use App\Facades\Tenancy;
use App\Http\Controllers\Internal\DomainCheckController;
use App\Http\Controllers\Super\LoginController;
use App\Http\Middleware\EnsureCentralDomain;
use App\Livewire\Super\Dashboard;
use App\Livewire\Super\PackageForm;
use App\Livewire\Super\PackageIndex;
use App\Livewire\Super\StoreIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Tenancy::check()) {
        return view('storefront.placeholder', ['store' => Tenancy::current()]);
    }

    return view('welcome');
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

        Route::get('plans', PackageIndex::class)->name('packages.index');
        Route::get('plans/new', PackageForm::class)->name('packages.create');
        Route::get('plans/{package}/edit', PackageForm::class)->name('packages.edit');

        Route::get('shops', StoreIndex::class)->name('stores.index');
    });
});
