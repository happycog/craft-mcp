<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\elements\Asset;
use craft\helpers\ElementHelper;
use craft\services\Assets;
use happycog\craftmcp\exceptions\ModelSaveException;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class UpdateAsset
{
    public function __construct(
        protected Assets $assetsService,
    ) {
    }

    /**
     * @param array<string, mixed> $fieldData
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'update_asset',
        description: <<<'END'
        Update an existing asset in Craft CMS. Modify asset metadata, custom fields, and optionally replace the file.

        You can update the asset's title, custom field values, and replace the physical file while maintaining the same asset ID.
        Field validation rules are enforced according to the field type constraints.

        - assetId: Required ID of the asset to update
        - title: Optional new title for the asset
        - fieldData: Optional custom field data as key-value pairs
        - replaceFileUrl: Optional URL to download replacement file from
        - replaceFilePath: Optional local file path to replace file from

        After updating the asset always link the user back to the asset in the Craft control panel so they can review
        the changes in the context of the Craft UI.
        END
    )]
    public function update(
        #[Schema(type: 'number', description: 'Asset ID to update')]
        int $assetId,

        #[Schema(type: 'string', description: 'New title for the asset')]
        ?string $title = null,

        #[Schema(type: 'object', description: 'Custom field data as key-value pairs')]
        array $fieldData = [],

        #[Schema(type: 'string', description: 'URL to download replacement file from')]
        ?string $replaceFileUrl = null,

        #[Schema(type: 'string', description: 'Local file path to replace file from')]
        ?string $replaceFilePath = null
    ): array {
        // Validate that only one replacement method is provided
        throw_if($replaceFileUrl !== null && $replaceFilePath !== null, 'Only one of replaceFileUrl or replaceFilePath should be provided');

        // Get existing asset
        $asset = Asset::find()->id($assetId)->one();
        throw_unless($asset instanceof Asset, \InvalidArgumentException::class, "Asset with ID {$assetId} not found");

        // Update title if provided
        if ($title !== null) {
            $asset->title = $title;
        }

        // Update custom field data
        foreach ($fieldData as $handle => $value) {
            $asset->setFieldValue($handle, $value);
        }

        // Handle file replacement if requested
        $tempFilePath = null;
        try {
            if ($replaceFileUrl !== null || $replaceFilePath !== null) {
                if ($replaceFileUrl !== null) {
                    $tempFilePath = $this->downloadFromUrl($replaceFileUrl);
                    $newFilename = $this->getFilenameFromUrl($replaceFileUrl);
                } else {
                    throw_unless(file_exists($replaceFilePath), \InvalidArgumentException::class, "File does not exist: {$replaceFilePath}");
                    $tempFilePath = $replaceFilePath;
                    $newFilename = basename($replaceFilePath);
                }

                // Replace the asset file (this handles saving internally)
                Craft::$app->getAssets()->replaceAssetFile($asset, $tempFilePath, $newFilename);
            } else {
                // Save asset without file replacement
                throw_unless(Craft::$app->getElements()->saveElement($asset), ModelSaveException::class, $asset);
            }

            return [
                '_notes' => 'The asset was successfully updated.',
                'assetId' => $asset->id,
                'filename' => $asset->filename,
                'title' => $asset->title,
                'url' => $asset->getUrl(),
                'size' => $asset->size,
                'dateUpdated' => $asset->dateUpdated?->format('c'),
                'editUrl' => ElementHelper::elementEditorUrl($asset),
            ];
        } finally {
            // Clean up temporary file if we downloaded it
            if ($replaceFileUrl !== null && $tempFilePath !== null && file_exists($tempFilePath)) {
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