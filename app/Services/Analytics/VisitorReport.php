<?php

namespace App\Services\Analytics;

use App\Models\Tenant;
use App\Models\VisitDay;
use App\Models\VisitPulse;
use Illuminate\Support\Carbon;

/**
 * The four visitor figures a shopkeeper asked for, and nothing else.
 *
 * Days are the shop's own days, so midnight means midnight where the shop is.
 */
class VisitorReport
{
    /**
     * @return array{
     *     rightNow: int,
     *     today: array{visits: int, visitors: int},
     *     yesterday: array{visits: int, visitors: int},
     *     month: array{visits: int, visitors: int},
     *     monthName: string,
     *     change: float|null,
     * }
     */
    public function forStore(Tenant $store): array
    {
        $now = Carbon::now($store->timezone);

        $todayKey = $now->toDateString();
        $yesterdayKey = $now->copy()->subDay()->toDateString();
        $monthStart = $now->copy()->startOfMonth()->toDateString();

        // On the first of the month, yesterday sits before the month starts.
        $from = min($monthStart, $yesterdayKey);

        $days = VisitDay::query()
            ->whereBetween('on_day', [$from, $todayKey])
            ->get(['on_day', 'visits', 'visitors'])
            ->keyBy(fn (VisitDay $day) => (string) $day->on_day);

        $today = $this->totals($days->get($todayKey));
        $yesterday = $this->totals($days->get($yesterdayKey));

        $month = $days->filter(fn (VisitDay $day) => (string) $day->on_day >= $monthStart);

        return [
            'rightNow' => VisitPulse::query()
                ->where('seen_at', '>=', Carbon::now('UTC')->subMinutes(VisitPulse::ONLINE_MINUTES))
                ->count(),
            'today' => $today,
            'yesterday' => $yesterday,
            'month' => [
                'visits' => (int) $month->sum('visits'),
                'visitors' => (int) $month->sum('visitors'),
            ],
            'monthName' => $now->translatedFormat('F'),
            'change' => $yesterday['visits'] > 0
                ? round(($today['visits'] - $yesterday['visits']) / $yesterday['visits'] * 100, 1)
                : null,
        ];
    }

    /**
     * @return array{visits: int, visitors: int}
     */
    protected function totals(?VisitDay $day): array
    {
        return [
            'visits' => (int) ($day->visits ?? 0),
            'visitors' => (int) ($day->visitors ?? 0),
        ];
    }
}
