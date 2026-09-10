<?php

namespace Tests\Feature\Billing;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\PlanIndex;
use App\Livewire\Super\PlanTable;
use App\Models\Admin;
use App\Models\Package;
use App\Models\PackageEntitlement;
use App\Models\PlanFeature;
use App\Models\PlanFeatureGroup;
use App\Models\PlanFeatureValue;
use App\Models\PlanPageSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use Database\Seeders\PlanTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The plans table: written by staff, read by shop owners.
 *
 * The whole thing turns on one rule — a row that names a real feature is read
 * from what the plan actually allows and cannot be typed over. These prove
 * that rule holds from both ends: that staff cannot write a number the
 * platform will not keep, and that a shop owner sees the number that is
 * really enforced.
 */
class PlanTableTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $shop;

    protected Package $starter;

    protected Package $growth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->starter = Package::factory()->allowing(['products' => 50])->create([
            'name' => 'Starter', 'slug' => 'starter', 'currency' => 'BDT',
            'price_minor' => 99000, 'sort_order' => 1, 'is_active' => true, 'is_public' => true,
        ]);

        $this->growth = Package::factory()->allowing(['products' => null])->create([
            'name' => 'Growth', 'slug' => 'growth', 'currency' => 'BDT',
            'price_minor' => 249000, 'sort_order' => 2, 'is_active' => true, 'is_public' => true,
        ]);

        $this->shop = Tenant::factory()->create([
            'currency' => 'BDT', 'currency_exponent' => 2, 'country_code' => 'BD',
        ]);

        app(SubscribeToPackage::class)->handle($this->shop, $this->starter);

        Entitlements::forget();
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    protected function asShopkeeper(): void
    {
        Tenancy::set($this->shop);
        $this->actingAs(User::factory()->create(['tenant_id' => $this->shop->id]));
    }

    protected function asStaff(): void
    {
        $this->actingAs(Admin::factory()->create(['name' => 'Md Jewel Rana']), 'admin');
    }

    /**
     * A section with one written row in it.
     */
    protected function aRow(string $name, ?string $feature = null, array $values = []): PlanFeature
    {
        $group = PlanFeatureGroup::firstOrCreate(['name' => 'Selling'], ['icon' => 'bag', 'position' => 0]);

        $row = PlanFeature::create([
            'plan_feature_group_id' => $group->id, 'name' => $name, 'feature' => $feature, 'position' => 0,
        ]);

        foreach ($values as $packageId => $value) {
            PlanFeatureValue::create([
                'plan_feature_id' => $row->id, 'package_id' => $packageId, 'value' => $value,
            ]);
        }

        return $row;
    }

    /*
     * ------------------------------------------------- what a shop owner reads
     */

    public function test_the_shop_owner_sees_the_table_staff_wrote(): void
    {
        PlanPageSettings::create([
            'eyebrow' => 'Plans & pricing',
            'heading' => 'Compare plans. Choose what fits',
            'heading_accent' => 'your business.',
            'blurb' => 'Simple, transparent pricing.',
            'promises' => [['icon' => 'tag', 'title' => 'No hidden fees', 'detail' => 'Transparent pricing']],
        ]);

        $this->aRow('Own digital products', null, [
            $this->starter->id => '6%',
            $this->growth->id => '3%',
        ]);

        $this->asShopkeeper();

        Livewire::test(PlanIndex::class)
            ->assertSee('Plans & pricing')
            ->assertSee('Compare plans. Choose what fits')
            ->assertSee('your business.')
            ->assertSee('No hidden fees')
            ->assertSee('Selling')
            ->assertSee('Own digital products')
            ->assertSee('6%')
            ->assertSee('3%')
            // The plan they are on is named as theirs, not sold to them again.
            ->assertSee('Your plan');
    }

    public function test_a_row_read_from_the_plan_shows_what_is_really_enforced(): void
    {
        $this->aRow('Product limit', 'products');

        $this->asShopkeeper();

        Livewire::test(PlanIndex::class)
            ->assertSee('Product limit')
            // Starter really allows 50; Growth really has no ceiling.
            ->assertSee('50')
            ->assertSee('Unlimited');
    }

    public function test_yes_and_no_become_a_tick_and_a_dash(): void
    {
        $this->aRow('Theme builder', null, [
            $this->starter->id => 'no',
            $this->growth->id => 'yes',
        ]);

        $this->asShopkeeper();

        $page = Livewire::test(PlanIndex::class);

        $page->assertSee('Theme builder')
            ->assertSee('aria-label="Included"', false)
            ->assertSee('aria-label="Not included"', false);
    }

    public function test_a_section_with_no_rows_is_not_drawn_as_an_empty_heading(): void
    {
        PlanFeatureGroup::create(['name' => 'Coming later', 'icon' => 'spark', 'position' => 5]);
        $this->aRow('Order management', null, [$this->starter->id => 'yes']);

        $this->asShopkeeper();

        Livewire::test(PlanIndex::class)
            ->assertSee('Order management')
            ->assertDontSee('Coming later');
    }

    /*
     * ------------------------------------------------- what staff can write
     */

    public function test_staff_can_rewrite_a_row_and_the_shop_owner_sees_it(): void
    {
        $row = $this->aRow('Report exports', null, [$this->starter->id => 'no']);

        $this->asStaff();

        Livewire::test(PlanTable::class)
            ->set('rowNames.'.$row->id, 'Exporting reports')
            ->set('cells.'.$row->id.'.'.$this->starter->id, 'yes')
            ->set('cells.'.$row->id.'.'.$this->growth->id, 'yes')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Exporting reports', $row->fresh()->name);

        $this->asShopkeeper();

        Livewire::test(PlanIndex::class)
            ->assertSee('Exporting reports')
            ->assertDontSee('Report exports');
    }

    public function test_staff_cannot_type_over_a_row_the_platform_enforces(): void
    {
        $row = $this->aRow('Product limit', 'products');

        $this->asStaff();

        Livewire::test(PlanTable::class)
            // Starter really allows 50. Try to promise 9,999.
            ->set('cells.'.$row->id.'.'.$this->starter->id, '9999')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('plan_feature_values', [
            'plan_feature_id' => $row->id,
            'package_id' => $this->starter->id,
        ]);

        $this->asShopkeeper();

        Livewire::test(PlanIndex::class)
            ->assertSee('50')
            ->assertDontSee('9,999')
            ->assertDontSee('9999');
    }

    public function test_the_number_follows_the_plan_when_the_plan_changes(): void
    {
        $this->aRow('Product limit', 'products');

        PackageEntitlement::updateOrCreate(
            ['package_id' => $this->starter->id, 'feature' => 'products'],
            ['enabled' => true, 'limit_value' => 500],
        );

        $this->asShopkeeper();

        Livewire::test(PlanIndex::class)->assertSee('500')->assertDontSee('>50<', false);
    }

    public function test_staff_can_add_a_section_a_row_and_take_them_away_again(): void
    {
        $this->asStaff();

        $page = Livewire::test(PlanTable::class)
            ->set('newGroup', 'Support')
            ->call('addGroup');

        $group = PlanFeatureGroup::where('name', 'Support')->firstOrFail();

        $page->set('newRow.'.$group->id, 'Phone support')
            ->call('addRow', $group->id);

        $row = PlanFeature::where('name', 'Phone support')->firstOrFail();

        $page->call('removeRow', $row->id);
        $this->assertDatabaseMissing('plan_features', ['id' => $row->id]);

        $page->call('removeGroup', $group->id);
        $this->assertDatabaseMissing('plan_feature_groups', ['id' => $group->id]);
    }

    public function test_taking_a_section_away_takes_its_rows_and_cells_with_it(): void
    {
        $row = $this->aRow('Blocklist', null, [$this->starter->id => 'yes']);
        $groupId = $row->plan_feature_group_id;

        $this->asStaff();

        Livewire::test(PlanTable::class)->call('removeGroup', $groupId);

        $this->assertDatabaseMissing('plan_features', ['id' => $row->id]);
        $this->assertDatabaseMissing('plan_feature_values', ['plan_feature_id' => $row->id]);
    }

    public function test_staff_choose_which_column_wears_the_badge(): void
    {
        $this->aRow('Order management', null, [$this->starter->id => 'yes']);

        $this->asStaff();

        Livewire::test(PlanTable::class)
            ->set('popular', $this->growth->id)
            ->set('badges.'.$this->growth->id, 'Most popular')
            ->set('ctas.'.$this->growth->id, 'Start now')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($this->growth->fresh()->highlight);
        $this->assertFalse($this->starter->fresh()->highlight);

        $this->asShopkeeper();

        Livewire::test(PlanIndex::class)
            ->assertSee('Most popular')
            ->assertSee('Start now');
    }

    public function test_rewriting_the_table_is_written_to_the_audit_log(): void
    {
        $this->aRow('Order management', null, [$this->starter->id => 'yes']);

        $this->asStaff();

        Livewire::test(PlanTable::class)->call('save');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'plans.table_saved',
            'admin_name' => 'Md Jewel Rana',
        ]);
    }

    /*
     * ------------------------------------------------- the standard layout
     */

    public function test_the_standard_table_can_be_laid_out_and_laying_it_out_twice_changes_nothing(): void
    {
        $this->asStaff();

        Livewire::test(PlanTable::class)->call('loadStandard');

        $groups = PlanFeatureGroup::count();
        $rows = PlanFeature::count();
        $cells = PlanFeatureValue::count();

        $this->assertSame(count(PlanTableSeeder::TABLE), $groups);
        $this->assertGreaterThan(20, $rows);

        Livewire::test(PlanTable::class)->call('loadStandard');

        $this->assertSame($groups, PlanFeatureGroup::count());
        $this->assertSame($rows, PlanFeature::count());
        $this->assertSame($cells, PlanFeatureValue::count());
    }

    public function test_the_standard_table_never_writes_over_a_row_the_platform_enforces(): void
    {
        $this->asStaff();

        Livewire::test(PlanTable::class)->call('loadStandard');

        $enforced = PlanFeature::whereNotNull('feature')->pluck('id');

        $this->assertNotEmpty($enforced, 'Some rows should be read from the plan.');
        $this->assertSame(0, PlanFeatureValue::whereIn('plan_feature_id', $enforced)->count());
    }
}
