<?php

declare(strict_types=1);

use happycog\craftmcp\tools\GetEntryTypes;

test('GetEntryTypes has correct schema', function () {
    $tool = new GetEntryTypes();
    $schema = $tool->getSchema();
    
    expect($schema->name)->toBe('craft_get_entry_types');
    expect($schema->description)->toContain('Get a list of all entry types');
    expect($schema->inputSchema['type'])->toBe('object');
    expect($schema->inputSchema['required'])->toBeEmpty(); // No required parameters
    expect($schema->inputSchema['properties'])->toHaveKey('sectionId');
    expect($schema->inputSchema['properties'])->toHaveKey('includeStandalone');
});

test('GetEntryTypes getAll returns structured data', function () {
    $tool = new GetEntryTypes();
    $result = $tool->getAll();
    
    expect($result)->toBeArray();
    expect($result)->toHaveKey('_notes');
    expect($result)->toHaveKey('sectionEntryTypes');
    expect($result)->toHaveKey('standaloneEntryTypes');
    expect($result)->toHaveKey('summary');
    
    // Check summary structure
    expect($result['summary'])->toHaveKey('sectionEntryTypes');
    expect($result['summary'])->toHaveKey('standaloneEntryTypes');
    expect($result['summary'])->toHaveKey('total');
    expect($result['summary'])->toHaveKey('filteredBySection');
});

test('GetEntryTypes getAll respects sectionId filter', function () {
    $tool = new GetEntryTypes();
    
    // Get all entry types first to find a valid section ID
    $allResult = $tool->getAll();
    
    if (!empty($allResult['sectionEntryTypes'])) {
        $firstEntryType = $allResult['sectionEntryTypes'][0];
        $sectionId = $firstEntryType['section']['id'];
        
        // Filter by that section
        $filteredResult = $tool->getAll($sectionId);
        
        expect($filteredResult['summary']['filteredBySection'])->toBe($sectionId);
        expect($filteredResult)->not->toHaveKey('standaloneEntryTypes'); // Should not include standalone when filtering by section
        
        // All returned entry types should belong to the specified section
        foreach ($filteredResult['sectionEntryTypes'] as $entryType) {
            expect($entryType['section']['id'])->toBe($sectionId);
        }
    }
});

test('GetEntryTypes getAll respects includeStandalone parameter', function () {
    $tool = new GetEntryTypes();
    
    // Test excluding standalone entry types
    $resultWithoutStandalone = $tool->getAll(null, false);
    expect($resultWithoutStandalone)->not->toHaveKey('standaloneEntryTypes');
    
    // Test including standalone entry types (default behavior)
    $resultWithStandalone = $tool->getAll(null, true);
    expect($resultWithStandalone)->toHaveKey('standaloneEntryTypes');
});

test('GetEntryTypes entry type format includes required fields', function () {
    $tool = new GetEntryTypes();
    $result = $tool->getAll();
    
    if (!empty($result['sectionEntryTypes'])) {
        $entryType = $result['sectionEntryTypes'][0];
        
        // Check required entry type fields
        expect($entryType)->toHaveKey('id');
        expect($entryType)->toHaveKey('name');
        expect($entryType)->toHaveKey('handle');
        expect($entryType)->toHaveKey('hasTitleField');
        expect($entryType)->toHaveKey('fieldLayoutId');
        expect($entryType)->toHaveKey('uid');
        expect($entryType)->toHaveKey('usage');
        expect($entryType)->toHaveKey('section');
        expect($entryType)->toHaveKey('editUrl');
        
        // Check usage statistics structure
        expect($entryType['usage'])->toHaveKey('entries');
        expect($entryType['usage'])->toHaveKey('drafts');
        expect($entryType['usage'])->toHaveKey('total');
        
        // Check section structure (if present)
        if ($entryType['section'] !== null) {
            expect($entryType['section'])->toHaveKey('id');
            expect($entryType['section'])->toHaveKey('name');
            expect($entryType['section'])->toHaveKey('handle');
            expect($entryType['section'])->toHaveKey('type');
        }
    }
});

test('GetEntryTypes handles standalone entry types correctly', function () {
    $tool = new GetEntryTypes();
    $result = $tool->getAll();
    
    if (!empty($result['standaloneEntryTypes'])) {
        $standaloneEntryType = $result['standaloneEntryTypes'][0];
        
        // Standalone entry types should have null section
        expect($standaloneEntryType['section'])->toBeNull();
        expect($standaloneEntryType['editUrl'])->toBeNull();
        
        // But should still have all other required fields
        expect($standaloneEntryType)->toHaveKey('id');
        expect($standaloneEntryType)->toHaveKey('name');
        expect($standaloneEntryType)->toHaveKey('handle');
        expect($standaloneEntryType)->toHaveKey('usage');
    }
});

test('GetEntryTypes summary counts are accurate', function () {
    $tool = new GetEntryTypes();
    $result = $tool->getAll();
    
    $expectedTotal = count($result['sectionEntryTypes']) + count($result['standaloneEntryTypes']);
    
    expect($result['summary']['sectionEntryTypes'])->toBe(count($result['sectionEntryTypes']));
    expect($result['summary']['standaloneEntryTypes'])->toBe(count($result['standaloneEntryTypes']));
    expect($result['summary']['total'])->toBe($expectedTotal);
});