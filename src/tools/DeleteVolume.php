<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\elements\Asset;
use craft\models\Volume;
use craft\services\Volumes;
use happycog\craftmcp\exceptions\ModelSaveException;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class DeleteVolume
{
    public function __construct(
        protected Volumes $volumesService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'delete_volume',
        description: <<<'END'
        Delete a volume from Craft CMS. The volume can only be deleted if it contains no assets.

        This operation performs safety checks to ensure the volume is empty before deletion.
        All associated field layouts and settings are removed as part of the deletion process.

        - volumeId: Required ID of the volume to delete
        END
    )]
    public function delete(
        #[Schema(type: 'number', description: 'Volume ID to delete')]
        int $volumeId
    ): array {
        // Get existing volume
        $volume = $this->volumesService->getVolumeById($volumeId);
        throw_unless($volume instanceof Volume, \InvalidArgumentException::class, "Volume with ID {$volumeId} not found");

        // Check if volume has any assets
        $assetCount = Asset::find()->volumeId($volumeId)->count();
        throw_if($assetCount > 0, \InvalidArgumentException::class, "Cannot delete volume '{$volume->name}' because it contains {$assetCount} assets. Please delete all assets first.");

        // Store volume info before deletion
        $name = $volume->name;
        $handle = $volume->handle;

        // Delete the volume (this also removes field layouts and settings)
        throw_unless($this->volumesService->deleteVolume($volume), ModelSaveException::class, $volume);

        return [
            '_notes' => 'The volume was successfully deleted.',
            'deletedVolumeId' => $volumeId,
            'name' => $name,
            'handle' => $handle,
        ];
    }
}