<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\base\FsInterface;
use craft\models\Volume;
use craft\services\Fs;
use craft\services\Volumes;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class GetFileSystems
{
    public function __construct(
        protected Fs $fsService,
        protected Volumes $volumesService,
    ) {
    }

    /**
     * @param array<int>|null $fileSystemIds
     * @return array<int, array<string, mixed>>
     */
    #[McpTool(
        name: 'get_file_systems',
        description: <<<'END'
        Get file systems from Craft CMS. Retrieve all file systems or filter by specific file system IDs.

        File systems define the underlying storage mechanism for volumes (local folders, cloud storage, etc.).
        Returns file system configuration and connection status information.

        - fileSystemIds: Optional array of file system IDs to filter results. If not provided, returns all file systems.

        Note: Sensitive credentials are excluded from responses for security.
        END
    )]
    public function get(
        #[Schema(type: 'array', items: ['type' => 'number'], description: 'Optional list of file system IDs to limit results')]
        ?array $fileSystemIds = null
    ): array {
        $fileSystems = $this->fsService->getAllFilesystems();
        $result = [];

        foreach ($fileSystems as $fs) {
            if (!$fs instanceof FsInterface) {
                continue;
            }

            if (is_array($fileSystemIds) && $fileSystemIds !== [] && !in_array($fs->id, $fileSystemIds, true)) {
                continue;
            }

            $fileSystemData = [
                'id' => $fs->id,
                'name' => $fs->name,
                'handle' => $fs->handle,
                'type' => get_class($fs),
                'hasUrls' => $fs->hasUrls,
                'url' => $fs->url,
                'dateCreated' => $fs->dateCreated?->format('c'),
                'dateUpdated' => $fs->dateUpdated?->format('c'),
                'editUrl' => $this->getFileSystemEditUrl($fs),
                'settings' => $this->sanitizeSettingsForOutput($fs),
                'usageInfo' => $this->getUsageInfo($fs),
            ];

            $result[] = $fileSystemData;
        }

        return $result;
    }

    private function getFileSystemEditUrl(FsInterface $fs): string
    {
        $cpUrl = Craft::$app->getConfig()->general->cpUrl ?? '';
        return $cpUrl . '/settings/fs/' . $fs->handle;
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeSettingsForOutput(FsInterface $fs): array
    {
        $settings = [];

        // Get class name for type identification
        $className = get_class($fs);

        // Handle different file system types
        if ($className === 'craft\fs\Local') {
            // For local file systems, only include non-sensitive settings
            $settings['path'] = $fs->path ?? null;
        }

        // For other file system types (S3, GCS, etc.), we would add their
        // non-sensitive settings here, but exclude credentials like:
        // - Access keys
        // - Secret keys
        // - Passwords
        // - API tokens

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function getUsageInfo(FsInterface $fs): array
    {
        // Find volumes that use this file system
        $volumes = $this->volumesService->getAllVolumes();
        $usedByVolumes = [];

        foreach ($volumes as $volume) {
            if ($volume instanceof Volume && $volume->getFsHandle() === $fs->handle) {
                $usedByVolumes[] = [
                    'id' => $volume->id,
                    'name' => $volume->name,
                    'handle' => $volume->handle,
                ];
            }
        }

        return [
            'volumeCount' => count($usedByVolumes),
            'usedByVolumes' => $usedByVolumes,
            'canBeDeleted' => count($usedByVolumes) === 0,
        ];
    }
}