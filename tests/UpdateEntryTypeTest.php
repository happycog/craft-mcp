<?php

use happycog\craftmcp\tools\CreateEntryType;
use happycog\craftmcp\tools\UpdateEntryType;

beforeEach(function () {
    // Clean up any existing test entry types
    $entriesService = Craft::$app->getEntries();
    $testHandles = [
        'testUpdateEntryType', 'originalHandle', 'updatedHandle', 'duplicateHandle'
    ];
    
    foreach ($testHandles as $handle) {
        $entryType = $entriesService->getEntryTypeByHandle($handle);
        if ($entryType) {
            $entriesService->deleteEntryType($entryType);
        }
    }
    
    // Track created entry types for cleanup
    $this->createdEntryTypeIds = [];
    
    $this->createEntryType = function (string $name, array $options = []) {
        $createEntryType = Craft::$container->get(CreateEntryType::class);
        
        $result = $createEntryType->create(
            name: $name,
            handle: $options['handle'] ?? null,
            hasTitleField: $options['hasTitleField'] ?? true,
            titleTranslationMethod: $options['titleTranslationMethod'] ?? 'site',
            titleTranslationKeyFormat: $options['titleTranslationKeyFormat'] ?? null,
            icon: $options['icon'] ?? null,
            color: $options['color'] ?? null
        );
        
        $this->createdEntryTypeIds[] = $result['entryTypeId'];
        return $result;
    };
    
    $this->updateEntryType = function (int $entryTypeId, array $updates = []) {
        $updateEntryType = Craft::$container->get(UpdateEntryType::class);
        
        return $updateEntryType->update(
            entryTypeId: $entryTypeId,
            name: $updates['name'] ?? null,
            handle: $updates['handle'] ?? null,
            hasTitleField: $updates['hasTitleField'] ?? null,
            titleTranslationMethod: $updates['titleTranslationMethod'] ?? null,
            titleTranslationKeyFormat: $updates['titleTranslationKeyFormat'] ?? null,
            icon: $updates['icon'] ?? null,
            color: $updates['color'] ?? null
        );
    };
});

afterEach(function () {
    // Clean up any entry types that weren't deleted during the test
    $entriesService = Craft::$app->getEntries();
    
    foreach ($this->createdEntryTypeIds ?? [] as $entryTypeId) {
        $entryType = $entriesService->getEntryTypeById($entryTypeId);
        if ($entryType) {
            $entriesService->deleteEntryType($entryType);
        }
    }
});

it('can update entry type name', function () {
    $created = ($this->createEntryType)('Original Name', ['handle' => 'originalHandle']);
    
    $result = ($this->updateEntryType)($created['entryTypeId'], ['name' => 'Updated Name']);
    
    expect($result['name'])->toBe('Updated Name');
    expect($result['handle'])->toBe('originalHandle'); // Should remain unchanged
    expect($result['changes'])->toContain('name');
    
    // Verify in database
    $entryType = Craft::$app->getEntries()->getEntryTypeById($created['entryTypeId']);
    expect($entryType->name)->toBe('Updated Name');
});

it('can update entry type handle', function () {
    $created = ($this->createEntryType)('Test Entry Type', ['handle' => 'originalHandle']);
    
    $result = ($this->updateEntryType)($created['entryTypeId'], ['handle' => 'updatedHandle']);
    
    expect($result['handle'])->toBe('updatedHandle');
    expect($result['changes'])->toContain('handle');
    
    // Verify in database
    $entryType = Craft::$app->getEntries()->getEntryTypeById($created['entryTypeId']);
    expect($entryType->handle)->toBe('updatedHandle');
});

it('can toggle title field', function () {
    $created = ($this->createEntryType)('Test Entry Type', ['hasTitleField' => true]);
    
    $result = ($this->updateEntryType)($created['entryTypeId'], ['hasTitleField' => false]);
    
    expect($result['hasTitleField'])->toBeFalse();
    expect($result['changes'])->toContain('hasTitleField');
    
    // Verify in database
    $entryType = Craft::$app->getEntries()->getEntryTypeById($created['entryTypeId']);
    expect($entryType->hasTitleField)->toBeFalse();
});

it('can update translation method', function () {
    $created = ($this->createEntryType)('Test Entry Type', ['titleTranslationMethod' => 'site']);
    
    $result = ($this->updateEntryType)($created['entryTypeId'], ['titleTranslationMethod' => 'language']);
    
    expect($result['titleTranslationMethod'])->toBe(\craft\base\Field::TRANSLATION_METHOD_LANGUAGE);
    expect($result['changes'])->toContain('titleTranslationMethod');
    
    // Verify in database
    $entryType = Craft::$app->getEntries()->getEntryTypeById($created['entryTypeId']);
    expect($entryType->titleTranslationMethod)->toBe(\craft\base\Field::TRANSLATION_METHOD_LANGUAGE);
});

