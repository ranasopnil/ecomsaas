<?php

namespace App\Services\Catalogue;

use App\Models\InventoryLevel;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Every change to stock goes through here.
 *
 * Stock is changed with one statement in the database that both checks and
 * subtracts at the same time. Reading a number, deciding, and writing it back
 * would let two shoppers buy the same last item.
 */
class InventoryService
{
    public function levelFor(ProductVariant $variant): InventoryLevel
    {
        return InventoryLevel::firstOrCreate(
            ['product_variant_id' => $variant->getKey()],
            ['available' => 0, 'reserved' => 0, 'track_inventory' => true],
        );
    }

    /**
     * Set stock to a counted figure, as after a stock take.
     */
    public function setTo(ProductVariant $variant, int $counted, string $note = ''): InventoryLevel
    {
        $level = $this->levelFor($variant);
        $change = $counted - $level->available;

        $level->update(['available' => $counted]);

        $this->record($variant, $change, $counted, InventoryMovement::REASON_STOCK_TAKE, null, $note);

        return $level->fresh();
    }

    /**
     * Add stock, as when a delivery arrives.
     */
    public function receive(ProductVariant $variant, int $quantity, string $reason = InventoryMovement::REASON_RECEIVED, ?string $reference = null, string $note = ''): InventoryLevel
    {
        $quantity = abs($quantity);
        $level = $this->levelFor($variant);

        InventoryLevel::query()
            ->whereKey($level->getKey())
            ->update([
                'available' => DB::raw('available + '.$quantity),
                'updated_at' => now(),
            ]);

        $level = $level->fresh();

        $this->record($variant, $quantity, $level->available, $reason, $reference, $note);

        return $level;
    }

    /**
     * Hold stock for an order being placed.
     *
     * Returns false when there is not enough, without changing anything. The
     * check and the subtraction are the same statement, so two shoppers cannot
     * both take the last one.
     */
    public function reserve(ProductVariant $variant, int $quantity, ?string $reference = null): bool
    {
        $quantity = abs($quantity);

        if ($quantity === 0) {
            return true;
        }

        $level = $this->levelFor($variant);

        if (! $level->track_inventory) {
            return true;
        }

        $query = InventoryLevel::query()->whereKey($level->getKey());

        if (! $level->allow_backorder) {
            $query->where('available', '>=', $quantity);
        }

        $affected = $query->update([
            'available' => DB::raw('available - '.$quantity),
            'reserved' => DB::raw('reserved + '.$quantity),
            'updated_at' => now(),
        ]);

        if ($affected === 0) {
            return false;
        }

        $this->record($variant, -$quantity, $level->fresh()->available, InventoryMovement::REASON_SOLD, $reference);

        return true;
    }

    /**
     * Give held stock back, as when an order is cancelled.
     */
    public function release(ProductVariant $variant, int $quantity, ?string $reference = null): void
    {
        $quantity = abs($quantity);
        $level = $this->levelFor($variant);

        if (! $level->track_inventory || $quantity === 0) {
            return;
        }

        InventoryLevel::query()
            ->whereKey($level->getKey())
            ->update([
                'available' => DB::raw('available + '.$quantity),
                'reserved' => DB::raw('GREATEST(reserved - '.$quantity.', 0)'),
                'updated_at' => now(),
            ]);

        $this->record($variant, $quantity, $level->fresh()->available, InventoryMovement::REASON_RELEASED, $reference);
    }

    /**
     * Held stock has left the building. It stops being held and is not added
     * back to what is available.
     */
    public function fulfil(ProductVariant $variant, int $quantity, ?string $reference = null): void
    {
        $quantity = abs($quantity);
        $level = $this->levelFor($variant);

        if (! $level->track_inventory || $quantity === 0) {
            return;
        }

        InventoryLevel::query()
            ->whereKey($level->getKey())
            ->update([
                'reserved' => DB::raw('GREATEST(reserved - '.$quantity.', 0)'),
                'updated_at' => now(),
            ]);
    }

    public function record(ProductVariant $variant, int $change, int $availableAfter, string $reason, ?string $reference = null, string $note = ''): InventoryMovement
    {
        return InventoryMovement::create([
            'product_variant_id' => $variant->getKey(),
            'quantity_change' => $change,
            'available_after' => $availableAfter,
            'reason' => $reason,
            'reference' => $reference,
            'note' => $note ?: null,
        ]);
    }
}
