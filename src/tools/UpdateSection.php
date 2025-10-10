<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\enums\PropagationMethod;
use craft\helpers\UrlHelper;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use happycog\craftmcp\exceptions\ModelSaveException;
use PhpMcp\Schema\CallToolRequest;
use PhpMcp\Schema\CallToolResult;
use PhpMcp\Server\Attributes\McpTool;

class UpdateSection
{
    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'update_section',
        description: <<<'END'
        Update an existing section in Craft CMS. Allows modification of section properties
        including name, handle, site settings, and entry type associations while preserving
        existing entry data where possible.

        Section type changes have restrictions: Single ↔ Channel is possible, but Structure
        changes require careful consideration due to hierarchical data. Entry type associations
        can be updated to add or remove entry types from the section.

        After updating the section always link the user back to the section settings in the Craft
        control panel so they can review the changes in the context of the Craft UI.
        END
    )]
    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sectionId' => [
                    'type' => 'integer',
                    'description' => 'The ID of the section to update'
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'The display name for the section'
                ],
                'handle' => [
                    'type' => 'string',
                    'description' => 'The section handle (machine-readable name)'
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['single', 'channel', 'structure'],
                    'description' => 'Section type: single, channel, or structure. Type changes have restrictions based on existing data.'
                ],
                'entryTypeIds' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Array of entry type IDs to assign to this section. Replaces existing associations.'
                ],
                'enableVersioning' => [
                    'type' => 'boolean',
                    'description' => 'Whether to enable entry versioning for this section'
                ],
                'propagationMethod' => [
                    'type' => 'string',
                    'enum' => ['all', 'siteGroup', 'language', 'custom', 'none'],
                    'description' => 'How content propagates across sites'
                ],
                'maxLevels' => [
                    'type' => 'integer',
                    'description' => 'Maximum hierarchy levels (only for structure sections). Null/0 for unlimited.'
                ],
                'defaultPlacement' => [
                    'type' => 'string',
                    'enum' => ['beginning', 'end'],
                    'description' => 'Where new entries are placed by default (only for structure sections)'
                ],
                'siteSettings' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'siteId' => ['type' => 'integer', 'description' => 'Site ID'],
                            'enabledByDefault' => ['type' => 'boolean', 'description' => 'Enable entries by default'],
                            'hasUrls' => ['type' => 'boolean', 'description' => 'Whether entries have URLs'],
                            'uriFormat' => ['type' => 'string', 'description' => 'URI format pattern'],
                            'template' => ['type' => 'string', 'description' => 'Template path for rendering']
                        ],
                        'required' => ['siteId']
                    ],
                    'description' => 'Site-specific settings for multi-site installations'
                ]
            ],
            'required' => ['sectionId']
        ];
    }

    /**
     * @param array<string, mixed> $entryTypeIds
     * @param array<string, mixed>|null $siteSettingsData
     * @return array<string, mixed>
     */
    public function update(
        int $sectionId,
        ?string $name = null,
        ?string $handle = null,
        ?string $type = null,
        ?array $entryTypeIds = null,
        ?bool $enableVersioning = null,
        ?string $propagationMethod = null,
        ?int $maxLevels = null,
        ?string $defaultPlacement = null,
        ?array $siteSettingsData = null
    ): array {
        $sectionsService = Craft::$app->getEntries();

        // Get existing section
        $section = $sectionsService->getSectionById($sectionId);
        throw_unless($section, "Section with ID {$sectionId} not found");

        // Update basic properties only if provided
        if ($name !== null) {
            $section->name = $name;
        }

        if ($handle !== null) {
            $section->handle = $handle;
        }

        if ($type !== null) {
            $newType = match ($type) {
                'single' => Section::TYPE_SINGLE,
                'channel' => Section::TYPE_CHANNEL,
                'structure' => Section::TYPE_STRUCTURE,
                default => throw new \InvalidArgumentException("Invalid section type: {$type}")
            };

            // Check for type change restrictions
            $this->validateTypeChange($section, $newType);
            $section->type = $newType;
        }

        if ($enableVersioning !== null) {
            $section->enableVersioning = $enableVersioning;
        }

        if ($propagationMethod !== null) {
            $section->propagationMethod = match ($propagationMethod) {
                'all' => PropagationMethod::All,
                'siteGroup' => PropagationMethod::SiteGroup,
                'language' => PropagationMethod::Language,
                'custom' => PropagationMethod::Custom,
                'none' => PropagationMethod::None,
                default => throw new \InvalidArgumentException("Invalid propagation method: {$propagationMethod}")
            };
        }

        // Structure-specific settings
        if ($section->type === Section::TYPE_STRUCTURE) {
            if ($maxLevels !== null) {
                $section->maxLevels = $maxLevels ?: null;
            }

            if ($defaultPlacement !== null) {
                $section->defaultPlacement = match ($defaultPlacement) {
                    'beginning' => Section::DEFAULT_PLACEMENT_BEGINNING,
                    'end' => Section::DEFAULT_PLACEMENT_END,
                    default => throw new \InvalidArgumentException("Invalid default placement: {$defaultPlacement}")
                };
            }
        }

        // Update site settings if provided
        if ($siteSettingsData !== null) {
            $siteSettings = [];
            foreach ($siteSettingsData as $siteData) {
                assert(is_array($siteData), 'Site data must be an array');
                assert(is_int($siteData['siteId']), 'Site ID must be an integer');

                $siteId = $siteData['siteId'];

                // Validate site exists
                $site = Craft::$app->getSites()->getSiteById($siteId);
                throw_unless($site, "Site with ID {$siteId} not found");

                $settings = new Section_SiteSettings([
                    'sectionId' => $section->id,
                    'siteId' => $siteId,
                    'enabledByDefault' => $siteData['enabledByDefault'] ?? true,
                    'hasUrls' => $siteData['hasUrls'] ?? true,
                    'uriFormat' => $siteData['uriFormat'] ?? $this->generateDefaultUriFormat($section->type, $section->handle),
                    'template' => $siteData['template'] ?? null,
                ]);

                $siteSettings[$siteId] = $settings;
            }

            $section->setSiteSettings($siteSettings);
        }

        // Validate and save section
        if (!$sectionsService->saveSection($section)) {
            throw new ModelSaveException($section);
        }

        // Update entry type associations if provided
        if ($entryTypeIds !== null) {
            // Type-safe conversion from mixed array to array<int>
            assert(is_array($entryTypeIds), 'Entry type IDs must be an array');
            $validatedEntryTypeIds = [];
            foreach ($entryTypeIds as $id) {
                assert(is_int($id), 'Entry type ID must be an integer');
                $validatedEntryTypeIds[] = $id;
            }
            $this->updateEntryTypeAssociations($section, $validatedEntryTypeIds);
        }

        // Generate control panel URL
        $editUrl = UrlHelper::cpUrl('settings/sections/' . $section->id);

        return [
            'sectionId' => $section->id,
            'name' => $section->name,
            'handle' => $section->handle,
            'type' => $section->type,
            'propagationMethod' => $section->propagationMethod->value,
            'maxLevels' => $section->type === Section::TYPE_STRUCTURE ? ($section->maxLevels ?: null) : null,
            'editUrl' => $editUrl,
        ];
    }

    /**
     * @param array<int> $entryTypeIds
     */
    private function updateEntryTypeAssociations(Section $section, array $entryTypeIds): void
    {
        $sectionsService = Craft::$app->getEntries();

        // Validate all entry types exist and collect them
        $entryTypes = [];
        foreach ($entryTypeIds as $entryTypeId) {
            $entryType = $sectionsService->getEntryTypeById($entryTypeId);
            throw_unless($entryType, "Entry type with ID {$entryTypeId} not found");
            $entryTypes[] = $entryType;
        }

        // Set the entry types on the section (this replaces all existing associations)
        $section->setEntryTypes($entryTypes);

        // Save the section to persist the entry type associations
        if (!$sectionsService->saveSection($section)) {
            throw new ModelSaveException($section);
        }
    }

    private function validateTypeChange(Section $section, string $newType): void
    {
        if ($section->type === $newType) {
            return; // No change
        }

        // Check if section has entries
        $hasEntries = \craft\elements\Entry::find()
            ->sectionId($section->id)
            ->exists();

        if (!$hasEntries) {
            return; // No entries, any change is safe
        }

        // Define allowed type changes for sections with entries
        $allowedChanges = [
            Section::TYPE_SINGLE => [Section::TYPE_CHANNEL],
            Section::TYPE_CHANNEL => [Section::TYPE_SINGLE],
            // Structure changes require manual migration
        ];

        $currentType = $section->type;
        if (!isset($allowedChanges[$currentType]) || !in_array($newType, $allowedChanges[$currentType], true)) {
            throw new \RuntimeException(
                "Cannot change section type from {$currentType} to {$newType} when entries exist. " .
                "Structure sections require manual data migration."
            );
        }
    }

    private function generateDefaultUriFormat(string $sectionType, string $handle): string
    {
        return match ($sectionType) {
            Section::TYPE_SINGLE => $handle,
            Section::TYPE_CHANNEL => "{$handle}/{slug}",
            Section::TYPE_STRUCTURE => "{$handle}/{slug}",
            default => "{$handle}/{slug}"
        };
    }

    /** @phpstan-ignore-next-line */
    public function execute(CallToolRequest $request): CallToolResult
    {
        /** @phpstan-ignore-next-line */
        $args = $request->params->arguments;

        // Extract and validate arguments
        $sectionId = $args['sectionId'] ?? null;
        throw_unless(is_int($sectionId), 'sectionId is required and must be an integer');

        // Extract optional parameters
        $name = $args['name'] ?? null;
        $handle = $args['handle'] ?? null;
        $type = $args['type'] ?? null;
        $entryTypeIds = $args['entryTypeIds'] ?? null;
        $enableVersioning = $args['enableVersioning'] ?? null;
        $propagationMethod = $args['propagationMethod'] ?? null;
        $maxLevels = $args['maxLevels'] ?? null;
        $defaultPlacement = $args['defaultPlacement'] ?? null;
        $siteSettingsData = $args['siteSettings'] ?? null;

        try {
            $result = $this->update(
                sectionId: $sectionId,
                name: $name,
                handle: $handle,
                type: $type,
                entryTypeIds: $entryTypeIds,
                enableVersioning: $enableVersioning,
                propagationMethod: $propagationMethod,
                maxLevels: $maxLevels,
                defaultPlacement: $defaultPlacement,
                siteSettingsData: $siteSettingsData
            );

            /** @phpstan-ignore-next-line */
            return CallToolResult::make(
                content: [[
                    'type' => 'text',
                    'text' => "Section '" . $result['name'] . "' updated successfully!\n\n" .
                              "Section Details:\n" .
                              "- ID: " . $result['sectionId'] . "\n" .
                              "- Name: " . $result['name'] . "\n" .
                              "- Handle: " . $result['handle'] . "\n" .
                              "- Type: " . $result['type'] . "\n" .
                              "- Propagation Method: " . $result['propagationMethod'] . "\n" .
                              ($result['maxLevels'] ? "- Max Levels: " . $result['maxLevels'] . "\n" : ($result['type'] === Section::TYPE_STRUCTURE ? "- Max Levels: Unlimited\n" : '')) .
                              ($result['editUrl'] ? "\nEdit section in Craft control panel: " . $result['editUrl'] : '') .
                              "\n\nNote: Entry type associations and site settings have been updated as specified."
                ]]
            );
        } catch (\Exception $e) {
            /** @phpstan-ignore-next-line */
            return CallToolResult::make(
                content: [['type' => 'text', 'text' => $e->getMessage()]]
            );
        }
    }
}
