<?php

declare(strict_types=1);

namespace happycog\craftmcp\tools;

use Craft;
use craft\models\EntryType;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class GetEntryType
{
    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_entry_type',
        description: 'Get detailed information about a specific entry type by ID. Returns entry type properties, field layout information, and section details.'
    )]
    public function get(
        #[Schema(type: 'number', description: 'The ID of the entry type to retrieve')]
        int $entryTypeId
    ): array
    {
        $entriesService = Craft::$app->getEntries();
        $entryType = $entriesService->getEntryTypeById($entryTypeId);

        if (!$entryType instanceof EntryType) {
            return ['error' => "Entry type with ID {$entryTypeId} not found"];
        }

        // Get section information by finding which section contains this entry type
        $section = null;
        $sections = $entriesService->getAllSections();
        foreach ($sections as $sectionCandidate) {
            foreach ($sectionCandidate->getEntryTypes() as $sectionEntryType) {
                if ($sectionEntryType->id === $entryType->id) {
                    $section = $sectionCandidate;
                    break 2; // Break out of both loops
                }
            }
        }
        
        $sectionInfo = null;
        if ($section) {
            $sectionInfo = [
                'id' => $section->id,
                'name' => $section->name,
                'handle' => $section->handle,
                'type' => $section->type,
                'enableVersioning' => $section->enableVersioning,
            ];
        }

        // Get field layout information
        $fieldLayout = $entryType->getFieldLayout();
        $fieldLayoutInfo = [
            'id' => $fieldLayout->id,
            'type' => $fieldLayout->type,
        ];

        // Get field information
        $fields = [];
        foreach ($fieldLayout->getTabs() as $tab) {
            foreach ($tab->getElements() as $element) {
                if ($element instanceof \craft\fieldlayoutelements\CustomField) {
                    $field = $element->getField();
                    $fields[] = [
                        'id' => $field->id,
                        'name' => $field->name,
                        'handle' => $field->handle,
                        'type' => get_class($field),
                        'required' => $element->required,
                        'instructions' => $field->instructions,
                    ];
                }
            }
        }

        // Get usage statistics
        $entryCount = \craft\elements\Entry::find()
            ->typeId($entryTypeId)
            ->count();

        $draftCount = \craft\elements\Entry::find()
            ->typeId($entryTypeId)
            ->drafts()
            ->count();

        $revisionCount = \craft\elements\Entry::find()
            ->typeId($entryTypeId)
            ->revisions()
            ->count();

        // Build response
        return [
            '_notes' => [
                'Retrieved entry type information including field layout and usage statistics',
                'Field layout shows all custom fields associated with this entry type',
                'Usage statistics show current entries, drafts, and revisions using this type',
            ],
            'entryType' => [
                'id' => $entryType->id,
                'name' => $entryType->name,
                'handle' => $entryType->handle,
                'hasTitleField' => $entryType->hasTitleField,
                'titleTranslationMethod' => $entryType->titleTranslationMethod,
                'titleTranslationKeyFormat' => $entryType->titleTranslationKeyFormat,
                'titleFormat' => $entryType->titleFormat,
                'slugTranslationMethod' => $entryType->slugTranslationMethod,
                'slugTranslationKeyFormat' => $entryType->slugTranslationKeyFormat,
                'showSlugField' => $entryType->showSlugField,
                'showStatusField' => $entryType->showStatusField,
                'fieldLayoutId' => $entryType->fieldLayoutId,
                'uid' => $entryType->uid,
            ],
            'section' => $sectionInfo,
            'fieldLayout' => $fieldLayoutInfo,
            'fields' => $fields,
            'usage' => [
                'entries' => $entryCount,
                'drafts' => $draftCount,
                'revisions' => $revisionCount,
                'total' => (int)$entryCount + (int)$draftCount + (int)$revisionCount,
            ],
            'editUrl' => $section ? Craft::$app->getConfig()->getGeneral()->baseCpUrl . "/settings/sections/{$section->id}/entrytypes/{$entryType->id}" : null,
        ];
    }
}