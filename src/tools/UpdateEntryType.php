<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\enums\Color;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\helpers\UrlHelper;
use happycog\craftmcp\exceptions\ModelSaveException;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class UpdateEntryType
{
    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'update_entry_type',
        description: <<<'END'
        Update an existing entry type in Craft CMS. Allows modification of entry type properties
        including name, handle, icon, color, and title field settings while preserving field layout
        and existing content.

        Entry type updates will preserve field layouts and any existing entries unless structural
        changes affect compatibility. Handle changes require uniqueness validation.

        After updating the entry type always link the user back to the entry type settings in the Craft
        control panel so they can review the changes in the context of the Craft UI.
        END
    )]
    public function update(
        #[Schema(type: 'integer', description: 'The ID of the entry type to update')]
        int $entryTypeId,

        #[Schema(type: 'string', description: 'The display name for the entry type')]
        ?string $name = null,

        #[Schema(type: 'string', description: 'The entry type handle (machine-readable name)')]
        ?string $handle = null,

        #[Schema(type: 'string', description: 'How titles are translated: none, site, language, or custom')]
        ?string $titleTranslationMethod = null,

        #[Schema(type: 'string', description: 'Translation key format for custom title translation')]
        ?string $titleTranslationKeyFormat = null,

        #[Schema(type: 'string', description: 'Custom title format pattern (e.g., "{name} - {dateCreated|date}") for controlling entry title display')]
        ?string $titleFormat = null,

        #[Schema(type: 'string', description: 'Icon identifier for the entry type')]
        ?string $icon = null,

        #[Schema(type: 'string', description: 'Color identifier for the entry type')]
        ?string $color = null,

        #[Schema(type: 'string', description: 'A short string describing the purpose of the entry type (optional)')]
        ?string $description = null,
    ): array
    {
        $entriesService = Craft::$app->getEntries();

        // Get the existing entry type
        $entryType = $entriesService->getEntryTypeById($entryTypeId);
        if (!$entryType) {
            throw new \InvalidArgumentException("Entry type with ID {$entryTypeId} not found.");
        }

        // Store original values for comparison
        $originalValues = [
            'name' => $entryType->name,
            'handle' => $entryType->handle,
            'description' => $entryType->description,
            'hasTitleField' => $entryType->hasTitleField,
            'titleTranslationMethod' => $entryType->titleTranslationMethod,
            'titleTranslationKeyFormat' => $entryType->titleTranslationKeyFormat,
            'titleFormat' => $entryType->titleFormat,
            'icon' => $entryType->icon,
            'color' => $entryType->color?->value,
        ];

        // Update properties if provided
        if ($name !== null) {
            $entryType->name = $name;
        }

        if ($handle !== null) {
            $entryType->handle = $handle;
        }

        if ($description !== null) {
            $entryType->description = $description;
        }

        if ($titleTranslationMethod !== null) {
            $entryType->titleTranslationMethod = $this->getTranslationMethodConstant($titleTranslationMethod);
        }

        if ($titleTranslationKeyFormat !== null) {
            $entryType->titleTranslationKeyFormat = $titleTranslationKeyFormat;
        }

        if ($titleFormat !== null) {
            $entryType->titleFormat = $titleFormat;
        }

        if ($icon !== null) {
            $entryType->icon = $icon;
        }

        if ($color !== null) {
            $entryType->color = $this->getColorEnum($color);
        }

        // Save the updated entry type
        throw_unless($entriesService->saveEntryType($entryType), ModelSaveException::class, $entryType);

        // Generate control panel URL
        $entryTypeId = $entryType->id;
        if ($entryTypeId === null) {
            throw new \Exception("Entry type ID is null after save operation");
        }
        $editUrl = UrlHelper::cpUrl('settings/entry-types/' . $entryTypeId);

        // Refresh the entry type from database to get the actual saved values
        $savedEntryType = $entriesService->getEntryTypeById($entryTypeId);
        if (!$savedEntryType) {
            throw new \Exception("Failed to retrieve saved entry type with ID {$entryTypeId}");
        }

        // Determine what changed
        $changes = [];
        $newValues = [
            'name' => $savedEntryType->name,
            'handle' => $savedEntryType->handle,
            'hasTitleField' => $savedEntryType->hasTitleField,
            'titleTranslationMethod' => $savedEntryType->titleTranslationMethod,
            'titleTranslationKeyFormat' => $savedEntryType->titleTranslationKeyFormat,
            'titleFormat' => $savedEntryType->titleFormat,
            'icon' => $savedEntryType->icon,
            'color' => $savedEntryType->color?->value,
        ];

        foreach ($newValues as $key => $newValue) {
            if ($originalValues[$key] !== $newValue) {
                $changes[] = $key;
            }
        }

        $changesSummary = empty($changes)
            ? 'No changes were made to the entry type.'
            : 'Updated: ' . implode(', ', $changes);

        return [
            '_notes' => "The entry type was successfully updated. {$changesSummary} You can further configure it in the Craft control panel.",
            'entryTypeId' => $savedEntryType->id,
            'name' => $savedEntryType->name,
            'handle' => $savedEntryType->handle,
            'hasTitleField' => $savedEntryType->hasTitleField,
            'titleTranslationMethod' => $savedEntryType->titleTranslationMethod,
            'titleTranslationKeyFormat' => $savedEntryType->titleTranslationKeyFormat,
            'titleFormat' => $savedEntryType->titleFormat,
            'icon' => $savedEntryType->icon,
            'color' => $savedEntryType->color?->value,
            'fieldLayoutId' => $savedEntryType->fieldLayoutId,
            'editUrl' => $editUrl,
            'changes' => $changes,
        ];
    }

    /**
     * @return 'custom'|'language'|'none'|'site'
     */
    private function getTranslationMethodConstant(string $method): string
    {
        $methodMap = [
            'none' => \craft\base\Field::TRANSLATION_METHOD_NONE,
            'site' => \craft\base\Field::TRANSLATION_METHOD_SITE,
            'language' => \craft\base\Field::TRANSLATION_METHOD_LANGUAGE,
            'custom' => \craft\base\Field::TRANSLATION_METHOD_CUSTOM,
        ];

        if (!isset($methodMap[$method])) {
            throw new \InvalidArgumentException("Invalid translation method '{$method}'. Must be one of: " . implode(', ', array_keys($methodMap)));
        }

        return $methodMap[$method];
    }

    private function getColorEnum(string $color): Color
    {
        $colorMap = [
            'red' => Color::Red,
            'orange' => Color::Orange,
            'amber' => Color::Amber,
            'yellow' => Color::Yellow,
            'lime' => Color::Lime,
            'green' => Color::Green,
            'emerald' => Color::Emerald,
            'teal' => Color::Teal,
            'cyan' => Color::Cyan,
            'sky' => Color::Sky,
            'blue' => Color::Blue,
            'indigo' => Color::Indigo,
            'violet' => Color::Violet,
            'purple' => Color::Purple,
            'fuchsia' => Color::Fuchsia,
            'pink' => Color::Pink,
            'rose' => Color::Rose,
            'white' => Color::White,
            'gray' => Color::Gray,
            'black' => Color::Black,
        ];

        $lowerColor = strtolower($color);
        if (!isset($colorMap[$lowerColor])) {
            throw new \InvalidArgumentException("Invalid color '{$color}'. Must be one of: " . implode(', ', array_keys($colorMap)));
        }

        return $colorMap[$lowerColor];
    }
}
