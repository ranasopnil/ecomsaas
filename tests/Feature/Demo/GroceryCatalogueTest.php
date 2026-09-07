<?php

namespace Tests\Feature\Demo;

use App\Services\Demo\GroceryCatalogue;
use App\Services\Demo\SampleShop;
use Tests\TestCase;

/**
 * The demo grocery shop's own data, checked without touching the database or
 * the network.
 *
 * The photographs are other people's work under free licences. Every one used
 * has to be listed in the credits file, or we are using it without saying so.
 */
class GroceryCatalogueTest extends TestCase
{
    protected function credits(): string
    {
        return (string) file_get_contents(base_path('database/demo/photo-credits.md'));
    }

    /** @return array<int, string> */
    protected function photosUsed(): array
    {
        return array_values(array_unique(array_merge(
            array_column(GroceryCatalogue::products(), 'photo'),
            array_column(GroceryCatalogue::categories(), 'photo'),
        )));
    }

    public function test_every_photograph_used_is_credited(): void
    {
        $credits = $this->credits();

        foreach ($this->photosUsed() as $photo) {
            $this->assertStringContainsString($photo, $credits, "[{$photo}] is used but not credited.");
        }
    }

    public function test_every_credited_photograph_names_its_licence(): void
    {
        foreach (explode("\n", $this->credits()) as $line) {
            if (! str_starts_with($line, '| ') || str_contains($line, '| File |') || str_contains($line, '| ---')) {
                continue;
            }

            [, $file, $licence] = array_map('trim', explode('|', $line));

            $this->assertNotSame('', $file);
            $this->assertNotSame('', $licence, "[{$file}] is listed with no licence.");
        }
    }

    public function test_every_product_sits_in_a_category_that_exists(): void
    {
        $categories = GroceryCatalogue::categories();

        foreach (GroceryCatalogue::products() as $product) {
            $this->assertArrayHasKey(
                $product['category'],
                $categories,
                "[{$product['name']}] is in [{$product['category']}], which is not a category.",
            );
        }
    }

    public function test_every_category_sits_inside_one_that_exists(): void
    {
        $categories = GroceryCatalogue::categories();

        foreach ($categories as $name => $definition) {
            if ($definition['parent'] === null) {
                continue;
            }

            $this->assertArrayHasKey($definition['parent'], $categories, "[{$name}] sits inside nothing.");
        }
    }

    public function test_every_product_is_sold_for_more_than_it_cost(): void
    {
        foreach (GroceryCatalogue::products() as $product) {
            $selling = (float) ($product['discount'] ?? $product['regular']);

            $this->assertGreaterThan(
                (float) $product['cost'],
                $selling,
                "[{$product['name']}] is sold at a loss.",
            );
        }
    }

    public function test_a_reduced_price_is_always_below_the_normal_one(): void
    {
        foreach (GroceryCatalogue::products() as $product) {
            if (! isset($product['discount'])) {
                continue;
            }

            $this->assertLessThan(
                (float) $product['regular'],
                (float) $product['discount'],
                "[{$product['name']}] is 'reduced' to more than its normal price.",
            );
        }
    }

    public function test_the_shop_kinds_are_the_two_that_can_actually_be_built(): void
    {
        $this->assertSame(['grocery', 'fashion'], SampleShop::kinds());
    }
}
