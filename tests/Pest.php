<?php

use App\Models\InventorySeriesCategory;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Services\PreprocurementWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/** @return array<string, mixed> */
function procurementIntakeData(): array
{
    return ['document_kind' => 'PR', 'procurement_category' => 'supplies', 'preprocurement' => ['approved_request_reference' => 'ENDUSER-1', 'end_user' => 'Field employee', 'department' => 'Operations', 'division' => 'PWSD', 'is_replacement' => false]];
}

function prepareAndSignProcurement(ProcurementRequest $record, User $gm): void
{
    Storage::fake('local');
    $creator = $record->creator;
    $route = route('inventory.procurement.transition', $record);
    foreach (PreprocurementWorkflow::categories()[$record->procurement_category]['documents'] as $key => $document) {
        test()->actingAs($creator)->post(route('inventory.procurement.attachments.store', $record), ['document_key' => $key, 'file' => UploadedFile::fake()->create($key.'.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
    }
    test()->actingAs($creator)->patch($route, ['action' => 'submit_intake', 'attested' => true, 'intake_details' => procurementIntakeData()['preprocurement']])->assertSessionHasNoErrors();
    test()->actingAs($gm)->patch($route, ['action' => 'verify_documents', 'attested' => true])->assertSessionHasNoErrors();
    $preparedBy = User::factory()->inventoryManager()->create();
    $recommending = User::factory()->inventoryManager()->create();
    $countersigning = User::factory()->inventoryManager()->create();
    $category = InventorySeriesCategory::factory()->create();
    test()->actingAs($creator)->patch($route, [
        'action' => 'prepare_document', 'attested' => true,
        'preparation' => ['requisition_type' => $record->document_kind === 'JO' ? 'Repair' : 'Common supplies', 'charge_account' => 'ACCOUNT-1', 'abc' => 100000, 'caf_number' => 'CAF-1', 'place_of_delivery' => 'Main office', 'prepared_by_division' => 'PMMD', 'prepared_by_user_id' => $preparedBy->id, 'recommending_user_id' => $recommending->id, 'countersigning_user_id' => $countersigning->id, 'gm_user_id' => $gm->id, 'target_completion_date' => '2026-12-31', 'estimated_cost' => 5000, 'work_description' => 'Repair pump and test operation.'],
        'line_categories' => $record->lines()->where('item_kind', 'consumable')->whereNull('inventory_item_id')->get()->map(fn ($line): array => ['line_id' => $line->id, 'series_category_id' => $line->series_category_id ?? $category->getKey()])->all(),
        'line_estimates' => $record->lines()->get()->map(fn ($line): array => ['line_id' => $line->id, 'estimated_unit_cost' => max(1, (float) $line->estimated_unit_cost)])->all(),
    ])->assertSessionHasNoErrors();
    foreach ([$preparedBy, $recommending, $countersigning] as $signer) {
        test()->actingAs($signer)->patch($route, ['action' => 'sign', 'attested' => true])->assertSessionHasNoErrors();
    }
}
