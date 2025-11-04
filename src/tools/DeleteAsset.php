<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\elements\Asset;
use craft\services\Assets;
use craft\services\Elements;
use happycog\craftmcp\exceptions\ModelSaveException;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class DeleteAsset
{
    public function __construct(
        protected Assets $assetsService,
        protected Elements $elementsService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'delete_asset',
        description: <<<'END'
        Delete an asset from Craft CMS. This removes both the asset record and associated files.

        The deletion is safe and will handle removal from any referencing entries automatically.
        This is a destructive operation that cannot be undone.

        - assetId: Required ID of the asset to delete
        END
    )]
    public function delete(
        #[Schema(type: 'number', description: 'Asset ID to delete')]
        int $assetId
    ): array {
        // Get existing asset
        $asset = Asset::find()->id($assetId)->one();
        throw_unless($asset instanceof Asset, \InvalidArgumentException::class, "Asset with ID {$assetId} not found");

        // Store asset info before deletion
        $filename = $asset->filename;
        $title = $asset->title;

        // Delete the asset (this also removes the physical file and handles relationship cleanup)
        throw_unless($this->elementsService->deleteElement($asset), ModelSaveException::class, $asset);

        return [
            '_notes' => 'The asset was successfully deleted.',
            'deletedAssetId' => $assetId,
            'filename' => $filename,
            'title' => $title,
        ];
    }
}