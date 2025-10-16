<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\models\Volume;
use craft\services\Volumes;
use craft\services\Fs;
use craft\services\Fields;
use happycog\craftmcp\exceptions\ModelSaveException;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class UpdateVolume
{
    public function __construct(
        protected Volumes $volumesService,
        protected Fs $fsService,
        protected Fields $fieldsService,
    ) {
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'update_volume',
        description: <<<'END'
        Update an existing volume in Craft CMS. Modify volume settings, field layouts, and file system associations.

        You can update the volume's name, handle, file system association, subfolder path, and other configuration.
        Field layout management allows you to update the asset field layouts for the volume.

        - volumeId: Required ID of the volume to update
        - name: Optional new name for the volume
        - handle: Optional new handle (must be unique)
        - fsId: Optional new file system ID to migrate to
        - subpath: Optional new subfolder path
        - settings: Optional volume-specific configuration

        After updating the volume always link the user back to the volume settings in the Craft control panel so they can review
        the changes in the context of the Craft UI.
        END
    )]
    public function update(
        #[Schema(type: 'number', description: 'Volume ID to update')]
        int $volumeId,

        #[Schema(type: 'string', description: 'New name for the volume')]
        ?string $name = null,

        #[Schema(type: 'string', description: 'New unique handle')]
        ?string $handle = null,

        #[Schema(type: 'string', description: 'New file system handle to migrate to')]
        ?string $fsHandle = null,

        #[Schema(type: 'string', description: 'New subfolder path')]
        ?string $subpath = null,

        #[Schema(type: 'object', description: 'Volume-specific configuration settings')]
        array $settings = []
    ): array {
        // Get existing volume
        $volume = $this->volumesService->getVolumeById($volumeId);
        throw_unless($volume instanceof Volume, \InvalidArgumentException::class, "Volume with ID {$volumeId} not found");

        // Update name if provided
        if ($name !== null) {
            $volume->name = $name;
        }

        // Update handle if provided
        if ($handle !== null) {
            // Check for handle uniqueness (excluding current volume)
            $existingVolume = $this->volumesService->getVolumeByHandle($handle);
            throw_if($existingVolume && $existingVolume->id !== $volumeId, \InvalidArgumentException::class, "Handle '{$handle}' is already in use");
            $volume->handle = $handle;
        }

        // Update file system if provided
        if ($fsHandle !== null) {
            $fs = $this->fsService->getFilesystemByHandle($fsHandle);
            throw_unless($fs, \InvalidArgumentException::class, "File system with handle '{$fsHandle}' does not exist");
            $volume->setFsHandle($fsHandle);
        }

        // Update subpath if provided
        if ($subpath !== null) {
            $volume->subpath = $subpath;
        }

        // Apply additional settings
        foreach ($settings as $key => $value) {
            if (property_exists($volume, $key)) {
                $volume->$key = $value;
            }
        }

        // Save volume
        throw_unless($this->volumesService->saveVolume($volume), ModelSaveException::class, $volume);

        return [
            '_notes' => 'The volume was successfully updated.',
            'volumeId' => $volume->id,
            'name' => $volume->name,
            'handle' => $volume->handle,
            'fsHandle' => $volume->getFsHandle(),
            'subpath' => $volume->subpath,
            'editUrl' => $this->getVolumeEditUrl($volume),
        ];
    }

    private function getVolumeEditUrl(Volume $volume): string
    {
        $cpUrl = Craft::$app->getConfig()->general->cpUrl ?? '';
        return $cpUrl . '/settings/assets/volumes/' . $volume->id;
    }
}