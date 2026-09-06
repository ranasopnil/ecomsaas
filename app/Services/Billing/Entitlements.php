<?php

namespace App\Services\Billing;

use App\Exceptions\LimitReached;
use App\Exceptions\TenantContextMissing;
use App\Facades\Tenancy;
use App\Models\Subscription;
use App\Models\TenantEntitlement;
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

        if ($row === null) {
            return config("features.{$feature}.default");
        }

        return $row->enabled ? $row->limit_value : 0;
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

        if ($row === null) {
            return (bool) config("features.{$feature}.default");
        }

        return $row->enabled;
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

            return;
        }

        unset($this->cache[$tenantId]);
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
