<?php

namespace Tests\Feature;

use App\Exceptions\CrossTenantWrite;
use App\Exceptions\TenantContextMissing;
use App\Facades\Tenancy;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

/**
 * The one test that must always pass.
 *
 * It proves no merchant can see or touch another merchant's data, and that
 * every tenant-owned table is shaped so that stays true.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenancy::forget();

        parent::tearDown();
    }

    public function test_reads_only_return_records_of_the_bound_store(): void
    {
        [$storeA, $storeB] = [Tenant::factory()->create(), Tenant::factory()->create()];

        $domainA = Tenancy::run($storeA, fn () => Domain::factory()->create(['tenant_id' => $storeA->id]));
        $domainB = Tenancy::run($storeB, fn () => Domain::factory()->create(['tenant_id' => $storeB->id]));

        Tenancy::run($storeA, function () use ($domainA, $domainB) {
            $this->assertEquals([$domainA->id], Domain::query()->pluck('id')->all());
            $this->assertNull(Domain::find($domainB->id));
        });

        Tenancy::run($storeB, function () use ($domainB) {
            $this->assertEquals([$domainB->id], Domain::query()->pluck('id')->all());
        });
    }

    public function test_querying_tenant_data_without_a_bound_store_throws(): void
    {
        $store = Tenant::factory()->create();
        Tenancy::run($store, fn () => Domain::factory()->create(['tenant_id' => $store->id]));

        $this->expectException(TenantContextMissing::class);

        Tenancy::runWithout(fn () => Domain::query()->get());
    }

    public function test_tenant_id_is_stamped_on_create(): void
    {
        $store = Tenant::factory()->create();

        $domain = Tenancy::run($store, fn () => Domain::create([
            'hostname' => 'stamped'.config('tenancy.subdomain_suffix'),
            'type' => Domain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => Domain::STATUS_VERIFIED,
        ]));

        $this->assertSame($store->id, $domain->tenant_id);
    }

    public function test_writing_into_another_store_is_refused(): void
    {
        [$storeA, $storeB] = [Tenant::factory()->create(), Tenant::factory()->create()];

        $this->expectException(CrossTenantWrite::class);

        Tenancy::run($storeA, fn () => Domain::create([
            'tenant_id' => $storeB->id,
            'hostname' => 'sneaky'.config('tenancy.subdomain_suffix'),
        ]));
    }

    public function test_moving_a_record_to_another_store_is_refused(): void
    {
        [$storeA, $storeB] = [Tenant::factory()->create(), Tenant::factory()->create()];

        $domain = Tenancy::run($storeA, fn () => Domain::factory()->create(['tenant_id' => $storeA->id]));

        $this->expectException(CrossTenantWrite::class);

        Tenancy::run($storeA, function () use ($domain, $storeB) {
            $domain->tenant_id = $storeB->id;
            $domain->save();
        });
    }

    public function test_a_hostname_resolves_to_its_store_before_any_store_is_bound(): void
    {
        $store = Tenant::factory()->create();
        $domain = Tenancy::run($store, fn () => Domain::factory()->create(['tenant_id' => $store->id]));

        $found = Tenancy::runWithout(fn () => Domain::findByHostname(strtoupper($domain->hostname)));

        $this->assertNotNull($found);
        $this->assertSame($store->id, $found->tenant->id);
    }

    /**
     * Every tenant-owned model must have a tenant_id column and at least one
     * index that leads with it. A new model that forgets either fails here.
     */
    public function test_every_tenant_owned_table_is_shaped_for_isolation(): void
    {
        $models = $this->tenantOwnedModels();

        $this->assertNotEmpty($models, 'No tenant-owned models found — the isolation test would prove nothing.');

        foreach ($models as $class) {
            $table = (new $class)->getTable();

            $this->assertTrue(
                DB::getSchemaBuilder()->hasColumn($table, 'tenant_id'),
                "Table [{$table}] uses BelongsToTenant but has no tenant_id column."
            );

            $this->assertContains(
                'tenant_id',
                $this->leadingIndexColumns($table),
                "Table [{$table}] has no index leading with tenant_id."
            );
        }
    }

    /**
     * @return array<int, class-string>
     */
    protected function tenantOwnedModels(): array
    {
        $models = [];

        foreach (File::allFiles(app_path('Models')) as $file) {
            $class = 'App\\Models\\'.Str::of($file->getRelativePathname())
                ->replace(['/', '.php'], ['\\', ''])
                ->value();

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! in_array(BelongsToTenant::class, $this->traitsOf($class), true)) {
                continue;
            }

            $models[] = $class;
        }

        return $models;
    }

    /**
     * @return array<int, string>
     */
    protected function traitsOf(string $class): array
    {
        $traits = [];

        do {
            $traits = array_merge($traits, class_uses($class) ?: []);
        } while ($class = get_parent_class($class));

        return array_values($traits);
    }

    /**
     * @return array<int, string>
     */
    protected function leadingIndexColumns(string $table): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT a.attname AS column_name
            FROM pg_index i
            JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = i.indkey[0]
            WHERE i.indrelid = ?::regclass
        SQL, [$table]);

        return array_map(fn ($row) => $row->column_name, $rows);
    }
}
