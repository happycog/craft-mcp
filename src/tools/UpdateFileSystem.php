<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\base\FsInterface;
use craft\fs\Local;
use craft\services\Fs;
use happycog\craftmcp\exceptions\ModelSaveException;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class UpdateFileSystem
{
    public function __construct(
        protected Fs $fsService,
    ) {
    }

    /**
     * @param array<string, mixed> $attributeAndSettingData
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'update_file_system',
        description: <<<'END'
        Update an existing file system in Craft CMS. Modify file system settings and configuration.

        You can update basic properties like name and handle, as well as file system-specific settings.
        For local file systems, you can update the path and URL settings.

        - fileSystemId: Required ID of the file system to update
        - attributeAndSettingData: Object containing file system attributes and settings to update

        Supported attributes:
        - name: File system name
        - handle: File system handle (must be unique)
        - hasUrls: Whether files have public URLs
        - url: Base URL for public files
        - settings: File system-specific settings (e.g., path for local file systems)

        After updating the file system always link the user back to the file system settings in the Craft control panel so they can review
        the changes in the context of the Craft UI.
        END
    )]
    public function update(
        #[Schema(type: 'number', description: 'ID of the file system to update')]
        int $fileSystemId,

        #[Schema(type: 'object', description: 'File system attributes and settings to update')]
        array $attributeAndSettingData
    ): array {
        // Get existing file system by handle first
        $allFileSystems = $this->fsService->getAllFilesystems();
        $fs = null;
        foreach ($allFileSystems as $filesystem) {
            if ($filesystem->id === $fileSystemId) {
                $fs = $filesystem;
                break;
            }
        }
        throw_unless($fs instanceof FsInterface, \InvalidArgumentException::class, "File system with ID {$fileSystemId} not found");

        // Update basic attributes
        if (isset($attributeAndSettingData['name']) && is_string($attributeAndSettingData['name'])) {
            $fs->name = $attributeAndSettingData['name'];
        }

        if (isset($attributeAndSettingData['handle']) && is_string($attributeAndSettingData['handle'])) {
            $newHandle = $attributeAndSettingData['handle'];
            // Validate handle uniqueness if it's changing
            if ($newHandle !== $fs->handle) {
                $existingFs = $this->fsService->getFilesystemByHandle($newHandle);
                throw_unless($existingFs === null, \InvalidArgumentException::class, "Handle '{$newHandle}' is already in use by another file system");
                $fs->handle = $newHandle;
            }
        }

        if (isset($attributeAndSettingData['hasUrls'])) {
            $fs->hasUrls = (bool) $attributeAndSettingData['hasUrls'];
        }

        if (isset($attributeAndSettingData['url']) && is_string($attributeAndSettingData['url'])) {
            $fs->url = $attributeAndSettingData['url'];
        }

        // Update file system-specific settings
        if (isset($attributeAndSettingData['settings']) && is_array($attributeAndSettingData['settings'])) {
            $this->updateFileSystemSpecificSettings($fs, $attributeAndSettingData['settings']);
        }

        // Save file system
        throw_unless($this->fsService->saveFilesystem($fs), ModelSaveException::class, $fs);

        return [
            '_notes' => 'The file system was successfully updated.',
            'fileSystemId' => $fs->id,
            'name' => $fs->name,
            'handle' => $fs->handle,
            'type' => get_class($fs),
            'hasUrls' => $fs->hasUrls,
            'url' => $fs->url,
            'dateCreated' => $fs->dateCreated?->format('c'),
            'dateUpdated' => $fs->dateUpdated?->format('c'),
            'editUrl' => $this->getFileSystemEditUrl($fs),
            'settings' => $this->sanitizeSettingsForOutput($fs),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function updateFileSystemSpecificSettings(FsInterface $fs, array $settings): void
    {
        // Handle different file system types
        if ($fs instanceof Local) {
            $this->updateLocalFileSystemSettings($fs, $settings);
        }

        // Add handling for other file system types (S3, GCS, etc.) here when needed
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function updateLocalFileSystemSettings(Local $fs, array $settings): void
    {
        if (isset($settings['path'])) {
            throw_unless(is_string($settings['path']), \InvalidArgumentException::class, 'Local file system path must be a string');
            $fs->path = $settings['path'];
        }

        // Add other local file system specific settings as needed
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

        if ($fs instanceof Local) {
            $settings['path'] = $fs->path;
        }

        // Add other non-sensitive settings for other file system types
        // Exclude credentials like access keys, secret keys, passwords, etc.

        return $settings;
    }
}