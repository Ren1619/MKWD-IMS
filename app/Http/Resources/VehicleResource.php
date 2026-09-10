<?php

namespace App\Http\Resources;

use App\Models\InventoryAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InventoryAsset */
class VehicleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->inventory_asset_id,
            'property_tag_uuid' => $this->property_tag_uuid,
            'property_number' => $this->property_number,
            'serial_number' => $this->serial_number,
            'name' => $this->name,
            'brand' => $this->brand,
            'model' => $this->model,
            'description' => $this->description,
            'category' => [
                'code' => $this->category->code,
                'name' => $this->category->name,
            ],
            'subcategory' => $this->whenLoaded('subcategory', fn () => $this->subcategory ? [
                'code' => $this->subcategory->code,
                'name' => $this->subcategory->name,
            ] : null),
            'unit_of_measure' => $this->unit_of_measure,
            'location' => $this->location,
            'nature_of_occupancy' => $this->nature_of_occupancy,
            'fund_cluster' => $this->fund_cluster,
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'acquisition_cost' => $this->acquisition_cost,
            'lifecycle_status' => $this->lifecycle_status->value,
            'condition_status' => $this->condition_status->value,
            'custody_status' => $this->custody_status,
            'current_custodian' => $this->whenLoaded('currentCustodian', fn () => $this->currentCustodian ? [
                'code' => $this->currentCustodian->code,
                'name' => $this->currentCustodian->name,
            ] : null),
            'current_borrower' => $this->whenLoaded('activeBorrowing', fn () => $this->activeBorrowing?->borrowerReference ? [
                'code' => $this->activeBorrowing->borrowerReference->code,
                'name' => $this->activeBorrowing->borrowerReference->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
