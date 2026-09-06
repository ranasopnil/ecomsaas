<?php

namespace Tests\Feature\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\VisitorStats;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitDay;
use App\Models\VisitPulse;
use App\Services\Billing\SubscribeToPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class VisitorStatsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        // A fixed day in the middle of a month, so "this month" is not being
        // asked about on the first or the last of one.
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00', 'Asia/Dhaka'));

        $this->store = Tenant::factory()->create(['currency' => 'BDT', 'timezone' => 'Asia/Dhaka']);
        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing(['products' => 50])->create());

        Entitlements::forget();
        Tenancy::set($this->store);

        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id]));
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function day(string $on, int $visits, int $visitors): void
    {
        VisitDay::create(['on_day' => $on, 'visits' => $visits, 'visitors' => $visitors]);
    }

    public function test_it_shows_today_yesterday_and_this_month(): void
    {
        $this->day('2026-09-15', 40, 25);   // today
        $this->day('2026-09-14', 30, 20);   // yesterday
        $this->day('2026-09-02', 12, 9);    // earlier this month
        $this->day('2026-08-30', 99, 88);   // last month, must be left out

        Livewire::test(VisitorStats::class)
            ->assertSee('Visits today')
            ->assertSee('40')
            ->assertSee('From 25 people')
            ->assertSee('Visits yesterday')
            ->assertSee('30')
            ->assertSee('From 20 people')
            ->assertSee('Visits in September')
            ->assertSee('82')              // 40 + 30 + 12, not 99
            ->assertDontSee('181');
    }

    public function test_it_counts_who_is_on_the_shop_right_now(): void
    {
        VisitPulse::create(['visitor_hash' => str_repeat('a', 64), 'seen_at' => Carbon::now('UTC')->subMinute()]);
        VisitPulse::create(['visitor_hash' => str_repeat('b', 64), 'seen_at' => Carbon::now('UTC')->subSeconds(20)]);
        VisitPulse::create(['visitor_hash' => str_repeat('c', 64), 'seen_at' => Carbon::now('UTC')->subMinutes(45)]);

        Livewire::test(VisitorStats::class)
            ->assertSee('Here right now')
            ->assertSee('2')
            ->assertSee('People looking at your shop now');
    }

    public function test_a_quiet_shop_says_so_rather_than_showing_nothing(): void
    {
        Livewire::test(VisitorStats::class)
            ->assertSee('Nobody is on your shop at the moment')
            ->assertSee('From 0 people');
    }

    public function test_it_shows_whether_today_is_up_or_down_on_yesterday(): void
    {
        $this->day('2026-09-15', 15, 10);
        $this->day('2026-09-14', 30, 20);

        Livewire::test(VisitorStats::class)
            ->assertSee('50%')
            ->assertSee('bg-rose-50 text-rose-700', false);
    }

    public function test_one_shop_never_sees_another_shops_visitors(): void
    {
        $this->day('2026-09-15', 40, 25);

        $other = Tenant::factory()->create(['currency' => 'BDT', 'timezone' => 'Asia/Dhaka']);
        app(SubscribeToPackage::class)->handle($other, Package::factory()->allowing(['products' => 10])->create());

        Tenancy::run($other, function () use ($other) {
            $this->actingAs(User::factory()->create(['tenant_id' => $other->id]));

            Livewire::test(VisitorStats::class)
                ->assertSee('From 0 people')
                ->assertDontSee('From 25 people');
        });
    }
}
