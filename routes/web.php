<?php

use App\Facades\Tenancy;
use App\Http\Controllers\Internal\DomainCheckController;
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
