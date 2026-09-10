<?php

namespace App\Services\Billing;

use App\Models\Package;
use App\Models\PackageEntitlement;
use App\Models\PlanFeature;
use App\Models\PlanFeatureGroup;
use App\Models\PlanFeatureValue;
use Illuminate\Support\Collection;

/**
 * The plans table, built once and read by both the shop owner and staff.
 *
 * A cell is one of three things, and which one it is is decided here rather
 * than in a view:
 *
 * - a tick or a dash, for something a plan either has or has not
 * - a few words, for anything else — "5%", "Unlimited", "Single"
 * - nothing at all, drawn as a dash
 *
 * A row that names a real feature is read from what the plan actually
 * allows. Staff cannot type over those, which is the whole point: a table
 * saying "500 products" can never disagree with the 500 the platform keeps.
 * Every other row is the platform's own words about itself.
 */
class PlanComparison
{
    /**
     * The whole table: sections, the rows in them, and every plan's answer.
     *
     * @param  Collection<int, Package>  $packages
     * @return array<int, array<string, mixed>>
     */
    public function build(Collection $packages): array
    {
        $groups = PlanFeatureGroup::query()
            ->with('features')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $written = $this->writtenValues($groups);
        $allowed = $this->allowances($packages);

        return $groups
            ->map(fn (PlanFeatureGroup $group) => [
                'group' => $group,
                'rows' => $group->features
                    ->map(fn (PlanFeature $row) => [
                        'row' => $row,
                        'cells' => $packages
                            ->mapWithKeys(fn (Package $package) => [
                                $package->id => $this->cell($row, $package, $written, $allowed),
                            ])
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ])
            // A section with no rows in it is a heading over nothing.
            ->filter(fn (array $section) => $section['rows'] !== [])
            ->values()
            ->all();
    }

    /**
     * What one plan says in one row.
     *
     * @param  Collection<string, string|null>  $written
     * @param  Collection<string, PackageEntitlement>  $allowed
     * @return array{kind: string, text: string|null}
     */
    protected function cell(PlanFeature $row, Package $package, Collection $written, Collection $allowed): array
    {
        if ($row->isEnforced()) {
            return $this->fromAllowance($row, $package, $allowed);
        }

        return $this->read($written->get($row->id.':'.$package->id));
    }

    /**
     * A row that names a real feature, read from what the plan allows.
     *
     * @param  Collection<string, PackageEntitlement>  $allowed
     * @return array{kind: string, text: string|null}
     */
    protected function fromAllowance(PlanFeature $row, Package $package, Collection $allowed): array
    {
        $definition = $row->definition();
        $entitlement = $allowed->get($package->id.':'.$row->feature);

        if (($definition['type'] ?? 'switch') === 'switch') {
            $on = $entitlement === null
                ? (bool) ($definition['default'] ?? false)
                : (bool) $entitlement->enabled;

            return ['kind' => $on ? 'tick' : 'dash', 'text' => null];
        }

        // A counted thing. No row at all means the platform's own default;
        // a row with no ceiling means no ceiling.
        if ($entitlement === null) {
            $ceiling = $definition['default'] ?? null;
        } elseif (! $entitlement->enabled) {
            return ['kind' => 'dash', 'text' => null];
        } else {
            $ceiling = $entitlement->limit_value;
        }

        if ($ceiling === null) {
            return ['kind' => 'text', 'text' => 'Unlimited'];
        }

        return (int) $ceiling === 0
            ? ['kind' => 'dash', 'text' => null]
            : ['kind' => 'text', 'text' => number_format((int) $ceiling)];
    }

    /**
     * What staff wrote in a cell, turned into a tick, a dash or words.
     *
     * @return array{kind: string, text: string|null}
     */
    protected function read(?string $written): array
    {
        $value = trim((string) $written);

        if ($value === '') {
            return ['kind' => 'dash', 'text' => null];
        }

        return match (mb_strtolower($value)) {
            'yes', 'y', 'true', 'tick', '1' => ['kind' => 'tick', 'text' => null],
            'no', 'n', 'false', 'dash', '0', '-', '—' => ['kind' => 'dash', 'text' => null],
            default => ['kind' => 'text', 'text' => $value],
        };
    }

    /**
     * Every cell staff have written, keyed "row:plan".
     *
     * @param  Collection<int, PlanFeatureGroup>  $groups
     * @return Collection<string, string|null>
     */
    protected function writtenValues(Collection $groups): Collection
    {
        $rowIds = $groups->flatMap(fn (PlanFeatureGroup $group) => $group->features->pluck('id'));

        if ($rowIds->isEmpty()) {
            return collect();
        }

        return PlanFeatureValue::query()
            ->whereIn('plan_feature_id', $rowIds)
            ->get()
            ->mapWithKeys(fn (PlanFeatureValue $value) => [
                $value->plan_feature_id.':'.$value->package_id => $value->value,
            ]);
    }

    /**
     * What every plan actually allows, keyed "plan:feature".
     *
     * @param  Collection<int, Package>  $packages
     * @return Collection<string, PackageEntitlement>
     */
    protected function allowances(Collection $packages): Collection
    {
        if ($packages->isEmpty()) {
            return collect();
        }

        return PackageEntitlement::query()
            ->whereIn('package_id', $packages->pluck('id'))
            ->get()
            ->mapWithKeys(fn (PackageEntitlement $row) => [
                $row->package_id.':'.$row->feature => $row,
            ]);
    }
}
