<?php

namespace App\Services\Demo;

/**
 * A believable grocery shop: what is on the shelves, what it costs, and the
 * photograph that goes with it.
 *
 * Prices are in taka and are roughly what these things cost in Dhaka, so the
 * shop front can be looked at without any figure standing out as nonsense.
 * Every 'photo' names a freely licensed file on Wikimedia Commons; the
 * licences are listed in database/demo/photo-credits.md.
 */
class GroceryCatalogue
{
    /** @return array<string, array{parent: string|null, photo: string}> */
    public static function categories(): array
    {
        return [
            'Fresh Produce' => ['parent' => null, 'photo' => 'Food-healthy-vegetables-potatoes (23958160949).jpg'],
            'Fruit' => ['parent' => 'Fresh Produce', 'photo' => 'Ripe mangoes.jpg'],
            'Vegetables' => ['parent' => 'Fresh Produce', 'photo' => 'Three whole red onions.jpg'],
            'Dairy & Eggs' => ['parent' => null, 'photo' => '6-Pack-Chicken-Eggs.jpg'],
            'Meat & Fish' => ['parent' => null, 'photo' => 'Raw chicken for sale.jpg'],
            'Fish' => ['parent' => 'Meat & Fish', 'photo' => 'Hilsa Fish in Dhaka.jpg'],
            'Bakery' => ['parent' => null, 'photo' => 'Fresh made bread 05.jpg'],
            'Rice & Cooking' => ['parent' => null, 'photo' => 'Basmati Rice.jpg'],
            'Spices' => ['parent' => 'Rice & Cooking', 'photo' => 'Kunyit Bubuk.jpg'],
            'Beverages' => ['parent' => null, 'photo' => 'Teacup with Tealeaves on Wooden Table.jpg'],
            'Snacks' => ['parent' => null, 'photo' => 'Potato Chips.jpg'],
            'Household' => ['parent' => null, 'photo' => 'Laundry detergent 1.jpg'],
            'Personal Care' => ['parent' => null, 'photo' => 'Handmade soap.jpg'],
        ];
    }

    /** @return array<int, string> */
    public static function brands(): array
    {
        return ['Fresh Daily', 'Padma Foods', 'Green Basket', 'Dhaka Mills', 'Bengal Home'];
    }

