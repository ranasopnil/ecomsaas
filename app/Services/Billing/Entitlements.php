<?php

namespace App\Services\Billing;

use App\Exceptions\LimitReached;
use App\Exceptions\TenantContextMissing;
use App\Facades\Tenancy;
use App\Models\Addon;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\TenantEntitlement;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Answers "is this store allowed to do that?".
 *
 * Every ceiling and every paid feature is checked through here, so there is one
 * place to change when plans change.
 */
class Entitlements
{
    /** @var array<int, array<string, TenantEntitlement>> */
    protected array $cache = [];

    /** @var array<int, Collection<int, SubscriptionAddon>> */
    protected array $extras = [];

    /**
     * The ceiling for a counted feature. Null means no ceiling.
     */
    public function limit(string $feature): ?int
    {
        $this->assertKnown($feature);

        if (! $this->hasActiveSubscription()) {
            return 0;
        }

        $row = $this->rows()[$feature] ?? null;

        $limit = $row === null
            ? config("features.{$feature}.default")
            : ($row->enabled ? $row->limit_value : 0);

        // Extras bought on top stack onto the plan: a Starter shop with two
        // "+50 products" is allowed 150. Something staff granted by hand is
        // the last word and is left exactly as they set it.
        if (! $this->isOverride($row) && $limit !== null) {
            $limit += $this->extraUnits($feature);
        }

        return $this->cap($feature, $limit);
    }

    /**
     * Some features have a ceiling the platform itself sets, above any plan.
     * Null (no limit) becomes the ceiling; anything larger is brought down.
     */
    protected function cap(string $feature, ?int $limit): ?int
    {
        $max = config("features.{$feature}.max");

        if ($max === null) {
            return $limit;
        }

        return $limit === null ? (int) $max : min($limit, (int) $max);
    }

    /**
     * Whether a feature is switched on for this store.
     */
    public function allows(string $feature): bool
    {
        $this->assertKnown($feature);

        if (! $this->hasActiveSubscription()) {
            return false;
        }

        $row = $this->rows()[$feature] ?? null;

        // Something staff granted or withheld by hand is the last word.
        if ($this->isOverride($row)) {
            return (bool) $row->enabled;
        }

        $fromPlan = $row === null
            ? (bool) config("features.{$feature}.default")
            : (bool) $row->enabled;

        return $fromPlan || $this->hasExtraSwitch($feature);
    }

    /**
     * How much more of a counted thing this shop has bought on top of its
     * plan.
     */
    protected function extraUnits(string $feature): int
    {
        return (int) $this->addons()
            ->filter(fn (SubscriptionAddon $bought) => $bought->addon?->kind === Addon::KIND_UNITS
                && $bought->addon?->feature === $feature)
            ->sum(fn (SubscriptionAddon $bought) => (int) $bought->addon->unit_amount * $bought->quantity);
    }

    /**
     * Has this shop bought this feature on top of its plan?
     */
    protected function hasExtraSwitch(string $feature): bool
    {
        return $this->addons()->contains(fn (SubscriptionAddon $bought) => $bought->addon?->kind === Addon::KIND_SWITCH
            && $bought->addon?->feature === $feature);
    }

    /**
     * The extras this shop is paying for right now.
     *
     * @return Collection<int, SubscriptionAddon>
     */
    protected function addons()
    {
        $tenantId = Tenancy::id();

        if ($tenantId === null) {
            throw TenantContextMissing::for(SubscriptionAddon::class);
        }

        return $this->extras[$tenantId] ??= SubscriptionAddon::query()
            ->inForce()
            ->with('addon')
            ->get();
    }

    protected function isOverride(?TenantEntitlement $row): bool
    {
        return $row !== null && $row->source === TenantEntitlement::SOURCE_OVERRIDE;
    }

    /**
     * How many more the store may add. Null means no ceiling.
     */
    public function remaining(string $feature, int $currentUsage): ?int
    {
        $limit = $this->limit($feature);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $currentUsage);
    }

    /**
     * Stop here if adding this many would go past the plan.
     *
     * The caller passes the current count, because only the caller knows what
     * it is counting: Product::count(), Domain::count(), and so on.
     */
    public function ensureCanAdd(string $feature, int $currentUsage, int $adding = 1): void
    {
        $limit = $this->limit($feature);

        if ($limit === null) {
            return;
        }

        if ($currentUsage + $adding > $limit) {
            throw LimitReached::forLimit($feature, $limit);
        }
    }

    /**
     * Stop here if the feature is not in the plan.
     */
    public function ensureAllows(string $feature): void
    {
        if (! $this->allows($feature)) {
            throw LimitReached::forSwitch($feature);
        }
    }

    /**
     * Everything the store is allowed, for showing on a plan page.
     *
     * @return array<string, array{label: string, type: string, enabled: bool, limit: int|null}>
     */
    public function all(): array
    {
        $summary = [];

        foreach (config('features') as $feature => $definition) {
            $summary[$feature] = [
                'label' => $definition['label'],
                'type' => $definition['type'],
                'enabled' => $definition['type'] === 'switch'
                    ? $this->allows($feature)
                    : ($this->limit($feature) === null || $this->limit($feature) > 0),
                'limit' => $definition['type'] === 'limit' ? $this->limit($feature) : null,
            ];
        }

        return $summary;
    }

    /**
     * Called after a plan change, so the next check sees the new rows.
     */
    public function forget(?int $tenantId = null): void
    {
        if ($tenantId === null) {
            $this->cache = [];
            $this->extras = [];

            return;
        }

        unset($this->cache[$tenantId], $this->extras[$tenantId]);
    }

    protected function hasActiveSubscription(): bool
    {
        return Subscription::query()->active()->exists();
    }

    /**
     * @return array<string, TenantEntitlement>
     */
    protected function rows(): array
    {
        $tenantId = Tenancy::id();

        if ($tenantId === null) {
            throw TenantContextMissing::for(TenantEntitlement::class);
        }

        return $this->cache[$tenantId] ??= TenantEntitlement::query()
            ->get()
            ->keyBy('feature')
            ->all();
    }

    protected function assertKnown(string $feature): void
    {
        if (! array_key_exists($feature, config('features'))) {
            throw new InvalidArgumentException("[{$feature}] is not a known feature. Add it to config/features.php.");
        }
    }
}
