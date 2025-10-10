<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\enums\Color;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\helpers\UrlHelper;
use craft\models\EntryType;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class CreateEntryType
{
    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'create_entry_type',
        description: <<<'END'
        Create a new entry type in Craft CMS. Entry types define the content schema and can exist
        independently of sections (useful for Matrix fields) or be assigned to sections later.
        
        Entry types control the structure of content with field layouts and determine whether entries
        have title fields, icon representation, and other content behaviors.
        
        After creating the entry type always link the user back to the entry type settings in the Craft 
        control panel so they can review and further configure the entry type in the context of the Craft UI.
        END
    )]
    public function create(
        #[Schema(type: 'string', description: 'The display name for the entry type')]
        string $name,
        
        #[Schema(type: 'string', description: 'The entry type handle (machine-readable name). Auto-generated from name if not provided.')]
        ?string $handle = null,
        
        #[Schema(type: 'boolean', description: 'Whether entries of this type have title fields')]
        bool $hasTitleField = true,
        
        #[Schema(type: 'string', description: 'How titles are translated: none, site, language, or custom')]
        string $titleTranslationMethod = 'site',
        
        #[Schema(type: 'string', description: 'Translation key format for custom title translation')]
        ?string $titleTranslationKeyFormat = null,
        
        #[Schema(type: 'string', description: 'Icon identifier for the entry type (optional)')]
        ?string $icon = null,
        
        #[Schema(type: 'string', description: 'Color identifier for the entry type (optional)')]
        ?string $color = null
    ): array
    {
        $entriesService = Craft::$app->getEntries();
        
        // Generate handle if not provided
        if (!$handle) {
            $handle = $this->generateHandle($name);
        }
        
        // Validate handle is unique across entry types
        $existingEntryType = $entriesService->getEntryTypeByHandle($handle);
        if ($existingEntryType) {
            throw new \InvalidArgumentException("An entry type with handle '{$handle}' already exists.");
        }
        
        // Map translation method
        $titleTranslationMethodConstant = $this->getTranslationMethodConstant($titleTranslationMethod);
        
        // Map color string to enum if provided
        $colorEnum = null;
        if ($color) {
            $colorEnum = $this->getColorEnum($color);
        }
        
        // Create entry type configuration
        $entryType = new EntryType();
        $entryType->name = $name;
        $entryType->handle = $handle;
        $entryType->hasTitleField = $hasTitleField;
        $entryType->titleTranslationMethod = $titleTranslationMethodConstant;
        $entryType->titleTranslationKeyFormat = $titleTranslationKeyFormat;
        $entryType->icon = $icon;
        $entryType->color = $colorEnum;
        
        // If hasTitleField is true, ensure the field layout includes the title field
        if ($hasTitleField) {
            $fieldLayout = $entryType->getFieldLayout();
            if (!$fieldLayout->isFieldIncluded('title')) {
                $fieldLayout->prependElements([new EntryTitleField()]);
            }
        }
        
        // Save the entry type
        if (!$entriesService->saveEntryType($entryType)) {
            $errors = $entryType->getErrors();
            $errorMessages = [];
            foreach ($errors as $attribute => $attributeErrors) {
                foreach ($attributeErrors as $error) {
                    $errorMessages[] = "{$attribute}: {$error}";
                }
            }
            throw new \Exception("Failed to save entry type: " . implode(', ', $errorMessages));
        }
        
        // Generate control panel URL
        $editUrl = UrlHelper::cpUrl('settings/entry-types/' . $entryType->id);
        
        // Refresh the entry type from database to get the actual saved values
        $savedEntryType = $entriesService->getEntryTypeById($entryType->id);
        
        return [
            '_notes' => 'The entry type was successfully created. You can further configure it in the Craft control panel.',
            'entryTypeId' => $savedEntryType->id,
            'name' => $savedEntryType->name,
            'handle' => $savedEntryType->handle,
            'hasTitleField' => $savedEntryType->hasTitleField,
            'titleTranslationMethod' => $savedEntryType->titleTranslationMethod,
            'titleTranslationKeyFormat' => $savedEntryType->titleTranslationKeyFormat,
            'icon' => $savedEntryType->icon,
            'color' => $savedEntryType->color?->value,
            'fieldLayoutId' => $savedEntryType->fieldLayoutId,
            'editUrl' => $editUrl,
        ];
    }
    
    private function generateHandle(string $name): string
    {
        // Convert to camelCase handle
        $handle = preg_replace('/[^a-zA-Z0-9]/', ' ', $name);
        $handle = ucwords(strtolower($handle ?? ''));
        $handle = str_replace(' ', '', $handle);
        $handle = lcfirst($handle);
        
        // Ensure it doesn't start with a number
        if (preg_match('/^[0-9]/', $handle)) {
            $handle = 'entryType' . ucfirst($handle);
        }
        
        return $handle;
    }
    
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