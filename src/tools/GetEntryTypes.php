<?php

declare(strict_types=1);

namespace happycog\craftmcp\tools;

use Craft;
use craft\models\EntryType;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class GetEntryTypes
{

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'craft_get_entry_types',
        description: 'Get a list of all entry types in Craft CMS. This is helpful for understanding the content structure and discovering available entry type IDs for creating entries.'
    )]
    public function getAll(
        #[Schema(type: 'number', description: 'Optional section ID to filter entry types by section')]
        ?int $sectionId = null,
        #[Schema(type: 'boolean', description: 'Whether to include standalone entry types (not associated with sections). Default: true')]
        bool $includeStandalone = true
    ): array
    {
        $entriesService = Craft::$app->getEntries();
        $allEntryTypes = [];
        $sectionEntryTypes = [];
        $standaloneEntryTypes = [];

        // Get all sections and their entry types
        $sections = $entriesService->getAllSections();
        foreach ($sections as $section) {
            // If filtering by section, skip other sections
            if ($sectionId !== null && $section->id !== $sectionId) {
                continue;
            }

            foreach ($section->getEntryTypes() as $entryType) {
                $entryTypeData = $this->formatEntryType($entryType, $section);
                $sectionEntryTypes[] = $entryTypeData;
                $allEntryTypes[$entryType->id] = true; // Track all section-associated entry types
            }
        }

        // If including standalone entry types, find those not associated with any section
        if ($includeStandalone && $sectionId === null) {
            $allEntryTypesFromService = $entriesService->getAllEntryTypes();
            foreach ($allEntryTypesFromService as $entryType) {
                // Only include if not already found in sections
                if (!isset($allEntryTypes[$entryType->id])) {
                    $entryTypeData = $this->formatEntryType($entryType, null);
                    $standaloneEntryTypes[] = $entryTypeData;
                }
            }
        }

        // Build response
        $response = [
            '_notes' => [
                'Entry types define the content structure and field layouts for entries',
                'Section-associated entry types are used for regular content creation',
                'Standalone entry types are often used for Matrix fields or other specialized content',
                'Use the entryTypeId when creating new entries with CreateEntry or CreateDraft'
            ],
            'sectionEntryTypes' => $sectionEntryTypes,
        ];

        if ($includeStandalone && $sectionId === null) {
            $response['standaloneEntryTypes'] = $standaloneEntryTypes;
        }

        $response['summary'] = [
            'sectionEntryTypes' => count($sectionEntryTypes),
            'standaloneEntryTypes' => count($standaloneEntryTypes),
            'total' => count($sectionEntryTypes) + count($standaloneEntryTypes),
            'filteredBySection' => $sectionId !== null ? $sectionId : null,
        ];

        return $response;
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
