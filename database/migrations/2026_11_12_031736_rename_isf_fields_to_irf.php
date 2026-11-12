<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->renameRequestFields('isf', 'irf');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->renameRequestFields('irf', 'isf');
    }

    private function renameRequestFields(string $from, string $to): void
    {
        Schema::table('supply_requests', function (Blueprint $table) use ($from, $to): void {
            foreach (['no', 'date', 'sequence'] as $field) {
                $table->renameColumn("{$from}_{$field}", "{$to}_{$field}");
            }
        });
        Schema::table('supply_requests', function (Blueprint $table) use ($from, $to): void {
            $table->renameIndex("supply_requests_{$from}_no_index", "supply_requests_{$to}_no_index");
            $table->renameIndex("supply_requests_{$from}_date_{$from}_sequence_unique", "supply_requests_{$to}_date_{$to}_sequence_unique");
        });
        Schema::table('department_liquidations', function (Blueprint $table) use ($from, $to): void {
            $table->renameColumn("{$from}_no", "{$to}_no");
        });
        DB::table('issuance_slips')->select(['id', 'snapshot'])->orderBy('id')
            ->chunkById(200, function (Collection $slips) use ($from, $to): void {
                foreach ($slips as $slip) {
                    $snapshot = json_decode($slip->snapshot, true, 512, JSON_THROW_ON_ERROR);
                    if (! is_array($snapshot) || ! array_key_exists("{$from}_no", $snapshot)) {
                        continue;
                    }
                    $snapshot["{$to}_no"] = $snapshot["{$from}_no"];
                    unset($snapshot["{$from}_no"]);
                    DB::table('issuance_slips')->where('id', $slip->id)->update(['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
                }
            });
    }
};
