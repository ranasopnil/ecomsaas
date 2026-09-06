<?php

namespace Tests\Feature\Storefront;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Domain;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitDay;
use App\Models\VisitPulse;
use App\Services\Analytics\VisitorReport;
use App\Services\Billing\SubscribeToPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class VisitCountingTest extends TestCase
{
    use RefreshDatabase;

    protected const BROWSER = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/126 Mobile Safari/537.36';

    protected Tenant $store;

    protected Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create(['currency' => 'BDT', 'timezone' => 'Asia/Dhaka']);
        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing(['products' => 50])->create());
        Entitlements::forget();

        $this->domain = Tenancy::run($this->store, fn () => Domain::factory()->create(['tenant_id' => $this->store->id]));
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * A shopper arriving at the shopfront, from one browser on one connection.
     */
    protected function shopper(string $browser = self::BROWSER, string $from = '203.0.113.10'): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $from])
            ->get('https://'.$this->domain->hostname.'/', ['User-Agent' => $browser]);
    }

    /**
     * @return array{visits: int, visitors: int}
     */
    protected function today(): array
    {
        return Tenancy::run($this->store, function () {
            $day = VisitDay::query()->where('on_day', Carbon::now($this->store->timezone)->toDateString())->first();

            return ['visits' => (int) ($day->visits ?? 0), 'visitors' => (int) ($day->visitors ?? 0)];
        });
    }

    public function test_a_shopper_looking_at_the_shop_is_counted(): void
    {
        $this->shopper()->assertOk();

        $this->assertSame(['visits' => 1, 'visitors' => 1], $this->today());
    }

    public function test_the_same_shopper_coming_back_is_more_visits_but_still_one_person(): void
    {
        $this->shopper();
        $this->shopper();
        $this->shopper();

        $this->assertSame(['visits' => 3, 'visitors' => 1], $this->today());
    }

    public function test_two_different_shoppers_are_counted_as_two_people(): void
    {
        $this->shopper(from: '203.0.113.10');
        $this->shopper(from: '203.0.113.99');

        $this->assertSame(['visits' => 2, 'visitors' => 2], $this->today());
    }

    public function test_a_search_engine_is_not_counted(): void
    {
        $this->shopper('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        $this->assertSame(['visits' => 0, 'visitors' => 0], $this->today());
    }

    public function test_a_page_the_browser_fetched_in_advance_is_not_counted(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('https://'.$this->domain->hostname.'/', [
                'User-Agent' => self::BROWSER,
                'Sec-Purpose' => 'prefetch;prerender',
            ])->assertOk();

        $this->assertSame(['visits' => 0, 'visitors' => 0], $this->today());
    }

    public function test_the_shopkeeper_looking_at_their_own_shop_is_not_counted(): void
    {
        $this->actingAs(Tenancy::run($this->store, fn () => User::factory()->create(['tenant_id' => $this->store->id])));

        $this->shopper()->assertOk();

        $this->assertSame(['visits' => 0, 'visitors' => 0], $this->today());
    }

    public function test_a_shop_never_sees_another_shops_visitors(): void
    {
        $this->shopper();

        $other = Tenant::factory()->create(['currency' => 'BDT', 'timezone' => 'Asia/Dhaka']);
        app(SubscribeToPackage::class)->handle($other, Package::factory()->allowing(['products' => 10])->create());

        $figures = Tenancy::run($other, fn () => app(VisitorReport::class)->forStore($other));

        $this->assertSame(0, $figures['today']['visits']);
        $this->assertSame(0, $figures['rightNow']);
    }

    public function test_somebody_who_just_arrived_shows_as_here_right_now(): void
    {
        $this->shopper();

        $figures = Tenancy::run($this->store, fn () => app(VisitorReport::class)->forStore($this->store));

        $this->assertSame(1, $figures['rightNow']);
    }

    public function test_somebody_who_left_a_while_ago_is_no_longer_here_right_now(): void
    {
        $this->shopper();

        Tenancy::run($this->store, function () {
            VisitPulse::query()->update(['seen_at' => Carbon::now('UTC')->subMinutes(30)]);

            $figures = app(VisitorReport::class)->forStore($this->store);

            // Gone from "right now", but the visit itself still counts for today.
            $this->assertSame(0, $figures['rightNow']);
            $this->assertSame(1, $figures['today']['visits']);
        });
    }

    public function test_a_day_ends_at_midnight_in_the_shops_own_timezone(): void
    {
        // Half past midnight in Dhaka is still the previous day in London.
        Carbon::setTestNow(Carbon::parse('2026-09-15 00:30', 'Asia/Dhaka'));

        $this->shopper();

        $day = Tenancy::run($this->store, fn () => VisitDay::query()->first());

        $this->assertSame('2026-09-15', (string) $day->on_day);
    }

    public function test_yesterdays_visitor_counts_again_as_a_person_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 10:00', 'Asia/Dhaka'));
        $this->shopper();

        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00', 'Asia/Dhaka'));
        $this->shopper();

        Tenancy::run($this->store, function () {
            $days = VisitDay::query()->orderBy('on_day')->get();

            $this->assertCount(2, $days);
            $this->assertSame([1, 1], $days->pluck('visits')->all());
            $this->assertSame([1, 1], $days->pluck('visitors')->all());
        });
    }

    public function test_old_fingerprints_are_tidied_away(): void
    {
        $this->shopper();

        Tenancy::run($this->store, fn () => VisitPulse::query()->update(['seen_at' => Carbon::now('UTC')->subDays(3)]));

        $this->artisan('visits:tidy')->assertExitCode(0);

        Tenancy::run($this->store, function () {
            $this->assertSame(0, VisitPulse::query()->count());

            // The counts survive; only the fingerprints go.
            $this->assertSame(1, (int) VisitDay::query()->first()->visits);
        });
    }
}
