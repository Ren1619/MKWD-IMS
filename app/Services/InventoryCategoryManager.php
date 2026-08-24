<?php

namespace App\Services;

use App\InventoryCategoryType;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryClassCategory;
use App\Models\InventoryMajorCategory;
use App\Models\InventorySeriesCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LogicException;

class InventoryCategoryManager
{
    /** @param array<string, mixed> $data */
    public function create(InventoryCategoryType $type, array $data): Model
    {
        $modelClass = $type->modelClass();

        return $modelClass::query()->create($this->attributes($type, $data));
    }

    /** @param array<string, mixed> $data */
    public function update(InventoryCategoryType $type, int $categoryId, array $data): Model
    {
        $category = $this->find($type, $categoryId);
        $category->update($this->attributes($type, $data));

        return $category;
    }

    public function find(InventoryCategoryType $type, int $categoryId): Model
    {
        $modelClass = $type->modelClass();

        return $modelClass::query()->findOrFail($categoryId);
    }

    public function deletionBlockReason(InventoryCategoryType $type, Model $category): ?string
    {
        return match ($type) {
            InventoryCategoryType::Major => $this->majorCategory($category)->classCategories()->exists()
                ? 'Archive this major category instead. It still contains class categories.'
                : null,
            InventoryCategoryType::ClassCategory => $this->classCategory($category)->seriesCategories()->exists()
                ? 'Archive this class category instead. It still contains series categories.'
                : null,
            InventoryCategoryType::Series => $this->seriesCategory($category)->items()->exists()
                ? 'Archive this series category instead. Inventory items still use it.'
                : null,
            InventoryCategoryType::Asset => $this->assetCategory($category)->assets()->exists()
                ? 'Archive this asset category instead. Assets still use it.'
                : null,
        };
    }

    public function updateStatus(InventoryCategoryType $type, int $categoryId, bool $isActive): void
    {
        DB::transaction(function () use ($type, $categoryId, $isActive): void {
            $category = $this->find($type, $categoryId);

            match ($type) {
                InventoryCategoryType::Major => $this->updateMajorStatus($this->majorCategory($category), $isActive),
                InventoryCategoryType::ClassCategory => $this->updateClassStatus($this->classCategory($category), $isActive),
                InventoryCategoryType::Series => $this->updateSeriesStatus($this->seriesCategory($category), $isActive),
                InventoryCategoryType::Asset => $this->setStatus($category, $isActive),
            };
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(InventoryCategoryType $type, array $data): array
    {
        $attributes = Arr::only($data, ['code', 'name', 'description']);

        if ($parentColumn = $type->parentColumn()) {
            $attributes[$parentColumn] = $data['parent_id'];
        }

        return $attributes;
    }

    private function updateMajorStatus(InventoryMajorCategory $category, bool $isActive): void
    {
        $this->setStatus($category, $isActive);

        $category->classCategories()->with('seriesCategories')->get()->each(function (InventoryClassCategory $classCategory) use ($isActive): void {
            $this->setStatus($classCategory, $isActive);
            $classCategory->seriesCategories->each(fn (InventorySeriesCategory $seriesCategory) => $this->setStatus($seriesCategory, $isActive));
        });
    }

    private function updateClassStatus(InventoryClassCategory $category, bool $isActive): void
    {
        if ($isActive) {
            $this->setStatus($category->majorCategory()->firstOrFail(), true);
        }

        $this->setStatus($category, $isActive);
        $category->seriesCategories()->get()->each(fn (InventorySeriesCategory $seriesCategory) => $this->setStatus($seriesCategory, $isActive));
    }

    private function updateSeriesStatus(InventorySeriesCategory $category, bool $isActive): void
    {
        if ($isActive) {
            $classCategory = $category->classCategory()->firstOrFail();
            $this->setStatus($classCategory->majorCategory()->firstOrFail(), true);
            $this->setStatus($classCategory, true);
        }

        $this->setStatus($category, $isActive);
    }

    private function setStatus(Model $category, bool $isActive): void
    {
        if ((bool) $category->getAttribute('is_active') !== $isActive) {
            $category->update(['is_active' => $isActive]);
        }
    }

    private function majorCategory(Model $category): InventoryMajorCategory
    {
        if (! $category instanceof InventoryMajorCategory) {
            throw new LogicException('The resolved category is not a major category.');
        }

        return $category;
    }

    private function classCategory(Model $category): InventoryClassCategory
    {
        if (! $category instanceof InventoryClassCategory) {
            throw new LogicException('The resolved category is not a class category.');
        }

        return $category;
    }

    private function seriesCategory(Model $category): InventorySeriesCategory
    {
        if (! $category instanceof InventorySeriesCategory) {
            throw new LogicException('The resolved category is not a series category.');
        }

        return $category;
    }

    private function assetCategory(Model $category): InventoryAssetCategory
    {
        if (! $category instanceof InventoryAssetCategory) {
            throw new LogicException('The resolved category is not an asset category.');
        }

        return $category;
    }
}