    /** @return array<int, array<string, mixed>> */
    public static function products(): array
    {
        return [
            // ---------------------------------------------------------- fruit
            ['name' => 'Sagor Bananas', 'category' => 'Fruit', 'brand' => 'Fresh Daily',
                'regular' => '140', 'discount' => '120', 'cost' => '95', 'stock' => 60, 'low' => 12,
                'photo' => 'Bunch of bananas on sale.jpg', 'unit' => 'per dozen',
                'tags' => 'banana, fruit, fresh', 'short' => 'Sweet local sagor bananas, sold by the dozen.',
                'body' => '<p>Picked and delivered the same day. Keep them out of the fridge and they will ripen on the counter.</p>'],
            ['name' => 'Himsagar Mangoes', 'category' => 'Fruit', 'brand' => 'Padma Foods',
                'regular' => '420', 'cost' => '300', 'stock' => 25, 'low' => 8,
                'photo' => 'Ripe mangoes.jpg', 'unit' => 'per kg',
                'tags' => 'mango, fruit, seasonal', 'short' => 'Himsagar mangoes from Rajshahi, ripe and ready.',
                'body' => '<h2>In season now</h2><p>Sweet, almost no fibre, and heavy for their size. Eat within three or four days.</p>'],
            ['name' => 'Red Apples', 'category' => 'Fruit', 'brand' => 'Green Basket',
                'regular' => '320', 'cost' => '230', 'stock' => 40,
                'photo' => 'Red Apple.jpg', 'unit' => 'per kg',
                'tags' => 'apple, fruit, imported', 'short' => 'Crisp red apples, about four to the kilo.',
                'body' => '<p>Keeps well in the fridge for a fortnight.</p>'],
            ['name' => 'Malta Oranges', 'category' => 'Fruit', 'brand' => 'Green Basket',
                'regular' => '280', 'cost' => '195', 'stock' => 30,
                'photo' => 'Oranges - whole-halved-segment.jpg', 'unit' => 'per kg',
                'tags' => 'orange, citrus, fruit', 'short' => 'Juicy oranges, good for eating or squeezing.',
                'body' => '<p>Thin skinned and easy to peel.</p>'],

            // ----------------------------------------------------- vegetables
            ['name' => 'Fresh Tomatoes', 'category' => 'Vegetables', 'brand' => 'Fresh Daily',
                'regular' => '90', 'cost' => '58', 'stock' => 55, 'low' => 15,
                'photo' => 'Fresh Tomato in the market for sale 01.jpg', 'unit' => 'per kg',
                'tags' => 'tomato, vegetable, fresh', 'short' => 'Firm red tomatoes, picked this morning.',
                'body' => '<p>Good for cooking or a salad. Keep somewhere cool, not in the fridge.</p>'],
            ['name' => 'Potatoes', 'category' => 'Vegetables', 'brand' => 'Fresh Daily',
                'regular' => '55', 'cost' => '34', 'stock' => 120, 'low' => 25,
                'photo' => 'Food-healthy-vegetables-potatoes (23958160949).jpg', 'unit' => 'per kg',
                'tags' => 'potato, vegetable, staple', 'short' => 'Everyday potatoes for curry and bhaji.',
                'body' => '<p>Store them somewhere dark and dry and they will last for weeks.</p>'],
            ['name' => 'Red Onions', 'category' => 'Vegetables', 'brand' => 'Fresh Daily',
                'regular' => '75', 'discount' => '65', 'cost' => '48', 'stock' => 90, 'low' => 20,
                'photo' => 'Three whole red onions.jpg', 'unit' => 'per kg',
                'tags' => 'onion, vegetable, staple', 'short' => 'Local red onions, strong and sweet.',
                'body' => '<p>The base of almost everything. Keep them dry and airy.</p>'],
            ['name' => 'Green Chillies', 'category' => 'Vegetables', 'brand' => 'Green Basket',
                'regular' => '160', 'cost' => '105', 'stock' => 18, 'low' => 6,
                'photo' => 'Green Chili.jpg', 'unit' => 'per kg',
                'tags' => 'chilli, vegetable, spice', 'short' => 'Hot green chillies, sold loose.',
                'body' => '<p>They freeze well if you buy more than you need.</p>'],
            ['name' => 'Spinach Bunch', 'category' => 'Vegetables', 'brand' => 'Green Basket',
                'regular' => '35', 'cost' => '20', 'stock' => 0,
                'photo' => 'Fresh Spinach leaves.jpg', 'unit' => 'per bunch',
                'tags' => 'spinach, greens, vegetable', 'short' => 'A big bunch of fresh palong shak.',
                'body' => '<p>Best used the day it arrives.</p>'],

            // --------------------------------------------------- dairy & eggs
            ['name' => 'Full Cream Milk', 'category' => 'Dairy & Eggs', 'brand' => 'Padma Foods',
                'regular' => '95', 'cost' => '72', 'stock' => 48, 'low' => 12,
                'photo' => 'Milk and straw.jpg', 'unit' => '1 litre',
                'tags' => 'milk, dairy, breakfast', 'short' => 'Pasteurised full cream milk, one litre.',
                'body' => '<p>Keep it cold. Once opened, use within two days.</p>'],
            ['name' => 'Farm Eggs', 'category' => 'Dairy & Eggs', 'brand' => 'Fresh Daily',
                'regular' => '155', 'discount' => '140', 'cost' => '118', 'stock' => 36, 'low' => 10,
                'photo' => '6-Pack-Chicken-Eggs.jpg', 'unit' => 'dozen',
                'tags' => 'eggs, dairy, breakfast', 'short' => 'A dozen brown eggs from local farms.',
                'body' => '<p>Delivered in a moulded tray so nothing arrives cracked.</p>'],
            ['name' => 'Sweet Yoghurt', 'category' => 'Dairy & Eggs', 'brand' => 'Padma Foods',
                'regular' => '120', 'cost' => '80', 'stock' => 22, 'low' => 6,
                'photo' => 'Yogurt fruit bowl.jpg', 'unit' => '500 g pot',
                'tags' => 'yoghurt, mishti doi, dairy', 'short' => 'Set sweet yoghurt in a clay pot.',
                'body' => '<p>Made the traditional way and set in the pot it is sold in.</p>'],
            ['name' => 'Butter Block', 'category' => 'Dairy & Eggs', 'brand' => 'Padma Foods',
                'regular' => '340', 'cost' => '250', 'stock' => 14,
                'photo' => 'Block of butter in butter dish.jpg', 'unit' => '200 g',
                'tags' => 'butter, dairy, baking', 'short' => 'Salted butter, two hundred grams.',
                'body' => '<p>Keep it wrapped in the fridge so it does not take on other smells.</p>'],
            ['name' => 'Cheese Block', 'category' => 'Dairy & Eggs', 'brand' => 'Padma Foods',
                'regular' => '480', 'cost' => '350', 'stock' => 9, 'low' => 4,
                'photo' => 'DeutschButterkäse.jpg', 'unit' => '250 g',
                'tags' => 'cheese, dairy', 'short' => 'Mild semi-hard cheese, good for toast.',
                'body' => '<p>Slices cleanly and melts well.</p>'],

            // ---------------------------------------------------- meat & fish
            ['name' => 'Whole Chicken', 'category' => 'Meat & Fish', 'brand' => 'Fresh Daily',
                'regular' => '380', 'cost' => '285', 'stock' => 16, 'low' => 5,
                'photo' => 'Raw chicken for sale.jpg', 'unit' => 'per kg',
                'tags' => 'chicken, meat, halal', 'short' => 'Fresh whole broiler chicken, cleaned and cut.',
                'body' => '<h2>Cut how you like</h2><p>Tell us at checkout whether you want it whole, in eight pieces or in curry cut.</p>'],
            ['name' => 'Beef Undercut', 'category' => 'Meat & Fish', 'brand' => 'Fresh Daily',
                'regular' => '820', 'cost' => '640', 'stock' => 7, 'low' => 3,
                'photo' => 'Fresh Bio Range Land Filet Beef (164927627).jpeg', 'unit' => 'per kg',
                'tags' => 'beef, meat, halal', 'short' => 'Lean beef undercut, trimmed.',
                'body' => '<p>Cut fresh each morning. Freeze anything you are not cooking today.</p>'],
            ['name' => 'Padma Hilsa', 'category' => 'Fish', 'brand' => 'Padma Foods',
                'regular' => '1450', 'cost' => '1100', 'stock' => 5, 'low' => 3,
                'photo' => 'Hilsa Fish in Dhaka.jpg', 'unit' => 'per kg',
                'tags' => 'hilsa, ilish, fish, padma', 'short' => 'Padma river hilsa, the real thing.',
                'body' => '<h2>Padma ilish</h2><p>Bought at the ghat in the morning and kept on ice all the way to your door.</p>'],
            ['name' => 'Fresh Prawns', 'category' => 'Fish', 'brand' => 'Padma Foods',
                'regular' => '950', 'discount' => '880', 'cost' => '700', 'stock' => 11,
                'photo' => 'Raw shrimp.jpg', 'unit' => 'per kg',
                'tags' => 'prawn, chingri, seafood', 'short' => 'Medium prawns, shell on.',
                'body' => '<p>Cleaned and deveined on request.</p>'],

            // --------------------------------------------------------- bakery
            ['name' => 'Milk Bread Loaf', 'category' => 'Bakery', 'brand' => 'Dhaka Mills',
                'regular' => '75', 'cost' => '48', 'stock' => 26, 'low' => 8,
                'photo' => 'Fresh made bread 05.jpg', 'unit' => '600 g loaf',
                'tags' => 'bread, bakery, breakfast', 'short' => 'Soft milk bread, baked this morning.',
                'body' => '<p>Baked daily. Anything unsold goes off the shelf at closing.</p>'],
            ['name' => 'Butter Biscuits', 'category' => 'Bakery', 'brand' => 'Dhaka Mills',
                'regular' => '110', 'cost' => '70', 'stock' => 33,
                'photo' => 'Cookies (6672151563).jpg', 'unit' => '300 g pack',
                'tags' => 'biscuit, bakery, tea time', 'short' => 'Crisp butter biscuits for with tea.',
                'body' => '<p>Keep the pack sealed and they stay crisp for a month.</p>'],

            // ------------------------------------------------- rice & cooking
            ['name' => 'Basmati Rice', 'category' => 'Rice & Cooking', 'brand' => 'Dhaka Mills',
                'regular' => '135', 'discount' => '125', 'cost' => '95', 'stock' => 44, 'low' => 10,
                'options' => ['Pack' => ['1 kg', '5 kg']],
                'variant_prices' => ['1 kg' => '135', '5 kg' => '620'],
                'photo' => 'Basmati Rice.jpg', 'unit' => 'per pack',
                'tags' => 'rice, basmati, staple', 'short' => 'Long grain basmati, aged for a year.',
                'body' => '<h2>Aged a year</h2><p>Aged rice cooks up dry and separate instead of sticking together.</p>'],
            ['name' => 'Red Lentils', 'category' => 'Rice & Cooking', 'brand' => 'Dhaka Mills',
                'regular' => '135', 'cost' => '95', 'stock' => 52, 'low' => 12,
                'photo' => 'Musuro Ko Dal.jpg', 'unit' => 'per kg',
                'tags' => 'lentils, masoor dal, staple', 'short' => 'Masoor dal, cleaned and sorted.',
                'body' => '<p>Cooks in about twenty minutes with no soaking.</p>'],
            ['name' => 'Atta Flour', 'category' => 'Rice & Cooking', 'brand' => 'Dhaka Mills',
                'regular' => '210', 'cost' => '155', 'stock' => 38,
                'photo' => 'Wheat flour (Obusera).jpg', 'unit' => '2 kg bag',
                'tags' => 'flour, atta, ruti, baking', 'short' => 'Wholemeal atta for ruti and paratha.',
                'body' => '<p>Stone ground, so it keeps some of the bran.</p>'],
            ['name' => 'Mustard Oil', 'category' => 'Rice & Cooking', 'brand' => 'Padma Foods',
                'regular' => '390', 'cost' => '295', 'stock' => 21, 'low' => 6,
                'photo' => 'Mustard Oil MoteNyinSei.jpg', 'unit' => '1 litre',
                'tags' => 'mustard oil, cooking oil', 'short' => 'Cold pressed mustard oil, sharp and strong.',
                'body' => '<p>Pressed without heat, so it keeps its bite.</p>'],
            ['name' => 'White Sugar', 'category' => 'Rice & Cooking', 'brand' => 'Dhaka Mills',
                'regular' => '145', 'cost' => '112', 'stock' => 47,
                'photo' => 'Sugar on a blue plate (close up).jpg', 'unit' => 'per kg',
                'tags' => 'sugar, staple, baking', 'short' => 'Refined white sugar.',
                'body' => '<p>Keep the bag closed so it does not clump in the damp.</p>'],
            ['name' => 'Iodised Salt', 'category' => 'Spices', 'brand' => 'Dhaka Mills',
                'regular' => '42', 'cost' => '26', 'stock' => 75,
                'photo' => 'Table salt with salt shaker V1.jpg', 'unit' => '1 kg',
                'tags' => 'salt, staple', 'short' => 'Free flowing iodised salt.',
                'body' => '<p>Iodised, as required.</p>'],
            ['name' => 'Turmeric Powder', 'category' => 'Spices', 'brand' => 'Green Basket',
                'regular' => '180', 'cost' => '125', 'stock' => 29, 'low' => 8,
                'photo' => 'Kunyit Bubuk.jpg', 'unit' => '200 g',
                'tags' => 'turmeric, holud, spice', 'short' => 'Ground turmeric, nothing added.',
                'body' => '<p>Ground from whole roots. No colouring and no filler.</p>'],

            // ------------------------------------------------------ beverages
            ['name' => 'Black Tea Leaves', 'category' => 'Beverages', 'brand' => 'Padma Foods',
                'regular' => '260', 'cost' => '185', 'stock' => 31,
                'photo' => 'Teacup with Tealeaves on Wooden Table.jpg', 'unit' => '400 g',
                'tags' => 'tea, cha, beverage', 'short' => 'Strong Sylheti black tea leaves.',
                'body' => '<p>Takes milk and sugar well. One spoon makes two cups.</p>'],
            ['name' => 'Instant Coffee', 'category' => 'Beverages', 'brand' => 'Green Basket',
                'regular' => '540', 'cost' => '410', 'stock' => 12, 'low' => 4,
                'photo' => 'Instant Coffee Grains Inside Jar.jpeg', 'unit' => '100 g jar',
                'tags' => 'coffee, beverage', 'short' => 'Freeze dried instant coffee in a glass jar.',
                'body' => '<p>Close the lid tightly; it takes up damp quickly.</p>'],
            ['name' => 'Drinking Water', 'category' => 'Beverages', 'brand' => 'Fresh Daily',
                'regular' => '25', 'cost' => '15', 'stock' => 200, 'low' => 40,
                'photo' => 'Bottled water (6972595593).jpg', 'unit' => '2 litre bottle',
                'tags' => 'water, drinking water, beverage', 'short' => 'Sealed drinking water, two litres.',
                'body' => '<p>Check the seal is unbroken before you drink it.</p>'],
            ['name' => 'Orange Juice', 'category' => 'Beverages', 'brand' => 'Green Basket',
                'regular' => '190', 'discount' => '165', 'cost' => '130', 'stock' => 17,
                'photo' => 'Orange juice half glass.jpg', 'unit' => '1 litre',
                'tags' => 'juice, orange, beverage', 'short' => 'Orange juice with no sugar added.',
                'body' => '<p>Shake before pouring. Keep it cold once opened.</p>'],

            // --------------------------------------------------------- snacks
            ['name' => 'Potato Chips', 'category' => 'Snacks', 'brand' => 'Green Basket',
                'regular' => '85', 'cost' => '52', 'stock' => 64,
                'photo' => 'Potato Chips.jpg', 'unit' => '150 g pack',
                'tags' => 'chips, crisps, snack', 'short' => 'Salted potato chips.',
                'body' => '<p>Fried in sunflower oil.</p>'],
            ['name' => 'Roasted Peanuts', 'category' => 'Snacks', 'brand' => 'Green Basket',
                'regular' => '150', 'cost' => '98', 'stock' => 41,
                'photo' => 'Roasted peanuts 2.jpg', 'unit' => '500 g',
                'tags' => 'peanuts, badam, snack', 'short' => 'Roasted and lightly salted peanuts.',
                'body' => '<p>Roasted in small batches so nothing sits around going stale.</p>'],

            // ------------------------------------------------------ household
            ['name' => 'Washing Powder', 'category' => 'Household', 'brand' => 'Bengal Home',
                'regular' => '320', 'discount' => '285', 'cost' => '230', 'stock' => 28,
                'photo' => 'Laundry detergent 1.jpg', 'unit' => '1 kg',
                'tags' => 'detergent, washing, household', 'short' => 'Washing powder for hand or machine.',
                'body' => '<p>One scoop does a full bucket.</p>'],
            ['name' => 'Toilet Tissue', 'category' => 'Household', 'brand' => 'Bengal Home',
                'regular' => '180', 'cost' => '125', 'stock' => 35,
                'photo' => 'Toilet Tissue Paper Roll 350 Sheet.jpg', 'unit' => '4 rolls',
                'tags' => 'tissue, toilet paper, household', 'short' => 'Two ply toilet tissue, four rolls.',
                'body' => '<p>Three hundred and fifty sheets to a roll.</p>'],

            // -------------------------------------------------- personal care
            ['name' => 'Bath Soap', 'category' => 'Personal Care', 'brand' => 'Bengal Home',
                'regular' => '95', 'cost' => '58', 'stock' => 58,
                'photo' => 'Handmade soap.jpg', 'unit' => '125 g bar',
                'tags' => 'soap, bath, personal care', 'short' => 'Gentle bath soap bar.',
                'body' => '<p>Lathers well in hard water.</p>'],
            ['name' => 'Shampoo', 'category' => 'Personal Care', 'brand' => 'Bengal Home',
                'regular' => '390', 'cost' => '280', 'stock' => 0,
                'photo' => 'Green shampoo bottle.jpg', 'unit' => '400 ml',
                'tags' => 'shampoo, hair, personal care', 'short' => 'Everyday shampoo for all hair types.',
                'body' => '<p>No added colour.</p>'],
        ];
    }
}
