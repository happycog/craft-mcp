<?php

declare(strict_types=1);

namespace happycog\craftmcp\tools;

use Craft;
use craft\models\EntryType;
use craft\models\Section;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;
use happycog\craftmcp\actions\FieldFormatter;

class GetEntryTypes
{
    public function __construct(
        protected FieldFormatter $fieldFormatter,
    ) {
    }


    /**
     * @param array<int>|null $entryTypeIds
     * @return array<int, array<string, mixed>>
     */
    #[McpTool(
        name: 'get_entry_types',
        description: 'Get a list of entry types with complete field information, usage stats, and edit URLs.'
    )]
    public function getAll(
        #[Schema(type: 'array', items: ['type' => 'number'], description: 'Optional list of entry type IDs to limit results')]
        ?array $entryTypeIds = null
    ): array
    {
        $entriesService = Craft::$app->getEntries();

        // Map entry type IDs to their sections (if any)
        $sectionByEntryTypeId = [];
        foreach ($entriesService->getAllSections() as $section) {
            foreach ($section->getEntryTypes() as $et) {
                $sectionByEntryTypeId[$et->id] = $section;
            }
        }

        $results = [];
        foreach ($entriesService->getAllEntryTypes() as $entryType) {
            if (is_array($entryTypeIds) && $entryTypeIds !== [] && !in_array($entryType->id, $entryTypeIds, true)) {
                continue;
            }
            /** @var Section|null $section */
            $section = $sectionByEntryTypeId[$entryType->id] ?? null;
            $results[] = $this->formatEntryType($entryType, $section);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatEntryType(EntryType $entryType, ?\craft\models\Section $section): array
    {
        // Get usage statistics
        $entryCount = \craft\elements\Entry::find()
            ->typeId($entryType->id)
            ->count();

        $draftCount = \craft\elements\Entry::find()
            ->typeId($entryType->id)
            ->drafts()
            ->count();

        // Fields via layout with context
        $layout = $entryType->getFieldLayout();
        $fields = $this->fieldFormatter->formatFieldsForLayout($layout);

        // Build entry type data
        $entryTypeData = [
            'id' => $entryType->id,
            'name' => $entryType->name,
            'handle' => $entryType->handle,
            'hasTitleField' => $entryType->hasTitleField,
            'titleTranslationMethod' => $entryType->titleTranslationMethod,
            'titleFormat' => $entryType->titleFormat,
            'fieldLayoutId' => $entryType->fieldLayoutId,
            'uid' => $entryType->uid,
            'fields' => $fields,
            'usage' => [
                'entries' => $entryCount,
                'drafts' => $draftCount,
                'total' => (int)$entryCount + (int)$draftCount,
            ],
        ];

        // Add section information if available
        if ($section) {
            $entryTypeData['section'] = [
                'id' => $section->id,
                'name' => $section->name,
                'handle' => $section->handle,
                'type' => $section->type,
            ];

            // Get control panel URL safely
            $generalConfig = Craft::$app->getConfig()->getGeneral();
            $cpUrl = $generalConfig->cpUrl ?? '';
            $entryTypeData['editUrl'] = $cpUrl ? $cpUrl . "/settings/sections/{$section->id}/entrytypes/{$entryType->id}" : null;
        } else {
            $entryTypeData['section'] = null;
            $entryTypeData['editUrl'] = null;
        }

        return $entryTypeData;
    }
}
