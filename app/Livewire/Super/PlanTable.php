<?php

namespace App\Livewire\Super;

use App\Models\AuditLog;
use App\Models\Package;
use App\Models\PlanFeature;
use App\Models\PlanFeatureGroup;
use App\Models\PlanFeatureValue;
use App\Models\PlanPageSettings;
use App\Services\Billing\PlanComparison;
use Database\Seeders\PlanTableSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Writing the plans table that every shop owner reads.
 *
 * The sections, the rows in them, what each plan says in each row, the badge
 * over the popular column and the words above the whole thing — all of it is
 * written here and nowhere else.
 *
 * One rule holds the thing together: a row may name a real feature, and when
 * it does, its column is read from what the plan actually allows and cannot
 * be typed over. That is what stops the table promising a ceiling the
 * platform does not keep. Every other row is the platform's own words, and
 * this screen says which is which on every row, because the difference
 * matters to whoever is writing it.
 */
#[Layout('layouts.super')]
#[Title('Plans table')]
class PlanTable extends Component
{
    /** The words above the table. */
    public string $eyebrow = '';

    public string $heading = '';

    public string $headingAccent = '';

    public string $blurb = '';

    /** @var array<int, array{icon: string, title: string, detail: string}> */
    public array $promises = [];

    /** Every cell staff can type in, keyed "rowId.packageId". */
    public array $cells = [];

    /** Group and row names, keyed by id, so they can be renamed in place. */
    public array $groupNames = [];

    public array $groupNotes = [];

    public array $rowNames = [];

    public array $rowNotes = [];

    public array $rowFeatures = [];

    /** Which plan wears the badge, and what each button says. */
    public ?int $popular = null;

    public array $badges = [];

    public array $ctas = [];

    /** New-thing boxes. */
    public string $newGroup = '';

    public array $newRow = [];

    public string $message = '';

    public string $messageType = 'status';

    public function mount(): void
    {
        $this->pullFromDatabase();
    }

    /**
     * Everything on the screen, read back out of the database.
     */
    protected function pullFromDatabase(): void
    {
        $settings = PlanPageSettings::current();

        $this->eyebrow = (string) $settings->eyebrow;
        $this->heading = (string) $settings->heading;
        $this->headingAccent = (string) $settings->heading_accent;
        $this->blurb = (string) $settings->blurb;
        $this->promises = $settings->promises ?: [];

        $packages = $this->packages();

        $this->popular = $packages->firstWhere('highlight', true)?->id;
        $this->badges = $packages->mapWithKeys(fn (Package $p) => [$p->id => (string) $p->badge])->all();
        $this->ctas = $packages->mapWithKeys(fn (Package $p) => [$p->id => (string) $p->cta])->all();

        $this->groupNames = [];
        $this->groupNotes = [];
        $this->rowNames = [];
        $this->rowNotes = [];
        $this->rowFeatures = [];
        $this->cells = [];

        $written = PlanFeatureValue::all()->keyBy(fn (PlanFeatureValue $v) => $v->plan_feature_id.'.'.$v->package_id);

        foreach (PlanFeatureGroup::with('features')->orderBy('position')->orderBy('id')->get() as $group) {
            $this->groupNames[$group->id] = $group->name;
            $this->groupNotes[$group->id] = (string) $group->note;

            foreach ($group->features as $row) {
                $this->rowNames[$row->id] = $row->name;
                $this->rowNotes[$row->id] = (string) $row->note;
                $this->rowFeatures[$row->id] = (string) $row->feature;

                foreach ($packages as $package) {
                    $this->cells[$row->id][$package->id] = (string) ($written->get($row->id.'.'.$package->id)?->value ?? '');
                }
            }
        }
    }

    /*
     * ------------------------------------------------------------- saving
     */

    public function save(): void
    {
        $this->validate([
            'heading' => ['nullable', 'string', 'max:120'],
            'headingAccent' => ['nullable', 'string', 'max:60'],
            'eyebrow' => ['nullable', 'string', 'max:60'],
            'blurb' => ['nullable', 'string', 'max:400'],
            'promises.*.title' => ['nullable', 'string', 'max:60'],
            'promises.*.detail' => ['nullable', 'string', 'max:80'],
            'groupNames.*' => ['required', 'string', 'max:60'],
            'rowNames.*' => ['required', 'string', 'max:120'],
            'cells.*.*' => ['nullable', 'string', 'max:40'],
        ]);

        $settings = PlanPageSettings::current();

        $settings->fill([
            'eyebrow' => trim($this->eyebrow) ?: null,
            'heading' => trim($this->heading) ?: null,
            'heading_accent' => trim($this->headingAccent) ?: null,
            'blurb' => trim($this->blurb) ?: null,
            'promises' => array_values(array_filter(
                $this->promises,
                fn (array $promise) => trim($promise['title'] ?? '') !== '',
            )),
        ])->save();

        foreach ($this->groupNames as $id => $name) {
            PlanFeatureGroup::whereKey($id)->update([
                'name' => trim($name),
                'note' => trim($this->groupNotes[$id] ?? '') ?: null,
            ]);
        }

        foreach ($this->rowNames as $id => $name) {
            $feature = trim($this->rowFeatures[$id] ?? '');

            PlanFeature::whereKey($id)->update([
                'name' => trim($name),
                'note' => trim($this->rowNotes[$id] ?? '') ?: null,
                'feature' => $feature !== '' && config('features.'.$feature) !== null ? $feature : null,
            ]);
        }

        // Cells belonging to a row that is read from what a plan allows are
        // not written: that row's column is not ours to type.
        $enforced = PlanFeature::query()->whereNotNull('feature')->pluck('feature', 'id');

        foreach ($this->cells as $rowId => $byPackage) {
            if (isset($enforced[$rowId]) && config('features.'.$enforced[$rowId]) !== null) {
                continue;
            }

            foreach ($byPackage as $packageId => $value) {
                PlanFeatureValue::updateOrCreate(
                    ['plan_feature_id' => $rowId, 'package_id' => $packageId],
                    ['value' => trim((string) $value) ?: null],
                );
            }
        }

        foreach ($this->packages() as $package) {
            $package->forceFill([
                'highlight' => $this->popular === $package->id,
                'badge' => trim($this->badges[$package->id] ?? '') ?: null,
                'cta' => trim($this->ctas[$package->id] ?? '') ?: null,
            ])->save();
        }

        AuditLog::record('plans.table_saved', null, null, 'Rewrote the plans comparison table');

        $this->pullFromDatabase();
        $this->say('Saved. Every shop owner sees this on their plan page now.');
    }

