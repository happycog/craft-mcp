<?php

use happycog\craftmcp\tools\CreateSection;
use happycog\craftmcp\tools\CreateEntryType;
use happycog\craftmcp\tools\CreateEntry;
use happycog\craftmcp\tools\DeleteSection;
use craft\models\Section;

beforeEach(function () {
    // Use microtime to ensure unique handles across all tests
    $this->uniqueId = str_replace('.', '', microtime(true));
    
    // Helper to create a test entry type for use in sections
    $this->createTestEntryType = function (string $name = null): array {
        if ($name === null) {
            $name = 'Test Entry Type ' . $this->uniqueId . mt_rand(1000, 9999);
        }
        $tool = new CreateEntryType();
        return $tool->create($name);
    };

    // Helper to create a test section
    $this->createTestSection = function (string $name = 'Test Section', string $type = 'channel', array $entryTypeIds = null): array {
        $entryTypes = [];
        if ($entryTypeIds === null) {
            $entryType = ($this->createTestEntryType)();
            $entryTypeIds = [$entryType['entryTypeId']];
            $entryTypes = [$entryType];
        }
        
        $tool = new CreateSection();
        $sectionData = $tool->create($name, $type, $entryTypeIds);
        
        // Merge section data with entry types for convenience
        $sectionData['entryTypes'] = $entryTypes;
        return $sectionData;
    };

    // Helper to create a test entry
    $this->createTestEntry = function (int $sectionId, int $entryTypeId, string $title = 'Test Entry'): array {
        $tool = new CreateEntry();
        return $tool->create($sectionId, $entryTypeId, null, ['title' => $title]);
    };
});

test('delete section tool schema is valid', function () {
    $tool = new DeleteSection();
    $schema = $tool->getSchema();
    
    expect($schema)->toBeArray()
        ->and($schema['type'])->toBe('object')
        ->and($schema['properties'])->toHaveKey('sectionId')
        ->and($schema['properties'])->toHaveKey('force')
        ->and($schema['required'])->toContain('sectionId');
});

test('deletes empty section successfully', function () {
    // Create a section without any entries
    $section = ($this->createTestSection)('Delete Test Empty');
    
    $tool = new DeleteSection();
    $result = $tool->delete($section['sectionId']);
    
    expect($result)->toBeArray()
        ->and($result['id'])->toBe($section['sectionId'])
        ->and($result['name'])->toBe('Delete Test Empty')
        ->and($result['handle'])->toBe('deleteTestEmpty')
        ->and($result['impact']['hasContent'])->toBeFalse()
        ->and($result['impact']['entryCount'])->toBe(0)
        ->and($result['impact']['draftCount'])->toBe(0)
        ->and($result['impact']['revisionCount'])->toBe(0);
});

test('prevents deletion of section with entries without force', function () {
    // Create a section and add an entry
    $section = ($this->createTestSection)('Delete Test With Entries');
    $entryType = $section['entryTypes'][0] ?? null;
    expect($entryType)->not->toBeNull();
    
    $entry = ($this->createTestEntry)($section['sectionId'], $entryType['entryTypeId'], 'Test Entry');
    
    $tool = new DeleteSection();
    
    expect(fn() => $tool->delete($section['sectionId']))
        ->toThrow(RuntimeException::class, 'Section \'Delete Test With Entries\' contains data and cannot be deleted without force=true');
});

test('deletes section with entries when force is true', function () {
    // Create a section and add an entry
    $section = ($this->createTestSection)('Delete Test Force');
    $entryType = $section['entryTypes'][0] ?? null;
    expect($entryType)->not->toBeNull();
    
    $entry = ($this->createTestEntry)($section['sectionId'], $entryType['entryTypeId'], 'Test Entry for Force Delete');
    
    $tool = new DeleteSection();
    $result = $tool->delete($section['sectionId'], true);
    
    expect($result)->toBeArray()
        ->and($result['id'])->toBe($section['sectionId'])
        ->and($result['name'])->toBe('Delete Test Force')
        ->and($result['impact']['hasContent'])->toBeTrue()
        ->and($result['impact']['entryCount'])->toBeGreaterThan(0);
});

