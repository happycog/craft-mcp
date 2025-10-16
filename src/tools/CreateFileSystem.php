<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\base\FsInterface;
use craft\fs\Local;
use craft\services\Fs;
use happycog\craftmcp\exceptions\ModelSaveException;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class CreateFileSystem
{
    public function __construct(
        protected Fs $fsService,
    ) {
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'create_file_system',
        description: <<<'END'
        Create a new file system in Craft CMS. File systems define the underlying storage mechanism for volumes.

        Craft supports local file systems by default. Additional file system types (Amazon S3, Google Cloud Storage)
        require their respective plugins to be installed.

        Currently supported types:
        - local: Local folder storage on the server

        For local file systems:
        - name: Required name for the file system
        - handle: Required unique handle (auto-generated if not provided)
        - type: Must be "local" for local file systems
        - settings: File system configuration including 'path' for local file systems

        After creating the file system always link the user back to the file system settings in the Craft control panel so they can review
        the changes in the context of the Craft UI.
        END
    )]
    public function create(
        #[Schema(type: 'string', description: 'Name for the file system')]
        string $name,

        #[Schema(type: 'string', description: 'Type of file system (currently only "local" supported)')]
        string $type,

        #[Schema(type: 'string', description: 'Unique handle (auto-generated if not provided)')]
        ?string $handle = null,

        #[Schema(type: 'object', description: 'File system configuration settings (e.g., path for local)')]
        array $settings = []
    ): array {
        // Validate type
        $supportedTypes = ['local'];
        throw_unless(in_array($type, $supportedTypes, true), \InvalidArgumentException::class, "Unsupported file system type: {$type}. Supported types: " . implode(', ', $supportedTypes));

        // Generate handle if not provided
        $handle ??= $this->generateHandle($name);

        // Create file system based on type
        $fs = $this->createLocalFilesystem($name, $handle, $settings);

        // Save file system
        throw_unless($this->fsService->saveFilesystem($fs), ModelSaveException::class, $fs);

        return [
            '_notes' => 'The file system was successfully created.',
            'fileSystemId' => $fs->id,
            'name' => $fs->name,
            'handle' => $fs->handle,
            'type' => get_class($fs),
            'hasUrls' => $fs->hasUrls,
            'url' => $fs->url,
            'dateCreated' => $fs->dateCreated?->format('c'),
            'editUrl' => $this->getFileSystemEditUrl($fs),
            'settings' => $this->sanitizeSettingsForOutput($fs),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function createLocalFilesystem(string $name, string $handle, array $settings): Local
    {
        // Validate required settings for local file system
        throw_unless(isset($settings['path']) && is_string($settings['path']), \InvalidArgumentException::class, 'Local file system requires a "path" setting');

        $config = [
            'name' => $name,
            'handle' => $handle,
            'path' => $settings['path'],
        ];

        // Add optional settings
        if (isset($settings['hasUrls'])) {
            $config['hasUrls'] = (bool) $settings['hasUrls'];
        }

        if (isset($settings['url'])) {
            $config['url'] = $settings['url'];
        }

        return new Local($config);
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
            $handle = 'filesystem_' . uniqid();
        }

        // Ensure handle is unique
        $originalHandle = $handle;
        $counter = 1;
        while ($this->fsService->getFilesystemByHandle($handle)) {
            $handle = $originalHandle . '_' . $counter;
            $counter++;
        }

        return $handle;
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

        // Add other non-sensitive settings as needed
        // Note: We deliberately exclude any credentials or sensitive information

        return $settings;
    }
}