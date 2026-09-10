<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\PlanFeature;
use App\Models\PlanFeatureGroup;
use App\Models\PlanFeatureValue;
use App\Models\PlanPageSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * The plans table as the owner designed it.
 *
 * A starting point, not a fixture: every section, row and cell here can be
 * rewritten by staff afterwards. Running it twice changes nothing, so it is
 * safe to press the button in super admin more than once.
 *
 * Rows that name a feature — `products`, `orders_per_month` and the two
 * switches — are read from what each plan actually allows, so their columns
 * are left alone here. Everything else is words, and words start out saying
 * what the design said.
 */
class PlanTableSeeder extends Seeder
{
    /**
     * The table, section by section. Each row is
     * [name, note, feature, [value per plan, in the order plans are sold]].
     */
    public const TABLE = [
        [
            'name' => 'Fees per order',
            'icon' => 'coins',
            'note' => 'Deducted per order based on product type.',
            'rows' => [
                ['Own physical products', null, null, ['5%', '0%', '0%', '0%']],
                ['Own digital products', null, null, ['10%', '6%', '3%', '4%']],
                ['Resell supplier products', null, null, ['3%', '1.9%', '0.75%', '1%']],
            ],
        ],
        [
            'name' => 'Selling',
            'icon' => 'bag',
            'note' => null,
            'rows' => [
                ['Sell your own physical products', null, null, ['no', 'yes', 'yes', 'yes']],
                ['Sell your own digital products', null, null, ['yes', 'yes', 'yes', 'yes']],
                ['Resell supplier products', null, null, ['yes', 'yes', 'yes', 'yes']],
                ['Product limit (own products)', null, 'products', []],
                ['Resell product limit', null, null, ['100', '500', 'Unlimited', 'Unlimited']],
                ['Orders per month', null, 'orders_per_month', []],
            ],
        ],
        [
            'name' => 'Store & branding',
            'icon' => 'shop',
            'note' => null,
            'rows' => [
                ['Subdomain', 'yourshop.example.com', null, ['Single', 'All', 'All', 'All']],
                ['Custom domain', null, null, ['no', 'yes', 'yes', 'yes']],
                ['Preset themes', null, null, ['2', 'Unlimited', 'Unlimited', 'Unlimited']],
                ['Theme builder', null, null, ['no', 'no', 'yes', 'yes']],
                ['Spotlight (featured products)', null, null, ['no', 'no', 'yes', 'no']],
                ['Customer login page', null, null, ['no', 'no', 'yes', 'no']],
            ],
        ],
        [
            'name' => 'Marketing & growth',
            'icon' => 'megaphone',
            'note' => null,
            'rows' => [
                ['Marketing pixels & GTM', null, null, ['yes', 'yes', 'yes', 'yes']],
                ['Abandoned cart recovery', null, null, ['no', 'no', 'yes', 'no']],
                ['Report exports', null, null, ['no', 'yes', 'yes', 'yes']],
            ],
        ],
        [
            'name' => 'Operations',
            'icon' => 'gear',
            'note' => null,
            'rows' => [
                ['Inventory management', null, null, ['yes', 'yes', 'yes', 'yes']],
                ['Order management', null, null, ['yes', 'yes', 'yes', 'yes']],
                ['Customer management', null, null, ['yes', 'yes', 'yes', 'yes']],
                ['Blocklist', null, null, ['no', 'no', 'yes', 'yes']],
                ['Turbo server (high performance)', null, null, ['no', 'no', 'yes', 'yes']],
            ],
        ],
        [
            'name' => 'Payments & delivery',
            'icon' => 'van',
            'note' => null,
            'rows' => [
                ['Own gateway / self-MFS', null, null, ['no', 'no', 'yes', 'yes']],
                ['External payment gateways', null, 'online_payments', []],
                ['Courier integration', null, 'courier_pickup', []],
                ['Third-party couriers', null, null, ['no', 'yes', 'yes', 'yes']],
            ],
        ],
    ];

    /** The promises across the top. Staff own these words. */
    public const PROMISES = [
        ['icon' => 'tag', 'title' => 'No hidden fees', 'detail' => 'Transparent pricing'],
        ['icon' => 'shield', 'title' => 'Secure & reliable', 'detail' => 'Your shop, always on'],
        ['icon' => 'headset', 'title' => 'Support when you need it', 'detail' => "We're here for you"],
    ];

    public function run(): void
    {
        $this->words();

        // In the order they are sold, which is the order of the columns.
        $packages = Package::query()->orderBy('sort_order')->orderBy('id')->get();

        foreach (self::TABLE as $position => $section) {
            $group = PlanFeatureGroup::firstOrCreate(
                ['name' => $section['name']],
                ['icon' => $section['icon'], 'note' => $section['note'], 'position' => $position],
            );

            foreach ($section['rows'] as $order => [$name, $note, $feature, $values]) {
                $row = PlanFeature::firstOrCreate(
                    ['plan_feature_group_id' => $group->id, 'name' => $name],
                    ['note' => $note, 'feature' => $feature, 'position' => $order],
                );

                // A row read from what a plan allows has nothing to write.
                if ($feature !== null) {
                    continue;
                }

                foreach ($packages as $column => $package) {
                    if (! array_key_exists($column, $values)) {
                        continue;
                    }

                    PlanFeatureValue::firstOrCreate(
                        ['plan_feature_id' => $row->id, 'package_id' => $package->id],
                        ['value' => $values[$column]],
                    );
                }
            }
        }

        $this->mostPopular($packages);
    }

    /**
     * The heading and the promises, if nobody has written their own yet.
     */
    protected function words(): void
    {
        if (PlanPageSettings::query()->exists()) {
            return;
        }

        PlanPageSettings::create([
            'eyebrow' => 'Plans & pricing',
            'heading' => 'Compare plans. Choose what fits',
            'heading_accent' => 'your business.',
            'blurb' => 'All the features you need to start, grow and scale your online store — with simple, transparent pricing.',
            'promises' => self::PROMISES,
        ]);
    }

    /**
     * One column wears the badge. The middle one, until staff say otherwise.
     *
     * @param  Collection<int, Package>  $packages
     */
    protected function mostPopular($packages): void
    {
        if ($packages->count() < 2 || Package::query()->where('highlight', true)->exists()) {
            return;
        }

        $packages->get((int) floor(($packages->count() - 1) / 2))
            ?->forceFill(['highlight' => true, 'badge' => 'Most popular'])
            ->save();
    }
}
