<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\base\FieldLayoutElement;
use craft\fieldlayoutelements\BaseField;
use craft\fieldlayoutelements\BaseNativeField;
use craft\fieldlayoutelements\BaseUiElement;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use happycog\craftmcp\exceptions\ModelSaveException;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class UpdateFieldLayout
{
    /**
     * @param array<int, array<string, mixed>> $tabs
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'update_field_layout',
        description: <<<'END'
        Update a field layout by organizing all field layout elements (custom fields, native fields like title,
        and UI elements) into tabs. Works with any Craft model that has a field layout (entry types, users,
        assets, etc.).

        To retain existing elements including native fields like title and UI elements, use get_field_layout
        to get the complete structure, modify it as needed, and pass it back using the 'elements' format.

        After updating the field layout always link the user back to the relevant settings in the Craft control
        panel so they can review the changes in the context of the Craft UI.
        END
    )]
    public function update(
        #[Schema(type: 'integer', description: 'The ID of the field layout to update')]
        int $fieldLayoutId,

        #[Schema(
            type: 'array',
            description: 'Array of tabs with elements structure from get_field_layout',
            items: [
                'type' => 'object',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                        'description' => 'The display name for the tab'
                    ],
                    'elements' => [
                        'type' => 'array',
                        'description' => 'Complete element structures from get_field_layout',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'uid' => ['type' => 'string'],
                                'type' => ['type' => 'string'],
                                'width' => ['type' => 'integer', 'default' => 100]
                            ]
                        ]
                    ]
                ],
                'required' => ['name', 'elements']
            ]
        )]
        array $tabs
    ): array {
        $fieldsService = Craft::$app->getFields();

        // Get the field layout directly
        $fieldLayout = $fieldsService->getLayoutById($fieldLayoutId);
        \throw_unless($fieldLayout instanceof FieldLayout, "Field layout with ID {$fieldLayoutId} not found");

        // Create a map of existing elements by UID for preservation
        $existingElementsByUid = [];
        foreach ($fieldLayout->getTabs() as $tab) {
            foreach ($tab->getElements() as $element) {
                $existingElementsByUid[$element->uid] = $element;
            }
        }

        // Create new tabs
        $newTabs = [];
        foreach ($tabs as $tabData) {
            assert(is_array($tabData));
            assert(isset($tabData['name']) && is_string($tabData['name']));

            $elements = [];
            
            // Require elements array
            \throw_unless(isset($tabData['elements']) && is_array($tabData['elements']), "Tab '{$tabData['name']}' must have an 'elements' array");
            
            // Handle full element structure
            foreach ($tabData['elements'] as $elementConfig) {
                assert(is_array($elementConfig));
                
                $element = null;
                
                // Try to preserve existing element by UID
                if (isset($elementConfig['uid']) && is_string($elementConfig['uid'])) {
                    $uid = $elementConfig['uid'];
                    if (isset($existingElementsByUid[$uid])) {
                        $element = $existingElementsByUid[$uid];
                        
                        // Update properties that can be modified
                        if (isset($elementConfig['width']) && is_int($elementConfig['width'])) {
                            $element->width = $elementConfig['width'];
                        }
                        
                        // Update field-specific properties
                        if ($element instanceof BaseField) {
                            if (isset($elementConfig['required']) && is_bool($elementConfig['required'])) {
                                $element->required = $elementConfig['required'];
                            }
                            if (isset($elementConfig['label']) && is_string($elementConfig['label'])) {
                                $element->label = $elementConfig['label'];
                            }
                            if (isset($elementConfig['instructions']) && is_string($elementConfig['instructions'])) {
                                $element->instructions = $elementConfig['instructions'];
                            }
                            if (isset($elementConfig['tip']) && is_string($elementConfig['tip'])) {
                                $element->tip = $elementConfig['tip'];
                            }
                            if (isset($elementConfig['warning']) && is_string($elementConfig['warning'])) {
                                $element->warning = $elementConfig['warning'];
                            }
                        }
                    }
                }
                
                // If we couldn't find/reuse an existing element, create a new one
                if ($element === null) {
                    $elementType = $elementConfig['type'] ?? '';
                    
                    if ($elementType === CustomField::class && isset($elementConfig['fieldId'])) {
                        $field = $fieldsService->getFieldById((int)$elementConfig['fieldId']);
                        \throw_unless($field !== null, "Field with ID {$elementConfig['fieldId']} not found");
                        
                        $element = new CustomField($field);
                    } elseif (class_exists($elementType) && is_subclass_of($elementType, FieldLayoutElement::class)) {
                        /** @var FieldLayoutElement $element */
                        $element = new $elementType();
                    } else {
                        // Skip invalid element types
                        continue;
                    }
                    
                    // Set basic properties for new elements
                    if (isset($elementConfig['width']) && is_int($elementConfig['width'])) {
                        $element->width = $elementConfig['width'];
                    }
                    
                    // Set field-specific properties for new elements
                    if ($element instanceof BaseField) {
                        if (isset($elementConfig['required']) && is_bool($elementConfig['required'])) {
                            $element->required = $elementConfig['required'];
                        }
                        if (isset($elementConfig['label']) && is_string($elementConfig['label'])) {
                            $element->label = $elementConfig['label'];
                        }
                        if (isset($elementConfig['instructions']) && is_string($elementConfig['instructions'])) {
                            $element->instructions = $elementConfig['instructions'];
                        }
                        if (isset($elementConfig['tip']) && is_string($elementConfig['tip'])) {
                            $element->tip = $elementConfig['tip'];
                        }
                        if (isset($elementConfig['warning']) && is_string($elementConfig['warning'])) {
                            $element->warning = $elementConfig['warning'];
                        }
                    }
                    
                    // Set native field specific properties
                    if ($element instanceof BaseNativeField && isset($elementConfig['attribute'])) {
                        $element->attribute = $elementConfig['attribute'];
                    }
                }
                
                if ($element !== null) {
                    $elements[] = $element;
                }
            }

            $tab = new FieldLayoutTab([
                'layout' => $fieldLayout,
                'name' => $tabData['name'],
                'elements' => $elements,
            ]);

            $newTabs[] = $tab;
        }

        // Update the field layout with new tabs
        $fieldLayout->setTabs($newTabs);

        // Save the field layout
        throw_unless($fieldsService->saveLayout($fieldLayout), ModelSaveException::class, $fieldLayout);

        // Get updated field layout information using the same logic as GetFieldLayout
        $updatedFieldLayout = $fieldsService->getLayoutById($fieldLayoutId);
        \throw_unless($updatedFieldLayout instanceof FieldLayout, "Updated field layout not found");

        $fieldLayoutInfo = [
            'id' => $updatedFieldLayout->id,
            'type' => $updatedFieldLayout->type,
            'tabs' => [],
        ];

        foreach ($updatedFieldLayout->getTabs() as $tab) {
            $tabInfo = [
                'name' => $tab->name,
                'elements' => [], // Complete element info
            ];

            /** @var FieldLayoutElement $element */
            foreach ($tab->getElements() as $element) {
                $elementInfo = [
                    'uid' => $element->uid,
                    'type' => $element::class,
                    'width' => $element->width,
                ];

                // Add element-specific properties based on type
                if ($element instanceof CustomField) {
                    $field = $element->getField();
                    if ($field !== null) {
                        $elementInfo['fieldId'] = $field->id;
                        $elementInfo['fieldName'] = $field->name;
                        $elementInfo['fieldHandle'] = $field->handle;
                        $elementInfo['fieldType'] = $field::class;
                        $elementInfo['required'] = $element->required;
                        $elementInfo['label'] = $element->label;
                        $elementInfo['instructions'] = $element->instructions;
                        $elementInfo['tip'] = $element->tip;
                        $elementInfo['warning'] = $element->warning;
                    }
                } elseif ($element instanceof BaseNativeField) {
                    $elementInfo['attribute'] = $element->attribute;
                    $elementInfo['required'] = $element->required;
                    $elementInfo['label'] = $element->label;
                    $elementInfo['instructions'] = $element->instructions;
                    $elementInfo['tip'] = $element->tip;
                    $elementInfo['warning'] = $element->warning;
                    $elementInfo['mandatory'] = $element->mandatory;
                    $elementInfo['requirable'] = $element->requirable;
                    $elementInfo['translatable'] = $element->translatable;
                } elseif ($element instanceof BaseField) {
                    $elementInfo['required'] = $element->required;
                    $elementInfo['label'] = $element->label;
                    $elementInfo['instructions'] = $element->instructions;
                    $elementInfo['tip'] = $element->tip;
                    $elementInfo['warning'] = $element->warning;
                }

                $tabInfo['elements'][] = $elementInfo;
            }

            $fieldLayoutInfo['tabs'][] = $tabInfo;
        }

        return [
            '_notes' => [
                'Field layout updated successfully',
                'All field layout elements (custom fields, native fields, and UI elements) have been organized into the specified tabs',
                'The field layout can now be used by any model that references it',
            ],
            'fieldLayout' => $fieldLayoutInfo,
        ];
    }
}
