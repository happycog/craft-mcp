<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\elements\Asset;
use craft\helpers\ElementHelper;
use craft\services\Assets;
use craft\services\Volumes;
use happycog\craftmcp\exceptions\ModelSaveException;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class CreateAsset
{
    public function __construct(
        protected Assets $assetsService,
        protected Volumes $volumesService,
    ) {
    }

    /**
     * @param array<string, mixed> $fieldData
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'create_asset',
        description: <<<'END'
        Create an asset in Craft CMS by downloading from a URL or uploading from a local file path.

        Assets in Craft are files (images, documents, videos, etc.) stored in volumes with associated metadata.
        You can specify custom field data for asset-specific fields like alt text, captions, etc.

        - volumeId: Required ID of the volume where the asset will be stored
        - url: URL to download the file from (primary method)
        - filePath: Local file path to upload from (alternative method)
        - filename: Optional custom filename (auto-generated from URL if not provided)
        - title: Optional asset title (defaults to filename if not provided)
        - fieldData: Optional custom field data as key-value pairs

        After creating the asset always link the user back to the asset in the Craft control panel so they can review
        the changes in the context of the Craft UI.
        END
    )]
    public function create(
        #[Schema(type: 'number', description: 'Volume ID where the asset will be stored')]
        int $volumeId,

        #[Schema(type: 'string', description: 'URL to download the file from')]
        ?string $url = null,

        #[Schema(type: 'string', description: 'Local file path to upload from')]
        ?string $filePath = null,

        #[Schema(type: 'string', description: 'Custom filename (auto-generated if not provided)')]
        ?string $filename = null,

        #[Schema(type: 'string', description: 'Asset title (defaults to filename if not provided)')]
        ?string $title = null,

        #[Schema(type: 'object', description: 'Custom field data as key-value pairs')]
        array $fieldData = []
    ): array {
        // Validate that either URL or filePath is provided
        throw_if($url === null && $filePath === null, 'Either url or filePath must be provided');
        throw_if($url !== null && $filePath !== null, 'Only one of url or filePath should be provided');

        // Validate volume exists
        $volume = $this->volumesService->getVolumeById($volumeId);
        throw_unless($volume, \InvalidArgumentException::class, "Volume with ID {$volumeId} does not exist");

        // Handle file creation
        $tempFilePath = null;
        try {
            if ($url !== null) {
                $tempFilePath = $this->downloadFromUrl($url);
                $filename ??= $this->getFilenameFromUrl($url);
            } else {
                throw_unless($filePath !== null, \InvalidArgumentException::class, 'Either url or filePath must be provided');
                throw_unless(file_exists($filePath), \InvalidArgumentException::class, "File does not exist: {$filePath}");
                $tempFilePath = $filePath;
                $filename ??= basename($filePath);
            }

            // Get root folder for the volume
            $rootFolder = $this->assetsService->getRootFolderByVolumeId($volumeId);
            
            // Create asset element
            $asset = new Asset();
            $asset->tempFilePath = $tempFilePath;
            $asset->volumeId = $volumeId;
            $asset->folderId = $rootFolder->id;
            $asset->filename = $filename;
            $asset->title = $title ?? pathinfo($filename, PATHINFO_FILENAME);
            $asset->newLocation = "{folder:{$rootFolder->id}}{$filename}";
            $asset->setScenario(Asset::SCENARIO_CREATE);

            // Set custom field data
            foreach ($fieldData as $handle => $value) {
                $asset->setFieldValue($handle, $value);
            }

            // Save asset with file
            throw_unless(Craft::$app->getElements()->saveElement($asset), ModelSaveException::class, $asset);

            return [
                '_notes' => 'The asset was successfully created.',
                'assetId' => $asset->id,
                'filename' => $asset->filename,
                'title' => $asset->title,
                'url' => $asset->getUrl(),
                'size' => $asset->size,
                'dateCreated' => $asset->dateCreated?->format('c'),
                'editUrl' => ElementHelper::elementEditorUrl($asset),
            ];
        } finally {
            // Clean up temporary file if we downloaded it
            if ($url !== null && $tempFilePath !== null && file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
        }
    }

    private function downloadFromUrl(string $url): string
    {
        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'craft_asset_');
        throw_unless($tempFile, 'Failed to create temporary file');

        // Download file with timeout handling
        $context = stream_context_create([
            'http' => [
                'timeout' => 30, // 30 second timeout
                'user_agent' => 'Craft CMS Asset Downloader',
            ],
        ]);

        $content = file_get_contents($url, false, $context);
        throw_unless($content !== false, "Failed to download file from URL: {$url}");

        // Write content to temporary file
        $written = file_put_contents($tempFile, $content);
        throw_unless($written !== false, 'Failed to write downloaded content to temporary file');

        return $tempFile;
    }

    private function getFilenameFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = basename($path ?: '');
        
        // If no filename found, generate one with .tmp extension
        if (empty($filename) || !str_contains($filename, '.')) {
            $filename = 'downloaded_file_' . uniqid() . '.tmp';
        }

        return $filename;
    }
}