test('provides detailed impact assessment', function () {
    // Create a section and add multiple entries
    $section = ($this->createTestSection)('Delete Test Impact');
    $entryType = $section['entryTypes'][0] ?? null;
    expect($entryType)->not->toBeNull();
    
    // Add multiple entries
    ($this->createTestEntry)($section['sectionId'], $entryType['entryTypeId'], 'Entry 1');
    ($this->createTestEntry)($section['sectionId'], $entryType['entryTypeId'], 'Entry 2');
    
    $tool = new DeleteSection();
    
    expect(fn() => $tool->delete($section['sectionId']))
        ->toThrow(RuntimeException::class)
        ->and(function () use ($tool, $section) {
            try {
                $tool->delete($section['sectionId']);
            } catch (\RuntimeException $e) {
                $message = $e->getMessage();
                expect($message)->toContain('Impact Assessment:')
                    ->and($message)->toContain('Entries:')
                    ->and($message)->toContain('Drafts:')
                    ->and($message)->toContain('Revisions:')
                    ->and($message)->toContain('Entry Types:');
            }
        });
});

test('fails when section does not exist', function () {
    $tool = new DeleteSection();
    
    expect(fn() => $tool->delete(99999))
        ->toThrow(RuntimeException::class, 'Section with ID 99999 not found');
});

test('deletes single section type', function () {
    $section = ($this->createTestSection)('Single Section Delete', 'single');
    
    $tool = new DeleteSection();
    // Single sections may have auto-created entries, use force if needed
    $result = $tool->delete($section['sectionId'], true);
    
    expect($result)->toBeArray()
        ->and($result['type'])->toBe(Section::TYPE_SINGLE);
});

test('deletes channel section type', function () {
    $section = ($this->createTestSection)('Channel Section Delete', 'channel');
    
    $tool = new DeleteSection();
    $result = $tool->delete($section['sectionId']);
    
    expect($result)->toBeArray()
        ->and($result['type'])->toBe(Section::TYPE_CHANNEL);
});

test('deletes structure section type', function () {
    $section = ($this->createTestSection)('Structure Section Delete', 'structure');
    
    $tool = new DeleteSection();
    $result = $tool->delete($section['sectionId']);
    
    expect($result)->toBeArray()
        ->and($result['type'])->toBe(Section::TYPE_STRUCTURE);
});

test('analyzes impact correctly for empty section', function () {
    $section = ($this->createTestSection)('Empty Impact Test');
    
    $tool = new DeleteSection();
    $result = $tool->delete($section['sectionId']);
    
    expect($result['impact'])->toBeArray()
        ->and($result['impact']['hasContent'])->toBeFalse()
        ->and($result['impact']['entryCount'])->toBe(0)
        ->and($result['impact']['draftCount'])->toBe(0)
        ->and($result['impact']['revisionCount'])->toBe(0)
        ->and($result['impact']['entryTypeCount'])->toBeGreaterThan(0)
        ->and($result['impact']['entryTypes'])->toBeArray();
});

test('includes entry type information in impact', function () {
    $entryType = ($this->createTestEntryType)('Impact Entry Type');
    $section = ($this->createTestSection)('Impact Entry Type Test', 'channel', [$entryType['entryTypeId']]);
    
    $tool = new DeleteSection();
    $result = $tool->delete($section['sectionId']);
    
    expect($result['impact']['entryTypes'])->toBeArray()
        ->and($result['impact']['entryTypes'][0])->toHaveKeys(['id', 'name', 'handle'])
        ->and($result['impact']['entryTypes'][0]['name'])->toBe('Impact Entry Type');
});

