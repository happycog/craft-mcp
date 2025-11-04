<?php

namespace happycog\craftmcp\tools;

use craft\base\FsInterface;
use craft\models\Volume;
use craft\services\Fs;
use craft\services\Volumes;
use happycog\craftmcp\exceptions\ModelSaveException;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class DeleteFileSystem
{
    public function __construct(
        protected Fs $fsService,
        protected Volumes $volumesService,
    ) {
    }

     /**
      * @return array<string, mixed>
      */
     #[McpTool(
         name: 'delete_file_system',
         description: <<<'END'
         Delete a file system from Craft CMS. Removes the file system configuration and settings.

         File systems can only be deleted if they are not currently in use by any volumes.
         This is a safety measure to prevent breaking existing volume configurations.

         - fileSystemHandle: Required handle of the file system to delete (use handle instead of ID for reliability)

         The operation will fail if:
         - The file system doesn't exist
         - The file system is currently used by one or more volumes

         Before deletion, all volumes must be updated to use a different file system or be deleted themselves.
         END
     )]
     public function delete(
         #[Schema(type: 'string', description: 'Handle of the file system to delete')]
         string $fileSystemHandle
     ): array {
         // Find the file system by handle (the proper Craft API method)
         $fs = $this->fsService->getFilesystemByHandle($fileSystemHandle);
         throw_unless($fs instanceof FsInterface, \InvalidArgumentException::class, "File system with handle '{$fileSystemHandle}' not found");

        // Check if file system is in use by any volumes
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

        throw_unless(empty($usedByVolumes), \InvalidArgumentException::class, 
            "Cannot delete file system '{$fs->name}' because it is currently used by " . count($usedByVolumes) . " volume(s): " . 
            implode(', ', array_map(fn($v) => $v['name'], $usedByVolumes))
        );

        // Store info for response before deletion
        $fileSystemInfo = [
            'fileSystemId' => $fs->id,
            'name' => $fs->name,
            'handle' => $fs->handle,
            'type' => get_class($fs),
        ];

        // Delete file system
        throw_unless($this->fsService->removeFilesystem($fs), ModelSaveException::class, $fs);

        return [
            '_notes' => 'The file system was successfully deleted.',
            'deletedFileSystem' => $fileSystemInfo,
            'success' => true,
        ];
    }
}