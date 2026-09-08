<?php

namespace App\Services\Billing;

use App\Facades\Entitlements;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalogue\ImageService;

/**
 * How much of its plan a shop has actually used.
 *
 * Counted from the shop's own records every time it is asked, so the figure a
 * shopkeeper reads is the figure the platform enforces. Nothing here is
 * cached: a number that is nearly full has to be nearly full.
 */
class Usage
{
    public function __construct(protected ImageService $images) {}

    /**
     * Every counted allowance, with what is used and what is allowed.
     *
     * @return array<int, array{feature: string, label: string, used: int, allowance: ?int, share: ?float, full: bool}>
     */
    public function counted(): array
    {
        $rows = [];

        foreach (config('features') as $feature => $definition) {
            if ($definition['type'] !== 'limit') {
                continue;
            }

            $used = $this->used($feature);
            $allowance = Entitlements::limit($feature);

            $rows[] = [
                'feature' => $feature,
                'label' => $definition['label'],
                'used' => $used,
                'allowance' => $allowance,
                'share' => $allowance === null || $allowance === 0 ? null : min(1, $used / $allowance),
                'full' => $allowance !== null && $used >= $allowance,
            ];
        }

        return $rows;
    }

    /**
     * The features that are simply on or off.
     *
     * @return array<int, array{feature: string, label: string, on: bool}>
     */
    public function switches(): array
    {
        $rows = [];

        foreach (config('features') as $feature => $definition) {
            if ($definition['type'] !== 'switch') {
                continue;
            }

            $rows[] = [
                'feature' => $feature,
                'label' => $definition['label'],
                'on' => Entitlements::allows($feature),
            ];
        }

        return $rows;
    }

    /**
     * How much of one counted thing this shop is using.
     */
    public function used(string $feature): int
    {
        return match ($feature) {
            'products' => Product::query()->count(),
            'staff_accounts' => User::query()->count(),
            // Its own web addresses, not the one the platform gave it, and
            // not the www copy of one it already has.
            'custom_domains' => Domain::query()->where('type', Domain::TYPE_CUSTOM)->get()
                ->reject(fn (Domain $domain) => str_starts_with($domain->hostname, 'www.'))
                ->count(),
            'storage_mb' => (int) ceil($this->images->storageUsedMb()),
            'orders_per_month' => Order::query()
                ->whereNotIn('status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_CANCELLED])
                ->where('placed_at', '>=', now()->startOfMonth())
                ->count(),
            default => 0,
        };
    }

    /**
     * What this shop would be over, if it moved to a plan with these
     * ceilings. Empty when everything fits.
     *
     * @param  array<string, ?int>  $allowances  feature => ceiling, null for no ceiling
     * @return array<int, array{label: string, used: int, allowance: int}>
     */
    public function overshoot(array $allowances): array
    {
        $over = [];

        foreach ($allowances as $feature => $allowance) {
            if ($allowance === null) {
                continue;
            }

            $used = $this->used($feature);

            if ($used > $allowance) {
                $over[] = [
                    'label' => config("features.{$feature}.label", $feature),
                    'used' => $used,
                    'allowance' => $allowance,
                ];
            }
        }

        return $over;
    }
}
