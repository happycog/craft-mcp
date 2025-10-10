<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\elements\Entry;
use PhpMcp\Schema\CallToolRequest;
use PhpMcp\Schema\CallToolResult;
use PhpMcp\Server\Attributes\McpTool;

class DeleteSection
{
    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'craft_delete_section',
        description: <<<'END'
        Delete a section from Craft CMS. This will remove the section and potentially affect related data.

        **WARNING**: Deleting a section that has existing entries will cause data loss. The tool will
        provide usage statistics and require confirmation for sections with existing content.

        Use the force parameter to delete sections that have existing entries. This action cannot be undone.
        END
    )]
    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sectionId' => [
                    'type' => 'integer',
                    'description' => 'The ID of the section to delete'
                ],
                'force' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Force deletion even if entries exist (default: false)'
                ]
            ],
            'required' => ['sectionId']
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(int $sectionId, bool $force = false): array
    {
        $sectionsService = Craft::$app->getEntries();
        
        // Get the section
        $section = $sectionsService->getSectionById($sectionId);
        throw_unless($section, "Section with ID {$sectionId} not found");

        // Analyze impact before deletion
        $impact = $this->analyzeImpact($section);

        // Check if force is required
        if ($impact['hasContent'] && !$force) {
            // Type-safe access to impact data for string interpolation
            assert(is_int($impact['entryCount']) || is_string($impact['entryCount']));
            assert(is_int($impact['draftCount']) || is_string($impact['draftCount']));
            assert(is_int($impact['revisionCount']) || is_string($impact['revisionCount']));
            assert(is_int($impact['entryTypeCount']) || is_string($impact['entryTypeCount']));
            
            $entryCount = (string)$impact['entryCount'];
            $draftCount = (string)$impact['draftCount'];
            $revisionCount = (string)$impact['revisionCount'];
            $entryTypeCount = (string)$impact['entryTypeCount'];
            
            throw new \RuntimeException(
                "Section '{$section->name}' contains data and cannot be deleted without force=true.\n\n" .
                "Impact Assessment:\n" .
                "- Entries: {$entryCount}\n" .
                "- Drafts: {$draftCount}\n" .
                "- Revisions: {$revisionCount}\n" .
                "- Entry Types: {$entryTypeCount}\n\n" .
                "Set force=true to proceed with deletion. This action cannot be undone."
            );
        }

        // Store section info for response
        $sectionInfo = [
            'id' => $section->id,
            'name' => $section->name,
            'handle' => $section->handle,
            'type' => $section->type,
            'impact' => $impact
        ];

        // Delete the section
        if (!$sectionsService->deleteSection($section)) {
            throw new \RuntimeException("Failed to delete section '{$section->name}'");
        }

        return $sectionInfo;
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeImpact(\craft\models\Section $section): array
    {
        // Count entries
        $entryCount = Entry::find()
            ->sectionId($section->id)
            ->count();

        // Count drafts
        $draftCount = Entry::find()
            ->sectionId($section->id)
            ->drafts()
            ->count();

        // Count revisions
        $revisionCount = Entry::find()
            ->sectionId($section->id)
            ->revisions()
            ->count();

        // Count entry types
        $entryTypes = $section->getEntryTypes();
        $entryTypeCount = count($entryTypes);

        // Check if there's any content
        $hasContent = $entryCount > 0 || $draftCount > 0 || $revisionCount > 0;

        return [
            'hasContent' => $hasContent,
            'entryCount' => $entryCount,
            'draftCount' => $draftCount,
            'revisionCount' => $revisionCount,
            'entryTypeCount' => $entryTypeCount,
            'entryTypes' => array_map(function($et) {
                return [
                    'id' => $et->id,
                    'name' => $et->name,
                    'handle' => $et->handle
                ];
            }, $entryTypes)
        ];
    }

    /** @phpstan-ignore-next-line */
    public function execute(CallToolRequest $request): CallToolResult
    {
        /** @phpstan-ignore-next-line */
        $args = $request->params->arguments;

        // Extract and validate arguments
        $sectionId = $args['sectionId'] ?? null;
        $force = $args['force'] ?? false;

        throw_unless(is_int($sectionId), 'sectionId is required and must be an integer');
        throw_unless(is_bool($force), 'force must be a boolean');

        try {
            $result = $this->delete($sectionId, $force);

            // Type-safe access to impact data
            $impact = $result['impact'];
            assert(is_array($impact), 'Impact must be an array');
            
            $impactText = '';
            if ($impact['hasContent']) {
                $entryCount = (string)$impact['entryCount'];
                $draftCount = (string)$impact['draftCount'];
                $revisionCount = (string)$impact['revisionCount'];
                
                $impactText = "\n\nDeleted Content:\n" .
                             "- Entries: {$entryCount}\n" .
                             "- Drafts: {$draftCount}\n" .
                             "- Revisions: {$revisionCount}\n";
            }

            $entryTypesText = '';
            if ($impact['entryTypeCount'] > 0) {
                $entryTypesText = "\n\nAffected Entry Types:\n";
                $entryTypes = $impact['entryTypes'];
                assert(is_array($entryTypes), 'Entry types must be an array');
                
                foreach ($entryTypes as $entryType) {
                    assert(is_array($entryType), 'Entry type must be an array');
                    assert(is_int($entryType['name']) || is_string($entryType['name']));
                    assert(is_int($entryType['id']) || is_string($entryType['id']));
                    
                    $name = (string)$entryType['name'];
                    $id = (string)$entryType['id'];
                    $entryTypesText .= "- {$name} (ID: {$id})\n";
                }
                $entryTypesText .= "\nNote: Entry types are now unassigned from any section and can be reassigned or deleted separately.";
            }

            assert(is_int($result['name']) || is_string($result['name']));
            assert(is_int($result['id']) || is_string($result['id']));
            
            $resultName = (string)$result['name'];
            $resultId = (string)$result['id'];

            /** @phpstan-ignore-next-line */
            return CallToolResult::make(
                content: [[
                    'type' => 'text',
                    'text' => "Section '{$resultName}' (ID: {$resultId}) deleted successfully!" .
                              $impactText .
                              $entryTypesText .
                              "\n\n⚠️  This action cannot be undone."
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