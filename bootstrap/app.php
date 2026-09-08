<?php

use App\Facades\Tenancy;
use App\Http\Middleware\EnsureCentralDomain;
use App\Http\Middleware\EnsureStoreDomain;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', IdentifyTenant::class);

        // A gateway posting to a webhook has no session and no token to send.
        // These addresses prove themselves with a signature instead.
        $middleware->validateCsrfTokens(except: [
            'payments/*/webhook',
            'payments/*/ipn',
            // Some gateways post the customer back rather than sending them,
            // from their own page, with no session and no token of ours.
            'payments/*/callback',
        ]);

        $middleware->alias([
            'tenant' => IdentifyTenant::class,
            'central' => EnsureCentralDomain::class,
            'store' => EnsureStoreDomain::class,
        ]);

        // Which shop a request belongs to must be settled before anything
        // else runs, and "is this the platform's own address?" must be
        // answered before anything asks who is signed in — otherwise a shop
        // address would reveal that a staff page exists there at all.
        $middleware->prependToPriorityList(
            before: EncryptCookies::class,
            prepend: IdentifyTenant::class,
        );

        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureCentralDomain::class,
        );

        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureStoreDomain::class,
        );

        // Send someone who is not signed in to the right sign-in page: the
        // shop's own when they are on a shop address, the staff one otherwise.
        $middleware->redirectGuestsTo(fn () => Tenancy::check()
            ? route('admin.login')
            : route('super.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
