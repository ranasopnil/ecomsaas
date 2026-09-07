<?php

namespace App\Livewire\Admin;

use App\Facades\Tenancy;
use App\Services\Storefront\TemplateCatalogue;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Choosing how the shop looks to customers.
 *
 * Every template is shown, including the ones this shop's plan does not
 * include — a shopkeeper should be able to see what they would get.
 */
#[Layout('layouts.admin')]
#[Title('Shop look')]
class TemplateIndex extends Component
{
    public ?string $justChanged = null;

    public function choose(string $template): void
    {
        $catalogue = app(TemplateCatalogue::class);
        $shop = Tenancy::current();

        if ($catalogue->find($template) === null) {
            return;
        }

        if (! $catalogue->mayUse($shop, $template)) {
            $this->dispatch('toast', [
                'text' => $catalogue->find($template)['name'].' is not in your plan. Move up a plan to use it.',
                'tone' => 'bad',
            ]);

            return;
        }

        $catalogue->choose($shop, $template);
        Tenancy::set($shop->fresh());

        $this->justChanged = $template;
        $this->dispatch('toast', [
            'text' => 'Your shop now uses the '.$catalogue->find($template)['name'].' look.',
            'tone' => 'ok',
        ]);
    }

    public function render()
    {
        return view('livewire.admin.template-index', [
            'templates' => app(TemplateCatalogue::class)->forShop(Tenancy::current()),
        ]);
    }
}