test('handles section with multiple entry types', function () {
    // Create multiple entry types
    $entryType1 = ($this->createTestEntryType)('Multi Type 1');
    $entryType2 = ($this->createTestEntryType)('Multi Type 2');
    
    $section = ($this->createTestSection)('Multi Type Section', 'channel', [
        $entryType1['entryTypeId'],
        $entryType2['entryTypeId']
    ]);
    
    $tool = new DeleteSection();
    $result = $tool->delete($section['sectionId']);
    
    expect($result['impact']['entryTypeCount'])->toBe(2)
        ->and($result['impact']['entryTypes'])->toHaveCount(2);
});

test('force parameter validation', function () {
    $section = ($this->createTestSection)('Force Validation Test');
    
    $tool = new DeleteSection();
    
    // Test with valid force values
    expect($tool->delete($section['sectionId'], false))->toBeArray();
    
    // Create new section for second test
    $section2 = ($this->createTestSection)('Force Validation Test 2');
    expect($tool->delete($section2['sectionId'], true))->toBeArray();
});

test('error message includes section name and details', function () {
    $section = ($this->createTestSection)('Error Message Test');
    $entryType = $section['entryTypes'][0] ?? null;
    expect($entryType)->not->toBeNull();
    
    ($this->createTestEntry)($section['sectionId'], $entryType['entryTypeId'], 'Error Test Entry');
    
    $tool = new DeleteSection();
    
    expect(fn() => $tool->delete($section['sectionId']))
        ->toThrow(RuntimeException::class)
        ->and(function () use ($tool, $section) {
            try {
                $tool->delete($section['sectionId']);
            } catch (\RuntimeException $e) {
                $message = $e->getMessage();
                expect($message)->toContain('Error Message Test')
                    ->and($message)->toContain('force=true')
                    ->and($message)->toContain('This action cannot be undone');
            }
        });
});

test('successful deletion includes complete information', function () {
    $section = ($this->createTestSection)('Complete Info Test');
    
    $tool = new DeleteSection();
    $result = $tool->delete($section['sectionId']);
    
    expect($result)->toHaveKeys(['id', 'name', 'handle', 'type', 'impact'])
        ->and($result['id'])->toBeInt()
        ->and($result['name'])->toBeString()
        ->and($result['handle'])->toBeString()
        ->and($result['type'])->toBeString()
        ->and($result['impact'])->toBeArray();
});

test('handles sections created with different settings', function () {
    // Test with different section configurations
    $entryType = ($this->createTestEntryType)('Settings Test Entry Type');
    
    // Create section with custom settings
    $tool = new CreateSection();
    $section = $tool->create(
        name: 'Custom Settings Section',
        type: 'structure',
        entryTypeIds: [$entryType['entryTypeId']],
        handle: 'customSettingsSection',
        enableVersioning: false,
        maxLevels: 5
    );
    
    $deleteJob = new DeleteSection();
    $result = $deleteJob->delete($section['sectionId']);
    
    expect($result['name'])->toBe('Custom Settings Section')
        ->and($result['handle'])->toBe('customSettingsSection')
        ->and($result['type'])->toBe(Section::TYPE_STRUCTURE);
});

test('impact assessment counts are accurate', function () {
    $section = ($this->createTestSection)('Accurate Count Test');
    $entryType = $section['entryTypes'][0] ?? null;
    expect($entryType)->not->toBeNull();
    
    // Add known number of entries
    ($this->createTestEntry)($section['sectionId'], $entryType['entryTypeId'], 'Count Entry 1');
    ($this->createTestEntry)($section['sectionId'], $entryType['entryTypeId'], 'Count Entry 2');
    ($this->createTestEntry)($section['sectionId'], $entryType['entryTypeId'], 'Count Entry 3');
    
    $tool = new DeleteSection();
    
    expect(fn() => $tool->delete($section['sectionId']))
        ->toThrow(RuntimeException::class)
        ->and(function () use ($tool, $section) {
            try {
                $tool->delete($section['sectionId']);
            } catch (\RuntimeException $e) {
                $message = $e->getMessage();
                expect($message)->toContain('Entries: 3');
            }
        });
});