it('can update icon and color', function () {
    $created = ($this->createEntryType)('Test Entry Type');
    
    $result = ($this->updateEntryType)($created['entryTypeId'], [
        'icon' => 'news',
        'color' => 'blue'
    ]);
    
    expect($result['icon'])->toBe('news');
    expect($result['color'])->toBe('blue');
    expect($result['changes'])->toContain('icon');
    expect($result['changes'])->toContain('color');
    
    // Verify in database
    $entryType = Craft::$app->getEntries()->getEntryTypeById($created['entryTypeId']);
    expect($entryType->icon)->toBe('news');
    expect($entryType->color?->value)->toBe('blue');
});

it('can update translation key format', function () {
    $created = ($this->createEntryType)('Test Entry Type', ['titleTranslationMethod' => 'custom']);
    
    $keyFormat = '{site}_{slug}';
    $result = ($this->updateEntryType)($created['entryTypeId'], [
        'titleTranslationKeyFormat' => $keyFormat
    ]);
    
    expect($result['titleTranslationKeyFormat'])->toBe($keyFormat);
    expect($result['changes'])->toContain('titleTranslationKeyFormat');
    
    // Verify in database
    $entryType = Craft::$app->getEntries()->getEntryTypeById($created['entryTypeId']);
    expect($entryType->titleTranslationKeyFormat)->toBe($keyFormat);
});

it('can update multiple properties at once', function () {
    $created = ($this->createEntryType)('Original Name');
    
    $result = ($this->updateEntryType)($created['entryTypeId'], [
        'name' => 'Updated Name',
        'hasTitleField' => false,
        'icon' => 'article',
        'color' => 'red'
    ]);
    
    expect($result['name'])->toBe('Updated Name');
    expect($result['hasTitleField'])->toBeFalse();
    expect($result['icon'])->toBe('article');
    expect($result['color'])->toBe('red');
    
    expect($result['changes'])->toContain('name');
    expect($result['changes'])->toContain('hasTitleField');
    expect($result['changes'])->toContain('icon');
    expect($result['changes'])->toContain('color');
});

it('reports no changes when no updates are made', function () {
    $created = ($this->createEntryType)('Test Entry Type');
    
    $result = ($this->updateEntryType)($created['entryTypeId'], []);
    
    expect($result['changes'])->toBeEmpty();
    expect($result['_notes'])->toContain('No changes were made');
});

it('throws exception for non-existent entry type', function () {
    expect(fn() => ($this->updateEntryType)(99999, ['name' => 'Test']))
        ->toThrow(\InvalidArgumentException::class, 'Entry type with ID 99999 not found');
});

it('throws exception for duplicate handle', function () {
    $created1 = ($this->createEntryType)('First Entry Type', ['handle' => 'duplicateHandle']);
    $created2 = ($this->createEntryType)('Second Entry Type', ['handle' => 'secondHandle']);
    
    expect(fn() => ($this->updateEntryType)($created2['entryTypeId'], ['handle' => 'duplicateHandle']))
        ->toThrow(\InvalidArgumentException::class, "An entry type with handle 'duplicateHandle' already exists");
});

it('throws exception for invalid translation method', function () {
    $created = ($this->createEntryType)('Test Entry Type');
    
    expect(fn() => ($this->updateEntryType)($created['entryTypeId'], ['titleTranslationMethod' => 'invalid']))
        ->toThrow(\InvalidArgumentException::class, "Invalid translation method 'invalid'");
});

it('throws exception for invalid color', function () {
    $created = ($this->createEntryType)('Test Entry Type');
    
    expect(fn() => ($this->updateEntryType)($created['entryTypeId'], ['color' => 'rainbow']))
        ->toThrow(\InvalidArgumentException::class, "Invalid color 'rainbow'");
});

it('includes control panel edit URL', function () {
    $created = ($this->createEntryType)('Test Entry Type');
    
    $result = ($this->updateEntryType)($created['entryTypeId'], ['name' => 'Updated Name']);
    
    expect($result['editUrl'])->toContain('/settings/entry-types/');
    expect($result['editUrl'])->toContain((string)$created['entryTypeId']);
});

it('preserves field layout ID', function () {
    $created = ($this->createEntryType)('Test Entry Type');
    $originalFieldLayoutId = $created['fieldLayoutId'];
    
    $result = ($this->updateEntryType)($created['entryTypeId'], ['name' => 'Updated Name']);
    
    expect($result['fieldLayoutId'])->toBe($originalFieldLayoutId);
});

it('returns all expected response fields', function () {
    $created = ($this->createEntryType)('Test Entry Type');
    
    $result = ($this->updateEntryType)($created['entryTypeId'], ['name' => 'Updated Name']);
    
    expect($result)->toHaveKeys([
        '_notes',
        'entryTypeId',
        'name',
        'handle',
        'hasTitleField',
        'titleTranslationMethod',
        'titleTranslationKeyFormat',
        'icon',
        'color',
        'fieldLayoutId',
        'editUrl',
        'changes'
    ]);
});