<?php

namespace App\Http\Controllers\Storefront;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\StorefrontFooter;
use App\Services\Storefront\CustomerLocation;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One of the pages a shopkeeper wrote for their customers — privacy, refunds,
 * delivery, terms, about us.
 *
 * The addresses are a fixed list held on the footer, not anything a merchant
 * chooses, and what they typed is shown as plain text. A page they have not
 * written does not exist.
 */
class PageController extends Controller
{
    public function __invoke(string $slug, CustomerLocation $location, TemplateCatalogue $templates): View
    {
        $shop = Tenancy::current();
        $footer = StorefrontFooter::forShop();
        $page = $footer->page($slug);

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        return view('storefront.page', [
            'store' => $shop,
            'template' => config('templates.'.$templates->activeFor($shop)),
            'location' => $location,
            'searchUrl' => route('storefront.places'),
            'page' => $page,
            'others' => $footer->writtenPages()->reject(fn (array $other) => $other['slug'] === $slug)->values(),
            'updatedAt' => $footer->updated_at,
        ]);
    }
}
