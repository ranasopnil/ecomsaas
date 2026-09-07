<?php

namespace App\Livewire\Super;

use App\Models\Package;
use App\Services\Storefront\TemplateCatalogue;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Which shop front each plan includes. Shops see every template either way;
 * this decides which ones they may actually switch to.
 */
#[Layout('layouts.super')]
#[Title('Shop templates')]
class TemplateMatrix extends Component
{
    public string $message = '';

    public function toggle(string $template, int $packageId): void
    {
        $catalogue = app(TemplateCatalogue::class);
        $package = Package::find($packageId);

        if ($package === null || $catalogue->find($template) === null) {
            return;
        }

        if ($catalogue->isAlwaysIncluded($template)) {
            $this->message = $catalogue->find($template)['name'].' comes with every plan, so it cannot be taken away.';

            return;
        }

        $now = ! $catalogue->packageIncludes($package, $template);
        $catalogue->setIncluded($package, $template, $now);

        $this->message = $catalogue->find($template)['name'].' is '
            .($now ? 'now included in ' : 'no longer included in ').$package->name.'.';
    }

    public function render()
    {
        $packages = Package::orderBy('id')->get();

        return view('livewire.super.template-matrix', [
            'templates' => app(TemplateCatalogue::class)->all(),
            'packages' => $packages,
            'grid' => app(TemplateCatalogue::class)->grid($packages),
        ]);
    }
}
