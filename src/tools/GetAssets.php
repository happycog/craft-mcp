<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\elements\Asset;
use craft\helpers\ElementHelper;
use craft\services\Assets;
use craft\services\Volumes;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class GetAssets
{
    public function __construct(
        protected Assets $assetsService,
        protected Volumes $volumesService,
    ) {
    }

    /**
     * @param array<int>|null $assetIds
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_assets',
        description: <<<'END'
        Get assets from Craft CMS. Retrieve all assets or filter by various criteria.

        Assets include file properties (size, dimensions, mimetype), URLs, custom fields, and volume information.
        For images, transform URLs are provided when applicable.

        Parameters:
        - assetIds: Optional array of asset IDs to filter results
        - volumeId: Optional volume ID to filter assets by volume
        - search: Optional search term to find assets by filename or title
        - limit: Optional limit on number of results returned
        END
    )]
    public function get(
        #[Schema(type: 'array', items: ['type' => 'number'], description: 'Optional list of asset IDs to limit results')]
        ?array $assetIds = null,
        
        #[Schema(type: 'integer', description: 'Optional volume ID to filter assets by volume')]
        ?int $volumeId = null,
        
        #[Schema(type: 'string', description: 'Optional search term to find assets by filename or title')]
        ?string $search = null,
        
        #[Schema(type: 'integer', description: 'Optional limit on number of results returned')]
        ?int $limit = null
    ): array {
        $query = Asset::find();

        if (is_array($assetIds) && $assetIds !== []) {
            $query->id($assetIds);
        }
        
        if ($volumeId !== null) {
            $volume = $this->volumesService->getVolumeById($volumeId);
            if (!$volume) {
                throw new \InvalidArgumentException("Volume with ID {$volumeId} not found");
            }
            $query->volumeId($volumeId);
        }
        
        if ($search !== null) {
            $query->search($search);
        }
        
        if ($limit !== null) {
            $query->limit($limit);
        }

        $assets = $query->all();
        $result = [];

        foreach ($assets as $asset) {
            if (!$asset instanceof Asset) {
                continue;
            }

            $assetData = [
                'id' => $asset->id,
                'filename' => $asset->filename,
                'title' => $asset->title,
                'url' => $asset->getUrl(),
                'size' => $asset->size,
                'kind' => $asset->kind,
                'width' => $asset->width,
                'height' => $asset->height,
                'mimeType' => $asset->mimeType,
                'dateCreated' => $asset->dateCreated?->format('c'),
                'dateUpdated' => $asset->dateUpdated?->format('c'),
                'editUrl' => ElementHelper::elementEditorUrl($asset),
                'volume' => [
                    'id' => $asset->volumeId,
                    'name' => $asset->getVolume()->name,
                    'handle' => $asset->getVolume()->handle,
                ],
            ];

            // Add transform URLs for images
            if ($asset->kind === 'image') {
                $assetData['transforms'] = [
                    'thumbnail' => $asset->getUrl(['width' => 150, 'height' => 150, 'mode' => 'crop']),
                    'small' => $asset->getUrl(['width' => 300, 'height' => 300, 'mode' => 'fit']),
                    'medium' => $asset->getUrl(['width' => 600, 'height' => 600, 'mode' => 'fit']),
                ];
            }

            // Add custom field data
            $customFields = [];
            $fieldLayout = $asset->getFieldLayout();
            if ($fieldLayout) {
                foreach ($fieldLayout->getCustomFields() as $field) {
                    if ($field->handle !== null) {
                        $customFields[$field->handle] = $asset->getFieldValue($field->handle);
                    }
                }
            }
            $assetData['customFields'] = $customFields;

            $result[] = $assetData;
        }

        return [
            'assets' => $result,
            'count' => count($result),
        ];
    }
}