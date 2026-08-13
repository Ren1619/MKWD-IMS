<?php

namespace Database\Seeders;

use App\Models\InventoryAssetCategory;
use App\Models\InventoryClassCategory;
use App\Models\InventoryMajorCategory;
use App\Models\InventorySeriesCategory;
use Illuminate\Database\Seeder;

class InventoryCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->categoryHierarchy() as $majorData) {
            $majorCategory = InventoryMajorCategory::query()->updateOrCreate(
                ['code' => $majorData['code']],
                [
                    'name' => $majorData['name'],
                    'description' => $majorData['description'],
                    'is_active' => true,
                ],
            );

            foreach ($majorData['classes'] as $classData) {
                $classCategory = InventoryClassCategory::query()->updateOrCreate(
                    ['code' => $classData['code']],
                    [
                        'inv_mjr_cat_id' => $majorCategory->getKey(),
                        'name' => $classData['name'],
                        'description' => $classData['description'],
                        'is_active' => true,
                    ],
                );

                foreach ($classData['series'] as $seriesData) {
                    InventorySeriesCategory::query()->updateOrCreate(
                        ['code' => $seriesData['code']],
                        [
                            'inv_class_cat_id' => $classCategory->getKey(),
                            'name' => $seriesData['name'],
                            'description' => $seriesData['description'],
                            'is_active' => true,
                        ],
                    );
                }
            }
        }

        foreach ($this->assetCategories() as $assetCategoryData) {
            InventoryAssetCategory::query()->updateOrCreate(
                ['code' => $assetCategoryData['code']],
                [
                    'name' => $assetCategoryData['name'],
                    'description' => $assetCategoryData['description'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<int, array{
     *     code: string,
     *     name: string,
     *     description: string,
     *     classes: array<int, array{
     *         code: string,
     *         name: string,
     *         description: string,
     *         series: array<int, array{code: string, name: string, description: string}>
     *     }>
     * }>
     */
    private function categoryHierarchy(): array
    {
        return [
            [
                'code' => 'OFF',
                'name' => 'Office Supplies',
                'description' => 'Consumable materials used for routine administrative work.',
                'classes' => [
                    [
                        'code' => 'OFF-WRT',
                        'name' => 'Writing Instruments',
                        'description' => 'Pens, markers, and other writing implements.',
                        'series' => [
                            ['code' => 'BALLPEN', 'name' => 'Ballpoint Pens', 'description' => 'Disposable and refillable ballpoint pens.'],
                            ['code' => 'MARKER', 'name' => 'Markers', 'description' => 'Permanent and whiteboard markers.'],
                        ],
                    ],
                    [
                        'code' => 'OFF-PAP',
                        'name' => 'Paper Products',
                        'description' => 'Paper used for printing, writing, and filing.',
                        'series' => [
                            ['code' => 'COPY-PAPER', 'name' => 'Copy Paper', 'description' => 'Cut-size paper for printers and photocopiers.'],
                            ['code' => 'NOTEBOOK', 'name' => 'Notebooks and Pads', 'description' => 'Bound notebooks and writing pads.'],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'ICT',
                'name' => 'Information Technology Supplies',
                'description' => 'Consumable supplies used by computers and peripherals.',
                'classes' => [
                    [
                        'code' => 'ICT-PRN',
                        'name' => 'Printing Consumables',
                        'description' => 'Replaceable printer and copier supplies.',
                        'series' => [
                            ['code' => 'PRN-INK', 'name' => 'Printer Ink', 'description' => 'Ink bottles and ink cartridges.'],
                            ['code' => 'PRN-TONER', 'name' => 'Toner Cartridges', 'description' => 'Laser printer and copier toner cartridges.'],
                        ],
                    ],
                    [
                        'code' => 'ICT-STO',
                        'name' => 'Data Storage Media',
                        'description' => 'Portable media used to transfer or archive files.',
                        'series' => [
                            ['code' => 'USB-DRIVE', 'name' => 'USB Flash Drives', 'description' => 'Portable solid-state storage devices.'],
                            ['code' => 'OPT-MEDIA', 'name' => 'Optical Media', 'description' => 'Recordable compact and digital video discs.'],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'JAN',
                'name' => 'Janitorial and Sanitation Supplies',
                'description' => 'Cleaning and sanitation materials for MKWD facilities.',
                'classes' => [
                    [
                        'code' => 'JAN-CHM',
                        'name' => 'Cleaning Agents',
                        'description' => 'Chemical products used for cleaning and disinfection.',
                        'series' => [
                            ['code' => 'DISINFECT', 'name' => 'Disinfectants', 'description' => 'Surface and general-purpose disinfecting products.'],
                            ['code' => 'DETERGENT', 'name' => 'Detergents', 'description' => 'Powder and liquid cleaning detergents.'],
                        ],
                    ],
                    [
                        'code' => 'JAN-TOO',
                        'name' => 'Cleaning Tools',
                        'description' => 'Low-value tools and materials used by janitorial staff.',
                        'series' => [
                            ['code' => 'MOP-BROOM', 'name' => 'Mops and Brooms', 'description' => 'Manual floor cleaning tools.'],
                            ['code' => 'WASTE-BAG', 'name' => 'Waste Bags', 'description' => 'Disposable bags for waste collection.'],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'SAF',
                'name' => 'Medical and Safety Supplies',
                'description' => 'First-aid and personal protective supplies.',
                'classes' => [
                    [
                        'code' => 'SAF-AID',
                        'name' => 'First Aid Supplies',
                        'description' => 'Consumable materials maintained in first-aid kits.',
                        'series' => [
                            ['code' => 'WOUND-CARE', 'name' => 'Wound Care', 'description' => 'Bandages, gauze, and antiseptic materials.'],
                            ['code' => 'MEDICINE', 'name' => 'Over-the-counter Medicine', 'description' => 'Approved medicines maintained for workplace first aid.'],
                        ],
                    ],
                    [
                        'code' => 'SAF-PPE',
                        'name' => 'Personal Protective Equipment',
                        'description' => 'Disposable protective equipment for field and office use.',
                        'series' => [
                            ['code' => 'FACE-MASK', 'name' => 'Face Masks', 'description' => 'Disposable protective face masks.'],
                            ['code' => 'SAF-GLOVE', 'name' => 'Safety Gloves', 'description' => 'Disposable and general-purpose work gloves.'],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'RMT',
                'name' => 'Repair and Maintenance Supplies',
                'description' => 'Materials consumed during facility and utility maintenance.',
                'classes' => [
                    [
                        'code' => 'RMT-ELC',
                        'name' => 'Electrical Supplies',
                        'description' => 'Electrical repair and replacement materials.',
                        'series' => [
                            ['code' => 'LIGHT-BULB', 'name' => 'Light Bulbs', 'description' => 'LED and other replacement lamps.'],
                            ['code' => 'ELEC-TAPE', 'name' => 'Electrical Tape', 'description' => 'Insulating tape for electrical work.'],
                        ],
                    ],
                    [
                        'code' => 'RMT-PLM',
                        'name' => 'Plumbing Supplies',
                        'description' => 'Materials used for water system and facility plumbing repairs.',
                        'series' => [
                            ['code' => 'PVC-FIT', 'name' => 'PVC Fittings', 'description' => 'PVC elbows, tees, couplings, and adapters.'],
                            ['code' => 'SEALANT', 'name' => 'Sealants', 'description' => 'Thread seal tape and general-purpose sealants.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array<int, array{code: string, name: string, description: string}> */
    private function assetCategories(): array
    {
        return [
            ['code' => 'AST-ICT', 'name' => 'ICT Equipment', 'description' => 'Computers, network devices, and related equipment.'],
            ['code' => 'AST-OFF', 'name' => 'Office Equipment', 'description' => 'Machines and devices used for administrative work.'],
            ['code' => 'AST-FNF', 'name' => 'Furniture and Fixtures', 'description' => 'Movable office furniture and installed fixtures.'],
            ['code' => 'AST-MCH', 'name' => 'Machinery and Technical Equipment', 'description' => 'Operational machinery and specialized technical equipment.'],
            ['code' => 'AST-TRN', 'name' => 'Transportation Equipment', 'description' => 'Vehicles and other transportation-related equipment.'],
        ];
    }
}
