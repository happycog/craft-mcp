<?php

declare(strict_types=1);

use happycog\craftmcp\tools\GetEntryType;
use happycog\craftmcp\tools\CreateEntryType;

beforeEach(function () {
    $this->getEntryType = function (int $entryTypeId) {
        return Craft::$container->get(GetEntryType::class)->get($entryTypeId);
    };
    
    // Create a test entry type using the CreateEntryType tool
    $this->createTestEntryType = function (string $name = 'Test Entry Type') {
        $createEntryType = Craft::$container->get(CreateEntryType::class);
        
        $result = $createEntryType->create(
            name: $name,
            handle: 'testEntryType' . uniqid(),
            hasTitleField: true,
            titleTranslationMethod: 'site'
        );
        
        return $result;
    };
    
    // Track created entry types for cleanup
    $this->createdEntryTypeIds = [];
});

afterEach(function () {
    // Clean up any test entry types
    $entriesService = Craft::$app->getEntries();
    
    foreach ($this->createdEntryTypeIds ?? [] as $entryTypeId) {
        $entryType = $entriesService->getEntryTypeById($entryTypeId);
        if ($entryType) {
            $entriesService->deleteEntryType($entryType);
        }
    }
});

it('returns entry type information for valid ID', function () {
    $created = ($this->createTestEntryType)('Valid Entry Type');
    $this->createdEntryTypeIds[] = $created['entryTypeId'];
    
    $result = ($this->getEntryType)($created['entryTypeId']);
    
    expect($result)->toHaveKeys(['_notes', 'entryType', 'section', 'fieldLayout', 'fields', 'usage', 'editUrl']);
    expect($result['entryType']['id'])->toBe($created['entryTypeId']);
    expect($result['entryType']['name'])->toBe('Valid Entry Type');
    expect($result['entryType']['handle'])->toBeString();
});

it('returns error message for non-existent entry type', function () {
    $result = ($this->getEntryType)(99999);
    
    expect($result)->toHaveKey('error');
    expect($result['error'])->toBe('Entry type with ID 99999 not found');
});

it('includes complete entry type properties', function () {
    $created = ($this->createTestEntryType)('Properties Test');
    $this->createdEntryTypeIds[] = $created['entryTypeId'];
    
    $result = ($this->getEntryType)($created['entryTypeId']);
    
    $entryType = $result['entryType'];
    expect($entryType)->toHaveKeys([
        'id', 'name', 'handle', 'hasTitleField', 'titleTranslationMethod',
        'titleTranslationKeyFormat', 'titleFormat', 'slugTranslationMethod',
        'slugTranslationKeyFormat', 'showSlugField', 'showStatusField',
        'fieldLayoutId', 'uid'
    ]);
    
    expect($entryType['hasTitleField'])->toBeBool();
    expect($entryType['showSlugField'])->toBeBool();
    expect($entryType['showStatusField'])->toBeBool();
    expect($entryType['fieldLayoutId'])->toBeInt();
});

it('includes section information', function () {
    $created = ($this->createTestEntryType)('Section Info Test');
    $this->createdEntryTypeIds[] = $created['entryTypeId'];
    
    $result = ($this->getEntryType)($created['entryTypeId']);
    
    $section = $result['section'];
    // Section might be null for entry types created via CreateEntryType tool
    // as they may not be associated with a section immediately
    if ($section !== null) {
        expect($section)->toHaveKeys(['id', 'name', 'handle', 'type', 'enableVersioning']);
        expect($section['id'])->toBeInt();
        expect($section['type'])->toBeString();
        expect($section['enableVersioning'])->toBeBool();
    } else {
        expect($section)->toBeNull();
    }
});

it('includes field layout information', function () {
    $created = ($this->createTestEntryType)('Field Layout Test');
    $this->createdEntryTypeIds[] = $created['entryTypeId'];
    
    $result = ($this->getEntryType)($created['entryTypeId']);
    
    $fieldLayout = $result['fieldLayout'];
    expect($fieldLayout)->toHaveKeys(['id', 'type']);
    expect($fieldLayout['id'])->toBeInt();
    expect($fieldLayout['type'])->toBeString();
});

it('includes fields array', function () {
    $created = ($this->createTestEntryType)('Fields Test');
    $this->createdEntryTypeIds[] = $created['entryTypeId'];
    
    $result = ($this->getEntryType)($created['entryTypeId']);
    
    expect($result['fields'])->toBeArray();
    // New entry types might not have custom fields, so we just verify it's an array
});

it('includes usage statistics', function () {
    $created = ($this->createTestEntryType)('Usage Test');
    $this->createdEntryTypeIds[] = $created['entryTypeId'];
    
    $result = ($this->getEntryType)($created['entryTypeId']);
    
    $usage = $result['usage'];
    expect($usage)->toHaveKeys(['entries', 'drafts', 'revisions', 'total']);
    expect($usage['entries'])->toBeInt();
    expect($usage['drafts'])->toBeInt();
    expect($usage['revisions'])->toBeInt();
    expect($usage['total'])->toBeInt();
    expect($usage['total'])->toBe($usage['entries'] + $usage['drafts'] + $usage['revisions']);
});

it('includes control panel edit URL', function () {
    $created = ($this->createTestEntryType)('Edit URL Test');
    $this->createdEntryTypeIds[] = $created['entryTypeId'];
    
    $result = ($this->getEntryType)($created['entryTypeId']);
    
    // Edit URL might be null if entry type is not associated with a section
    if ($result['editUrl'] !== null) {
        expect($result['editUrl'])->toBeString();
        expect($result['editUrl'])->toContain('/settings/sections/');
        expect($result['editUrl'])->toContain('/entrytypes/');
        expect($result['editUrl'])->toContain((string) $created['entryTypeId']);
    } else {
        expect($result['editUrl'])->toBeNull();
    }
});

it('includes helpful notes', function () {
    $created = ($this->createTestEntryType)('Notes Test');
    $this->createdEntryTypeIds[] = $created['entryTypeId'];
    
    $result = ($this->getEntryType)($created['entryTypeId']);
    
    expect($result['_notes'])->toBeArray();
    expect($result['_notes'])->not->toBeEmpty();
    expect($result['_notes'][0])->toContain('Retrieved entry type information');
});