    /*
     * ------------------------------------------------- adding and removing
     */

    public function addGroup(): void
    {
        $name = trim($this->newGroup);

        if ($name === '') {
            $this->say('Give the section a name first.', 'error');

            return;
        }

        PlanFeatureGroup::create([
            'name' => $name,
            'icon' => 'spark',
            'position' => (int) PlanFeatureGroup::max('position') + 1,
        ]);

        $this->newGroup = '';
        $this->pullFromDatabase();
        $this->say($name.' added.');
    }

    public function addRow(int $groupId): void
    {
        $name = trim($this->newRow[$groupId] ?? '');

        if ($name === '') {
            $this->say('Give the row a name first.', 'error');

            return;
        }

        PlanFeature::create([
            'plan_feature_group_id' => $groupId,
            'name' => $name,
            'position' => (int) PlanFeature::where('plan_feature_group_id', $groupId)->max('position') + 1,
        ]);

        $this->newRow[$groupId] = '';
        $this->pullFromDatabase();
        $this->say($name.' added.');
    }

    public function removeRow(int $rowId): void
    {
        $row = PlanFeature::find($rowId);

        if ($row === null) {
            return;
        }

        $name = $row->name;
        $row->delete();

        $this->pullFromDatabase();
        $this->say($name.' taken off the table.');
    }

    public function removeGroup(int $groupId): void
    {
        $group = PlanFeatureGroup::find($groupId);

        if ($group === null) {
            return;
        }

        $name = $group->name;
        $group->delete();

        $this->pullFromDatabase();
        $this->say($name.' and everything in it taken off the table.');
    }

    public function moveGroup(int $groupId, int $by): void
    {
        $this->reorder(PlanFeatureGroup::orderBy('position')->orderBy('id')->get(), $groupId, $by);
        $this->pullFromDatabase();
    }

    public function moveRow(int $rowId, int $by): void
    {
        $row = PlanFeature::find($rowId);

        if ($row === null) {
            return;
        }

        $this->reorder(
            PlanFeature::where('plan_feature_group_id', $row->plan_feature_group_id)
                ->orderBy('position')->orderBy('id')->get(),
            $rowId,
            $by,
        );

        $this->pullFromDatabase();
    }

    /**
     * Swap one thing with its neighbour and write every position down again,
     * so an old row with position 0 cannot get stuck at the top.
     *
     * @param  Collection<int, Model>  $all
     */
    protected function reorder($all, int $id, int $by): void
    {
        $order = $all->pluck('id')->all();
        $at = array_search($id, $order, true);

        if ($at === false) {
            return;
        }

        $to = $at + $by;

        if ($to < 0 || $to >= count($order)) {
            return;
        }

        [$order[$at], $order[$to]] = [$order[$to], $order[$at]];

        foreach ($order as $position => $thing) {
            $all->firstWhere('id', $thing)?->forceFill(['position' => $position])->save();
        }
    }

    public function addPromise(): void
    {
        if (count($this->promises) >= 4) {
            $this->say('Three or four is as many as fit across the top.', 'error');

            return;
        }

        $this->promises[] = ['icon' => 'spark', 'title' => '', 'detail' => ''];
    }

    public function removePromise(int $at): void
    {
        unset($this->promises[$at]);
        $this->promises = array_values($this->promises);
    }

    /**
     * Lay the table out as the design had it. Safe to press twice: it only
     * adds what is missing and never overwrites anything already written.
     */
    public function loadStandard(): void
    {
        app(PlanTableSeeder::class)->run();

        AuditLog::record('plans.table_seeded', null, null, 'Laid out the standard plans table');

        $this->pullFromDatabase();
        $this->say('The standard table is in. Change anything you like, then save.');
    }

    protected function say(string $message, string $type = 'status'): void
    {
        $this->message = $message;
        $this->messageType = $type;
    }

    /**
     * The plans, in the order their columns appear.
     *
     * @return Collection<int, Package>
     */
    protected function packages()
    {
        return Package::query()
            ->where('is_active', true)
            ->with('prices')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function render(PlanComparison $comparison)
    {
        $packages = $this->packages();

        return view('livewire.super.plan-table', [
            'packages' => $packages,
            'groups' => PlanFeatureGroup::with('features')->orderBy('position')->orderBy('id')->get(),
            'features' => config('features'),
            'preview' => $comparison->build($packages),
            'settings' => PlanPageSettings::current(),
        ]);
    }
}
