<?php

namespace App\Services;

use App\Models\InventoryAsset;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class PropertyTagService
{
    public function scanUrl(InventoryAsset $asset): string
    {
        return route('property-tags.show', ['asset' => $asset->property_tag_uuid]);
    }

    public function dataUri(InventoryAsset $asset): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(360, 2),
            new SvgImageBackEnd,
        );
        $svg = (new Writer($renderer))->writeString($this->scanUrl($asset));

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
