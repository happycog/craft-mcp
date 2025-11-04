<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\models\Volume;
use craft\services\Volumes;
use craft\services\Fs;
use happycog\craftmcp\exceptions\ModelSaveException;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class CreateVolume
{
    public function __construct(
        protected Volumes $volumesService,
        protected Fs $fsService,
    ) {
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'create_volume',
        description: <<<'END'
        Create a new volume in Craft CMS. Volumes are logical containers that define where assets are stored and how they're accessed.

        Each volume must be linked to an existing file system that provides the underlying storage mechanism.
        You can configure volume-specific settings like subfolder paths and field layouts.

        - name: Required name for the volume
        - handle: Required unique handle (auto-generated if not provided)
        - fsId: Required file system ID to link the volume to
        - subpath: Optional subfolder path within the file system
        - settings: Optional volume-specific configuration

        After creating the volume always link the user back to the volume settings in the Craft control panel so they can review
        the changes in the context of the Craft UI.
        END
    )]
    public function create(
        #[Schema(type: 'string', description: 'Name for the volume')]
        string $name,

        #[Schema(type: 'string', description: 'File system handle to link the volume to')]
        string $fsHandle,

        #[Schema(type: 'string', description: 'Unique handle (auto-generated if not provided)')]
        ?string $handle = null,

        #[Schema(type: 'string', description: 'Subfolder path within the file system')]
        ?string $subpath = null,

        #[Schema(type: 'object', description: 'Volume-specific configuration settings')]
        array $settings = []
    ): array {
        // Validate file system exists
        $fs = $this->fsService->getFilesystemByHandle($fsHandle);
        throw_unless($fs, \InvalidArgumentException::class, "File system with handle '{$fsHandle}' does not exist");

        // Generate handle if not provided
        $handle ??= $this->generateHandle($name);

        // Create volume model
        $volume = new Volume([
            'name' => $name,
            'handle' => $handle,
            'subpath' => $subpath ?? '',
        ]);

        // Set file system
        $volume->setFsHandle($fsHandle);

        // Apply additional settings
        foreach ($settings as $key => $value) {
            if (property_exists($volume, $key)) {
                $volume->$key = $value;
            }
        }

        // Save volume
        throw_unless($this->volumesService->saveVolume($volume), ModelSaveException::class, $volume);

        return [
            '_notes' => 'The volume was successfully created.',
            'volumeId' => $volume->id,
            'name' => $volume->name,
            'handle' => $volume->handle,
            'fsHandle' => $volume->getFsHandle(),
            'subpath' => $volume->subpath,
            'editUrl' => $this->getVolumeEditUrl($volume),
        ];
    }

    private function generateHandle(string $name): string
    {
        // Convert name to handle format (lowercase, no spaces, alphanumeric + underscores)
        $handle = strtolower(trim($name));
        $handle = preg_replace('/[^a-z0-9_]/', '_', $handle) ?? $handle;
        $handle = preg_replace('/_+/', '_', $handle) ?? $handle;
        $handle = trim($handle, '_');

        // Ensure handle is not empty
        if (empty($handle)) {
            $handle = 'volume_' . uniqid();
        }

        // Ensure handle is unique
        $originalHandle = $handle;
        $counter = 1;
        while ($this->volumesService->getVolumeByHandle($handle)) {
            $handle = $originalHandle . '_' . $counter;
            $counter++;
        }

        return $handle;
    }

    private function getVolumeEditUrl(Volume $volume): string
    {
        $cpUrl = Craft::$app->getConfig()->general->cpUrl ?? '';
        return $cpUrl . '/settings/assets/volumes/' . $volume->id;
    }
}