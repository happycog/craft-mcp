<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\helpers\UrlHelper;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use PhpMcp\Schema\CallToolRequest;
use PhpMcp\Schema\CallToolResult;
use PhpMcp\Server\Attributes\McpTool;



class CreateSection
{
    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'craft_create_section',
        description: <<<'END'
        Create a new section in Craft CMS. Sections define the structural organization of content with different types:
        - Single: One entry per section (e.g., homepage, about page)
        - Channel: Multiple entries with flexible structure (e.g., news, blog posts)
        - Structure: Hierarchical entries with parent-child relationships (e.g., pages with nested structure)

        Supports multi-site installations with site-specific settings. Entry types must be created separately using the 
        CreateEntryType tool and can be assigned to the section after creation.

        Returns the section details including control panel URL for further configuration.

        After creating the section always link the user back to the section settings in the Craft control panel 
        so they can review and further configure the section in the context of the Craft UI.
        END
    )]
    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'The display name for the section'
                ],
                'handle' => [
                    'type' => 'string',
                    'description' => 'The section handle (machine-readable name). Auto-generated from name if not provided.'
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['single', 'channel', 'structure'],
                    'description' => 'Section type: single (one entry), channel (multiple entries), or structure (hierarchical entries)'
                ],
                'entryTypeIds' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Array of entry type IDs to assign to this section. Use CreateEntryType tool to create entry types first.'
                ],
                'enableVersioning' => [
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Whether to enable entry versioning for this section'
                ],
                'propagationMethod' => [
                    'type' => 'string',
                    'enum' => ['all', 'siteGroup', 'language', 'custom', 'none'],
                    'default' => 'all',
                    'description' => 'How content propagates across sites: all, siteGroup, language, custom, or none'
                ],
                'maxLevels' => [
                    'type' => 'integer',
                    'description' => 'Maximum hierarchy levels (only for structure sections). Null/0 for unlimited.'
                ],
                'defaultPlacement' => [
                    'type' => 'string',
                    'enum' => ['beginning', 'end'],
                    'default' => 'end',
                    'description' => 'Where new entries are placed by default (only for structure sections)'
                ],
                'siteSettings' => [
                    'type' => 'array',
                    'description' => 'Site-specific settings. If not provided, section will be enabled for all sites with default settings.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'siteId' => [
                                'type' => 'integer',
                                'description' => 'Site ID'
                            ],
                            'enabledByDefault' => [
                                'type' => 'boolean',
                                'default' => true,
                                'description' => 'Whether entries are enabled by default for this site'
                            ],
                            'hasUrls' => [
                                'type' => 'boolean',
                                'default' => true,
                                'description' => 'Whether entries have URLs for this site'
                            ],
                            'uriFormat' => [
                                'type' => 'string',
                                'description' => 'URI format for entries (e.g., "news/{slug}", "pages/{slug}")'
                            ],
                            'template' => [
                                'type' => 'string',
                                'description' => 'Template path for rendering entries (e.g., "news/_entry", "pages/_entry")'
                            ]
                        ],
                        'required' => ['siteId']
                    ]
                ]
            ],
            'required' => ['name', 'type']
        ];
    }

    /**
     * @param array<int> $entryTypeIds
     * @param array<int, array<string, mixed>>|null $siteSettings
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        string $type,
        array $entryTypeIds,
        ?string $handle = null,
        bool $enableVersioning = true,
        string $propagationMethod = Section::PROPAGATION_METHOD_ALL,
        ?int $maxLevels = null,
        string $defaultPlacement = Section::DEFAULT_PLACEMENT_END,
        ?array $siteSettings = null
    ): array {
        throw_unless(in_array($type, [Section::TYPE_SINGLE, Section::TYPE_CHANNEL, Section::TYPE_STRUCTURE]), 
                    'Section type must be single, channel, or structure');

        throw_unless(!empty($entryTypeIds), 'At least one entry type ID is required');

        // Validate entry types exist
        $entriesService = Craft::$app->getEntries();
        foreach ($entryTypeIds as $entryTypeId) {
            $entryType = $entriesService->getEntryTypeById($entryTypeId);
            throw_unless($entryType, "Entry type with ID {$entryTypeId} not found");
        }

        // Auto-generate handle if not provided
        $handle ??= $this->generateHandle($name);

        // Create the section
        $section = new Section([
            'name' => $name,
            'handle' => $handle,
            'type' => $type,
            'enableVersioning' => $enableVersioning,
            'propagationMethod' => $propagationMethod,
        ]);

        // Set entry types
        $entryTypes = [];
        foreach ($entryTypeIds as $entryTypeId) {
            $entryType = $entriesService->getEntryTypeById($entryTypeId);
            throw_unless($entryType, "Entry type with ID {$entryTypeId} not found");
            $entryTypes[] = $entryType;
        }
        $section->setEntryTypes($entryTypes);

        // Set structure-specific properties
        if ($type === Section::TYPE_STRUCTURE) {
            if ($maxLevels !== null && $maxLevels > 0) {
                $section->maxLevels = $maxLevels;
            }
            
            // Validate and set default placement
            if (in_array($defaultPlacement, [Section::DEFAULT_PLACEMENT_BEGINNING, Section::DEFAULT_PLACEMENT_END])) {
                $section->defaultPlacement = $defaultPlacement;
            } else {
                $section->defaultPlacement = Section::DEFAULT_PLACEMENT_END;
            }
        }

        // Configure site settings
        $siteSettingsObjects = [];
        
        if ($siteSettings) {
            // Use provided site settings
            foreach ($siteSettings as $siteSettingData) {
                $siteId = $siteSettingData['siteId'];
                throw_unless(is_int($siteId), 'siteId must be an integer');
                
                $site = Craft::$app->getSites()->getSiteById($siteId);
                throw_unless($site, "Site with ID {$siteId} not found");

                $siteSettingsObjects[$siteId] = new Section_SiteSettings([
                    'siteId' => $siteId,
                    'enabledByDefault' => $siteSettingData['enabledByDefault'] ?? true,
                    'hasUrls' => $siteSettingData['hasUrls'] ?? true,
                    'uriFormat' => $siteSettingData['uriFormat'] ?? ($type === Section::TYPE_SINGLE ? $handle : "{$handle}/{slug}"),
                    'template' => $siteSettingData['template'] ?? null,
                ]);
            }
        } else {
            // Default: enable for all sites with basic settings
            foreach (Craft::$app->getSites()->getAllSites() as $site) {
                $defaultUriFormat = $type === Section::TYPE_SINGLE ? $handle : "{$handle}/{slug}";
                $siteSettingsObjects[$site->id] = new Section_SiteSettings([
                    'siteId' => $site->id,
                    'enabledByDefault' => true,
                    'hasUrls' => true,
                    'uriFormat' => $defaultUriFormat,
                    'template' => null,
                ]);
            }
        }

        $section->setSiteSettings($siteSettingsObjects);

        // Save the section
        $sectionsService = Craft::$app->getEntries();
        
        if (!$sectionsService->saveSection($section)) {
            $errors = $section->getErrors();
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    $errorMessages[] = "{$field}: {$error}";
                }
            }
            
            throw new \RuntimeException('Failed to create section: ' . implode(', ', $errorMessages));
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

    /** @phpstan-ignore-next-line */
    public function execute(CallToolRequest $request): CallToolResult
    {
        /** @phpstan-ignore-next-line */
        $args = $request->params->arguments;

        // Extract and validate arguments
        $name = $args['name'] ?? null;
        $entryTypeIds = $args['entryTypeIds'] ?? null;
        $handle = $args['handle'] ?? null;
        $type = $args['type'] ?? null;
        $enableVersioning = $args['enableVersioning'] ?? true;
        $propagationMethod = $args['propagationMethod'] ?? Section::PROPAGATION_METHOD_ALL;
        $maxLevels = $args['maxLevels'] ?? null;
        $defaultPlacement = $args['defaultPlacement'] ?? Section::DEFAULT_PLACEMENT_END;
        $siteSettingsData = $args['siteSettings'] ?? null;

        // Validate required parameters
        throw_unless(is_string($name) && !empty($name), 'name is required and must be a non-empty string');
        throw_unless(is_string($type) && !empty($type), 'type is required and must be a non-empty string');
        throw_unless(is_array($entryTypeIds) && !empty($entryTypeIds), 'entryTypeIds is required and must be a non-empty array');

        try {
            $result = $this->create(
                name: $name,
                type: $type,
                entryTypeIds: $entryTypeIds,
                handle: $handle,
                enableVersioning: $enableVersioning,
                propagationMethod: $propagationMethod,
                maxLevels: $maxLevels,
                defaultPlacement: $defaultPlacement,
                siteSettings: $siteSettingsData
            );

            /** @phpstan-ignore-next-line */
            return CallToolResult::make(
                content: [[
                    'type' => 'text',
                     'text' => "Section '" . $result['name'] . "' created successfully!\n\n" .
                               "Section Details:\n" .
                               "- ID: " . $result['sectionId'] . "\n" .
                               "- Name: " . $result['name'] . "\n" .
                               "- Handle: " . $result['handle'] . "\n" .
                               "- Type: " . $result['type'] . "\n" .
                               "- Propagation Method: " . $result['propagationMethod'] . "\n" .
                               ($result['maxLevels'] ? "- Max Levels: " . $result['maxLevels'] . "\n" : ($result['type'] === Section::TYPE_STRUCTURE ? "- Max Levels: Unlimited\n" : '')) .
                               ($result['editUrl'] ? "\nEdit section in Craft control panel: " . $result['editUrl'] : '') .
                              "\n\nNote: You can now assign entry types to this section or create new entry types specifically for this section."
                ]]
            );
        } catch (\Exception $e) {
            /** @phpstan-ignore-next-line */
            return CallToolResult::make(
                content: [['type' => 'text', 'text' => $e->getMessage()]]
            );
        }
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
            $handle = 'section' . ucfirst($handle);
        }
        
        return $handle;
    }
}