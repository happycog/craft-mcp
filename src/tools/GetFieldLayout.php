<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\base\FieldLayoutElement;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use happycog\craftmcp\exceptions\ModelSaveException;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class GetFieldLayout
{
    /**
     * @param array<int, array<string, mixed>> $tabs
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_field_layout',
        description: <<<'END'
        Get the details of a field layout by its ID, including tabs and fields with their properties.
        END
    )]
    public function update(
        #[Schema(type: 'integer', description: 'The ID of the field layout to update')]
        int $fieldLayoutId,
    ): array {
        $fieldsService = Craft::$app->getFields();

        // Get the field layout directly
        $fieldLayout = $fieldsService->getLayoutById($fieldLayoutId);
        \throw_unless($fieldLayout instanceof FieldLayout, "Field layout with ID {$fieldLayoutId} not found");        // Validate all field IDs exist before proceeding

        $fieldLayoutInfo = [];
        foreach ($fieldLayout->getTabs() as $tab) {
            $tabInfo = [
                'name' => $tab->name,
                'fields' => [],
            ];

            /** @var FieldLayoutElement $element */
            foreach ($tab->getElements() as $element) {
                $tabInfo[] = [
                    'uid' => $element->uid,
                    'type' => $element::class,
                ];
            }

            $fieldLayoutInfo['tabs'][] = $tabInfo;
        }

        return [
            '_notes' => 'Field layout currently contains the following elements.',
            'fieldLayout' => $fieldLayoutInfo,
        ];
    }
}
