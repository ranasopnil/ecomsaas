<?php

namespace App\Services\Analytics;

use App\Models\Tenant;
use App\Models\VisitDay;
use App\Models\VisitPulse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Adds one visit to a shop's counters.
 *
 * On purpose, this keeps nothing but numbers: no addresses, no browsers, no
 * pages, no referrers, no names. The only per-person row is a fingerprint that
 * is thrown away after a day and cannot be turned back into anybody.
 */
class VisitRecorder
{
    public function handle(Request $request, Tenant $store): void
    {
        $day = Carbon::now($store->timezone)->toDateString();
        $seenAt = Carbon::now('UTC');

        $isNewVisitor = $this->markHere($this->fingerprint($request, $store, $day), $seenAt);

        $this->countTheDay($day, $isNewVisitor);
    }

    /**
     * Note that this visitor is here now.
     *
     * Returns true the first time we see them today, which is what makes the
     * difference between "visits" and "people".
     */
    protected function markHere(string $fingerprint, Carbon $seenAt): bool
    {
        $pulse = VisitPulse::query()->where('visitor_hash', $fingerprint)->first();

        if ($pulse !== null) {
            $pulse->update(['seen_at' => $seenAt]);

            return false;
        }

        try {
            VisitPulse::create(['visitor_hash' => $fingerprint, 'seen_at' => $seenAt]);

            return true;
        } catch (UniqueConstraintViolationException) {
            // Two pages opened in the same instant. The other one won, so this
            // is the same person, not a new one.
            VisitPulse::query()->where('visitor_hash', $fingerprint)->update(['seen_at' => $seenAt]);

            return false;
        }
    }

    /**
     * Add to today's row, making it first if today has had no visitors yet.
     */
    protected function countTheDay(string $day, bool $isNewVisitor): void
    {
        $people = $isNewVisitor ? 1 : 0;

        if ($this->addToDay($day, $people) > 0) {
            return;
        }

        try {
            VisitDay::create(['on_day' => $day, 'visits' => 1, 'visitors' => $people]);
        } catch (UniqueConstraintViolationException) {
            // Somebody else opened the day a moment before us.
            $this->addToDay($day, $people);
        }
    }

    protected function addToDay(string $day, int $people): int
    {
        return VisitDay::query()
            ->where('on_day', $day)
            ->incrementEach(['visits' => 1, 'visitors' => $people]);
    }

    /**
     * A one-way mark for one visitor, for one shop, for one day.
     *
     * The address and browser go in and cannot come back out, and the day is
     * part of the mix, so the same person is a fresh mark again tomorrow.
     */
    protected function fingerprint(Request $request, Tenant $store, string $day): string
    {
        return hash('sha256', implode('|', [
            $store->getKey(),
            $day,
            (string) $request->ip(),
            (string) $request->userAgent(),
            (string) config('app.key'),
        ]));
    }
}
