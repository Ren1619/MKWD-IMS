<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\InventorySeriesCategory;
use Illuminate\Database\Seeder;

class ConsumableInventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->callSilent(InventoryCategorySeeder::class);

        /** @var array<string, int> $seriesCategoryIds */
        $seriesCategoryIds = InventorySeriesCategory::query()
            ->whereIn('code', collect($this->items())->pluck('series_code'))
            ->pluck('inv_series_cat_id', 'code')
            ->all();

        foreach ($this->items() as $itemData) {
            $seriesCode = (string) $itemData['series_code'];
            $receivedAt = (string) $itemData['received_at'];
            $quantityIn = (int) $itemData['quantity_in'];
            unset($itemData['series_code'], $itemData['received_at'], $itemData['quantity_in']);

            $item = InventoryItem::query()->updateOrCreate(
                ['stock_number' => $itemData['stock_number']],
                [
                    ...$itemData,
                    'series_category_id' => $seriesCategoryIds[$seriesCode],
                ],
            );

            $item->batches()->updateOrCreate(
                ['batch_number' => 1],
                [
                    'quantity_in' => $quantityIn,
                    'quantity_remaining' => $itemData['quantity'],
                    'received_at' => $receivedAt,
                ],
            );
        }
    }

    /** @return array<int, array<string, int|float|string|null>> */
    private function items(): array
    {
        return [
            [
                'series_code' => 'COPY-PAPER',
                'name' => 'A4 Copy Paper, 80 gsm',
                'stock_number' => 'CON-2026-001',
                'unit_of_measure' => 'ream',
                'uacs_object_code' => '50203010-02',
                'description' => '500 sheets per ream for routine office printing.',
                'quantity' => 185,
                'quantity_in' => 185,
                'price' => 248.50,
                'expiration_date' => null,
                'status' => 'active',
                'received_at' => '2026-01-12',
            ],
            [
                'series_code' => 'BALLPEN',
                'name' => 'Black Ballpoint Pen, 0.5 mm',
                'stock_number' => 'CON-2026-002',
                'unit_of_measure' => 'box',
                'uacs_object_code' => '50203010-01',
                'description' => 'Box of 12 black retractable ballpoint pens.',
                'quantity' => 64,
                'quantity_in' => 64,
                'price' => 126.75,
                'expiration_date' => null,
                'status' => 'active',
                'received_at' => '2026-02-03',
            ],
            [
                'series_code' => 'PRN-INK',
                'name' => 'Black Printer Ink Bottle',
                'stock_number' => 'CON-2025-003',
                'unit_of_measure' => 'bottle',
                'uacs_object_code' => '50203010-03',
                'description' => 'Genuine 65 ml black ink bottle for office ink-tank printers.',
                'quantity' => 28,
                'quantity_in' => 28,
                'price' => 395.00,
                'expiration_date' => '2027-11-30',
                'status' => 'active',
                'received_at' => '2025-11-18',
            ],
            [
                'series_code' => 'PRN-TONER',
                'name' => 'High-yield Black Toner Cartridge',
                'stock_number' => 'CON-2025-004',
                'unit_of_measure' => 'cartridge',
                'uacs_object_code' => '50203010-03',
                'description' => 'High-yield cartridge rated for approximately 3,000 pages.',
                'quantity' => 9,
                'quantity_in' => 9,
                'price' => 4850.00,
                'expiration_date' => '2028-03-31',
                'status' => 'active',
                'received_at' => '2025-08-25',
            ],
            [
                'series_code' => 'DISINFECT',
                'name' => '70% Isopropyl Alcohol',
                'stock_number' => 'CON-2026-005',
                'unit_of_measure' => 'gallon',
                'uacs_object_code' => '50203020-00',
                'description' => 'One-gallon disinfecting alcohol for refill stations.',
                'quantity' => 17,
                'quantity_in' => 17,
                'price' => 785.25,
                'expiration_date' => '2027-04-15',
                'status' => 'active',
                'received_at' => '2026-04-20',
            ],
            [
                'series_code' => 'DETERGENT',
                'name' => 'Powder Detergent, 1 kg',
                'stock_number' => 'CON-2024-006',
                'unit_of_measure' => 'pack',
                'uacs_object_code' => '50203020-00',
                'description' => 'General-purpose powdered detergent for facility cleaning.',
                'quantity' => 6,
                'quantity_in' => 6,
                'price' => 142.00,
                'expiration_date' => '2025-12-31',
                'status' => 'inactive',
                'received_at' => '2024-10-07',
            ],
            [
                'series_code' => 'FACE-MASK',
                'name' => 'Disposable Surgical Face Mask',
                'stock_number' => 'CON-2026-007',
                'unit_of_measure' => 'box',
                'uacs_object_code' => '50203070-00',
                'description' => 'Box of 50 three-ply disposable face masks.',
                'quantity' => 42,
                'quantity_in' => 42,
                'price' => 168.90,
                'expiration_date' => '2029-02-28',
                'status' => 'active',
                'received_at' => '2026-06-15',
            ],
            [
                'series_code' => 'WOUND-CARE',
                'name' => 'Sterile Gauze Pads, 4 x 4 in',
                'stock_number' => 'CON-2025-008',
                'unit_of_measure' => 'pack',
                'uacs_object_code' => '50203070-00',
                'description' => 'Pack of individually wrapped sterile gauze pads.',
                'quantity' => 31,
                'quantity_in' => 31,
                'price' => 215.00,
                'expiration_date' => '2027-08-31',
                'status' => 'active',
                'received_at' => '2025-05-09',
            ],
            [
                'series_code' => 'LIGHT-BULB',
                'name' => 'LED Bulb, 12 W Daylight',
                'stock_number' => 'CON-2023-009',
                'unit_of_measure' => 'piece',
                'uacs_object_code' => '50213040-00',
                'description' => 'Energy-efficient E27 LED replacement bulb.',
                'quantity' => 0,
                'quantity_in' => 24,
                'price' => 118.50,
                'expiration_date' => null,
                'status' => 'disposed',
                'received_at' => '2023-03-14',
            ],
            [
                'series_code' => 'PVC-FIT',
                'name' => 'PVC Elbow, 25 mm',
                'stock_number' => 'CON-2026-010',
                'unit_of_measure' => 'piece',
                'uacs_object_code' => '50213040-00',
                'description' => 'Schedule 40, 90-degree PVC elbow for minor line repairs.',
                'quantity' => 73,
                'quantity_in' => 73,
                'price' => 34.75,
                'expiration_date' => null,
                'status' => 'active',
                'received_at' => '2026-07-21',
            ],
        ];
    }
}
