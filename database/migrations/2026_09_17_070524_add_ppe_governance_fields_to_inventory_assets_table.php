<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->string('serial_number', 100)->nullable()->change();
            $table->string('old_property_number', 100)->nullable()->after('property_number');
            $table->string('manufacturer')->nullable()->after('brand');
            $table->text('specifications')->nullable()->after('description');
            $table->string('acquisition_method', 50)->nullable()->after('acquisition_date');
            $table->string('supplier_name')->nullable()->after('acquisition_method');
            $table->string('purchase_order_no', 120)->nullable()->after('supplier_name');
            $table->string('inspection_acceptance_report_no', 120)->nullable()->after('purchase_order_no');
            $table->string('invoice_no', 120)->nullable()->after('inspection_acceptance_report_no');
            $table->string('disbursement_voucher_no', 120)->nullable()->after('invoice_no');
            $table->date('delivery_date')->nullable()->after('disbursement_voucher_no');
            $table->date('inspection_date')->nullable()->after('delivery_date');
            $table->date('acceptance_date')->nullable()->after('inspection_date');
            $table->decimal('directly_attributable_costs', 14, 2)->default(0)->after('acquisition_cost');
            $table->string('accounting_policy_version', 30)->nullable()->after('accounting_classification');
            $table->decimal('capitalization_threshold_applied', 14, 2)->nullable()->after('accounting_policy_version');
            $table->string('depreciation_method', 40)->nullable()->after('capitalization_threshold_applied');
            $table->string('depreciation_start_rule', 40)->nullable()->after('depreciation_method');
            $table->boolean('is_depreciable')->default(true)->after('depreciation_start_rule');
            $table->string('accounting_status', 30)->default('recognized')->index()->after('is_depreciable');
            $table->date('derecognized_at')->nullable()->index()->after('accounting_status');
        });

        DB::table('inventory_assets')->orderBy('inventory_asset_id')->chunkById(250, function ($assets): void {
            foreach ($assets as $asset) {
                $isPpe = $asset->accounting_classification === 'ppe';
                $derecognizedAt = $asset->lifecycle_status === 'disposed'
                    ? substr((string) $asset->updated_at, 0, 10)
                    : null;

                DB::table('inventory_assets')->where('inventory_asset_id', $asset->inventory_asset_id)->update([
                    'accounting_policy_version' => '2026-01',
                    'capitalization_threshold_applied' => 50000,
                    'depreciation_method' => $isPpe ? 'straight_line' : null,
                    'depreciation_start_rule' => $isPpe ? 'first_half_month' : null,
                    'is_depreciable' => $isPpe,
                    'accounting_status' => $derecognizedAt ? 'derecognized' : 'recognized',
                    'derecognized_at' => $derecognizedAt,
                ]);

                $events = [[
                    'inventory_asset_id' => $asset->inventory_asset_id,
                    'event_type' => 'registered',
                    'event_date' => $asset->acquisition_date,
                    'reference_no' => $asset->property_number,
                    'amount' => $asset->acquisition_cost,
                    'notes' => 'Opening registration event migrated from the existing PPE register.',
                    'details' => json_encode(['source' => 'legacy_register']),
                    'created_at' => $asset->created_at,
                    'updated_at' => $asset->created_at,
                ]];

                if ((float) $asset->impairment_losses > 0) {
                    $events[] = [
                        'inventory_asset_id' => $asset->inventory_asset_id,
                        'event_type' => 'impairment',
                        'event_date' => $asset->appraisal_date ?: $asset->acquisition_date,
                        'reference_no' => null,
                        'amount' => $asset->impairment_losses,
                        'notes' => 'Opening impairment balance migrated from the existing PPE register.',
                        'details' => json_encode(['source' => 'legacy_register']),
                        'created_at' => $asset->created_at,
                        'updated_at' => $asset->created_at,
                    ];
                }

                if ($asset->loss_report_no) {
                    $events[] = [
                        'inventory_asset_id' => $asset->inventory_asset_id,
                        'event_type' => 'loss',
                        'event_date' => $asset->loss_report_date ?: $asset->acquisition_date,
                        'reference_no' => $asset->loss_report_no,
                        'amount' => null,
                        'notes' => $asset->loss_circumstances,
                        'details' => json_encode(['loss_type' => $asset->loss_type, 'source' => 'legacy_register']),
                        'created_at' => $asset->created_at,
                        'updated_at' => $asset->created_at,
                    ];
                }

                if ($derecognizedAt) {
                    $events[] = [
                        'inventory_asset_id' => $asset->inventory_asset_id,
                        'event_type' => 'disposal',
                        'event_date' => $derecognizedAt,
                        'reference_no' => null,
                        'amount' => $asset->disposal_value,
                        'notes' => 'Opening disposal migrated from the existing PPE register.',
                        'details' => json_encode(['method' => $asset->disposal_method, 'source' => 'legacy_register']),
                        'created_at' => $asset->updated_at,
                        'updated_at' => $asset->updated_at,
                    ];
                    $events[] = [
                        'inventory_asset_id' => $asset->inventory_asset_id,
                        'event_type' => 'derecognition',
                        'event_date' => $derecognizedAt,
                        'reference_no' => null,
                        'amount' => null,
                        'notes' => 'Opening derecognition migrated from the existing PPE register.',
                        'details' => json_encode(['source' => 'legacy_register']),
                        'created_at' => $asset->updated_at,
                        'updated_at' => $asset->updated_at,
                    ];
                }

                DB::table('inventory_asset_events')->insert($events);
            }
        }, 'inventory_asset_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropIndex(['accounting_status']);
            $table->dropIndex(['derecognized_at']);
            $table->dropColumn([
                'old_property_number',
                'manufacturer',
                'specifications',
                'acquisition_method',
                'supplier_name',
                'purchase_order_no',
                'inspection_acceptance_report_no',
                'invoice_no',
                'disbursement_voucher_no',
                'delivery_date',
                'inspection_date',
                'acceptance_date',
                'directly_attributable_costs',
                'accounting_policy_version',
                'capitalization_threshold_applied',
                'depreciation_method',
                'depreciation_start_rule',
                'is_depreciable',
                'accounting_status',
                'derecognized_at',
            ]);
            $table->string('serial_number', 100)->nullable(false)->change();
        });
    }
};
