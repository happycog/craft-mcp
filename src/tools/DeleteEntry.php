<?php

namespace happycog\craftmcp\tools;

use Craft;
use craft\elements\Entry;
use craft\helpers\ElementHelper;
use craft\helpers\UrlHelper;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class DeleteEntry
{
    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'delete_entry',
        description: <<<'END'
        Delete an entry in Craft.

        - By default, this performs a soft delete (Craft's standard behavior) where the entry is marked as 
        deleted but remains in the database and can be restored.
        - Set permanentlyDelete to true to permanently remove the entry from the database.
        - Permanently deleted entries cannot be restored.
        
        Returns the deleted entry's basic information for confirmation.
        END
    )]
    public function delete(
        #[Schema(type: 'number')]
        int $entryId,
        
        #[Schema(type: 'boolean', description: 'Set to true to permanently delete the entry. Default is false (soft delete).')]
        bool $permanentlyDelete = false,
    ): array
    {
        $entry = Craft::$app->getElements()->getElementById($entryId, Entry::class);
        
        throw_unless($entry, \InvalidArgumentException::class, "Entry with ID {$entryId} not found");

        $section = $entry->getSection();
        $entryInfo = [
            '_notes' => 'The entry was successfully deleted.',
            'entryId' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'sectionId' => $entry->sectionId,
            'sectionName' => $section?->name,
            'postDate' => $entry->postDate?->format('c'),
            'deletedPermanently' => $permanentlyDelete,
        ];

        if (!$permanentlyDelete && $section !== null) {
            $entryInfo['restoreUrl'] = UrlHelper::cpUrl('entries/'.$section->handle, ['source' => 'section:'.$section->uid, 'status' => 'trashed']);
        }

        $elementsService = Craft::$app->getElements();
        
        $success = $elementsService->deleteElement($entry, $permanentlyDelete);

        if (!$success) {
            throw new \RuntimeException("Failed to delete entry with ID {$entryId}.");
        }

        return $entryInfo;
    }
}
