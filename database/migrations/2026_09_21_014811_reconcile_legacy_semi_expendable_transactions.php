<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('inventory_assets')
            ->where('accounting_classification', 'semi_expendable')
            ->orderBy('inventory_asset_id')
            ->chunkById(250, function ($assets): void {
                foreach ($assets as $asset) {
                    $hasReceipt = DB::table('inventory_asset_events')
                        ->where('inventory_asset_id', $asset->inventory_asset_id)
                        ->where('event_type', 'receipt')
                        ->exists();

                    if (! $hasReceipt) {
                        DB::table('inventory_asset_events')->insert([
                            'inventory_asset_id' => $asset->inventory_asset_id,
                            'event_type' => 'receipt',
                            'event_date' => $asset->acceptance_date ?: $asset->acquisition_date,
                            'reference_no' => $asset->inspection_acceptance_report_no ?: ($asset->purchase_order_no ?: $asset->property_number),
                            'amount' => $asset->acquisition_cost,
                            'quantity' => 1,
                            'notes' => 'Legacy semi-expendable receipt reconstructed without altering the original registration event.',
                            'details' => json_encode(['source' => 'semi_expendable_reconciliation_v1']),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $documents = DB::table('property_accountability_documents')
                        ->where('inventory_asset_id', $asset->inventory_asset_id)
                        ->where('document_type', 'ICS')
                        ->whereNull('value_class')
                        ->get();

                    foreach ($documents as $document) {
                        $expiresAt = $asset->semi_expendable_value_class === 'low'
                            ? Carbon::parse($document->issued_at)->addMonths((int) $document->estimated_useful_life_months)->toDateString()
                            : null;

                        DB::table('property_accountability_documents')->where('id', $document->id)->update([
                            'value_class' => $asset->semi_expendable_value_class,
                            'accountability_expires_at' => $expiresAt,
                        ]);

                        if ($expiresAt !== null && in_array($document->status, ['pending_recipient', 'active'], true)) {
                            DB::table('inventory_assets')->where('inventory_asset_id', $asset->inventory_asset_id)->update([
                                'accountability_expires_at' => $expiresAt,
                            ]);
                        }
                    }
                }
            }, 'inventory_asset_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('inventory_asset_events')
            ->where('event_type', 'receipt')
            ->where('notes', 'Legacy semi-expendable receipt reconstructed without altering the original registration event.')
            ->delete();
    }
};
