<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            InventoryCategorySeeder::class,
            InventoryAssetSeeder::class,
            ConsumableInventorySeeder::class,
        ]);
    }
}
