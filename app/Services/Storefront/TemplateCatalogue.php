<?php

namespace App\Services\Storefront;

use App\Facades\Tenancy;
use App\Models\Package;
use App\Models\PackageTemplate;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Which shop fronts exist, which plans include them, and which one a shop is
 * actually using.
 *
 * A shop always sees every template. It can only choose the ones its plan
 * includes, so the rest read as something to upgrade for rather than something
 * hidden away.
 */
class TemplateCatalogue
{
    /** Every plan includes this one, so no shop is ever left without a shop front. */
    public const FALLBACK = 'classic';

    /**
     * @return Collection<string, array<string, mixed>>
     */
    public function all(): Collection
    {
        return collect(config('templates'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $template): ?array
    {
        return config("templates.{$template}");
    }

    public function isAlwaysIncluded(string $template): bool
    {
        return (bool) ($this->find($template)['always'] ?? false);
    }

    /**
     * @return array<int, string>
     */
    public function includedIn(?Package $package): array
    {
        $always = $this->all()->filter(fn (array $t) => $t['always'] ?? false)->keys()->all();

        if ($package === null) {
            return $always;
        }

        return array_values(array_unique([...$always, ...$package->templates()->pluck('template')->all()]));
    }

    public function packageIncludes(Package $package, string $template): bool
    {
        return in_array($template, $this->includedIn($package), true);
    }

    public function setIncluded(Package $package, string $template, bool $included): void
    {
        // A template every plan already includes cannot be taken away, or a
        // shop could be left with nothing to show customers.
        if ($this->find($template) === null || $this->isAlwaysIncluded($template)) {
            return;
        }

        if ($included) {
            PackageTemplate::firstOrCreate(['package_id' => $package->id, 'template' => $template]);

            return;
        }

        PackageTemplate::where('package_id', $package->id)->where('template', $template)->delete();
    }

    /**
     * The whole included/not-included grid staff see.
     *
     * @param  Collection<int, Package>  $packages
     * @return array<string, array<int, bool>> template => package id => included
     */
    public function grid(Collection $packages): array
    {
        $rows = PackageTemplate::all()->groupBy('package_id');
        $grid = [];

        foreach ($this->all() as $template => $definition) {
            foreach ($packages as $package) {
                $grid[$template][$package->id] = ($definition['always'] ?? false)
                    || (bool) $rows->get($package->id)?->contains('template', $template);
            }
        }

        return $grid;
    }

    /**
     * Every template, each marked with whether this shop's plan includes it and
     * whether it is the one in use.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function forShop(Tenant $tenant): Collection
    {
        $included = $this->includedIn($this->packageOf($tenant));
        $active = $this->activeFor($tenant);

        return $this->all()->map(function (array $definition, string $template) use ($included, $active) {
            $definition['included'] = in_array($template, $included, true);
            $definition['chosen'] = $active === $template;

            return $definition;
        });
    }

    public function mayUse(Tenant $tenant, string $template): bool
    {
        return $this->find($template) !== null
            && in_array($template, $this->includedIn($this->packageOf($tenant)), true);
    }

    /**
     * The template the shop front should actually render.
     *
     * A shop that chose one and later dropped to a plan without it falls back
     * rather than breaking. Losing a template must never mean losing the shop.
     */
    public function activeFor(Tenant $tenant): string
    {
        return $tenant->template !== null && $this->mayUse($tenant, $tenant->template)
            ? $tenant->template
            : self::FALLBACK;
    }

    public function choose(Tenant $tenant, string $template): bool
    {
        if (! $this->mayUse($tenant, $template)) {
            return false;
        }

        $tenant->forceFill(['template' => $template])->save();

        return true;
    }

    protected function packageOf(Tenant $tenant): ?Package
    {
        return Tenancy::run($tenant, fn () => Subscription::query()
            ->active()
            ->with('package')
            ->latest('id')
            ->first()?->package);
    }
}
