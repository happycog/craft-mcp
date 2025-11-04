<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\elements\Asset;
use craft\models\Volume;
use craft\services\Volumes;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class GetVolumes
{
    public function __construct(
        protected Volumes $volumesService,
    ) {
    }

    /**
     * @param array<int>|null $volumeIds
     * @return array<int, array<string, mixed>>
     */
    #[McpTool(
        name: 'get_volumes',
        description: <<<'END'
        Get volumes from Craft CMS. Retrieve all volumes or filter by specific volume IDs.

        Volumes are logical containers that define where assets are stored and accessed.
        Returns volume settings, file system details, field layouts, and usage information.

        - volumeIds: Optional array of volume IDs to filter results. If not provided, returns all volumes.
        END
    )]
    public function get(
        #[Schema(type: 'array', items: ['type' => 'number'], description: 'Optional list of volume IDs to limit results')]
        ?array $volumeIds = null
    ): array {
        $volumes = $this->volumesService->getAllVolumes();
        $result = [];

        foreach ($volumes as $volume) {
            if (!$volume instanceof Volume) {
                continue;
            }

            if (is_array($volumeIds) && $volumeIds !== [] && !in_array($volume->id, $volumeIds, true)) {
                continue;
            }

            $fs = $volume->getFs();
            $assetCount = Asset::find()->volumeId($volume->id)->count();

            $volumeData = [
                'id' => $volume->id,
                'name' => $volume->name,
                'handle' => $volume->handle,
                'subpath' => $volume->subpath,
                'editUrl' => $this->getVolumeEditUrl($volume),
                'fileSystem' => [
                    'handle' => $fs->handle,
                    'name' => $fs->name,
                    'type' => get_class($fs),
                ],
                'assetCount' => $assetCount,
            ];

            // Add field layout information
            $fieldLayout = $volume->getFieldLayout();
            $customFields = [];
            foreach ($fieldLayout->getCustomFields() as $field) {
                $customFields[] = [
                    'id' => $field->id,
                    'name' => $field->name,
                    'handle' => $field->handle,
                        'type' => get_class($field),
                    'required' => $field->required,
                ];
            }
            $volumeData['fieldLayout'] = [
                'id' => $fieldLayout->id,
                'customFields' => $customFields,
            ];

            $result[] = $volumeData;
        }

        return $result;
    }

    private function getVolumeEditUrl(Volume $volume): string
    {
        $cpUrl = Craft::$app->getConfig()->general->cpUrl ?? '';
        return $cpUrl . '/settings/assets/volumes/' . $volume->id;
    }
}