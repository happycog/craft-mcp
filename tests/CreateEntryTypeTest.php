<?php

use happycog\craftmcp\tools\CreateEntryType;

beforeEach(function () {
    // Clean up any existing test entry types before each test
    $entriesService = Craft::$app->getEntries();
    $testHandles = [
        'testEntryType', 'customHandle', 'blogPost', 'productListing',
        'duplicateHandle', 'complexEntryTypeNameWithCharacters', 'entryType123NumericType'
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
        
        // Track the created entry type for cleanup
        $this->createdEntryTypeIds[] = $result['entryTypeId'];
        
        return $result;
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

it('can create a basic entry type', function () {
    $result = ($this->createEntryType)('Test Entry Type');
    
    expect($result)->toHaveKeys(['entryTypeId', 'name', 'handle', 'hasTitleField', 'editUrl']);
    expect($result['name'])->toBe('Test Entry Type');
    expect($result['handle'])->toBe('testEntryType');
    expect($result['hasTitleField'])->toBeTrue();
    
    // Verify entry type was actually created
    $entryType = Craft::$app->getEntries()->getEntryTypeById($result['entryTypeId']);
    expect($entryType)->not->toBeNull();
    expect($entryType->name)->toBe('Test Entry Type');
    expect($entryType->handle)->toBe('testEntryType');
});

it('can create an entry type with custom handle', function () {
    $result = ($this->createEntryType)(
        'Custom Entry Type',
        ['handle' => 'customHandle']
    );
    
    expect($result['handle'])->toBe('customHandle');
    
    $entryType = Craft::$app->getEntries()->getEntryTypeById($result['entryTypeId']);
    expect($entryType->handle)->toBe('customHandle');
});

it('can create an entry type without title field', function () {
    $result = ($this->createEntryType)(
        'No Title Entry Type',
        ['hasTitleField' => false]
    );
    
    expect($result['hasTitleField'])->toBeFalse();
    
    $entryType = Craft::$app->getEntries()->getEntryTypeById($result['entryTypeId']);
    expect($entryType->hasTitleField)->toBeFalse();
});

it('can create an entry type with custom translation method', function () {
    $result = ($this->createEntryType)(
        'Translation Entry Type',
        ['titleTranslationMethod' => 'language']
    );
    
    expect($result['titleTranslationMethod'])->toBe(\craft\base\Field::TRANSLATION_METHOD_LANGUAGE);
    
    $entryType = Craft::$app->getEntries()->getEntryTypeById($result['entryTypeId']);
    expect($entryType->titleTranslationMethod)->toBe(\craft\base\Field::TRANSLATION_METHOD_LANGUAGE);
});

it('can create an entry type with icon and color', function () {
    $result = ($this->createEntryType)(
        'Styled Entry Type',
        [
            'icon' => 'news',
            'color' => 'blue'
        ]
    );
    
    expect($result['icon'])->toBe('news');
    expect($result['color'])->toBe('blue');
    
    expect($entryType = Craft::$app->getEntries()->getEntryTypeById($result['entryTypeId']))->not->toBeNull();
    expect($entryType->icon)->toBe('news');
    expect($entryType->color?->value)->toBe('blue');
});

it('can create an entry type with custom title translation key format', function () {
    $keyFormat = '{site}_{slug}';
    $result = ($this->createEntryType)(
        'Custom Key Entry Type',
        [
            'titleTranslationMethod' => 'custom',
            'titleTranslationKeyFormat' => $keyFormat
        ]
    );
    
    expect($result['titleTranslationKeyFormat'])->toBe($keyFormat);
    
    $entryType = Craft::$app->getEntries()->getEntryTypeById($result['entryTypeId']);
    expect($entryType->titleTranslationKeyFormat)->toBe($keyFormat);
});

it('throws exception for duplicate handle', function () {
    // Create first entry type
    ($this->createEntryType)('First Entry Type', ['handle' => 'duplicateHandle']);
    
    // Try to create second entry type with same handle
    expect(fn() => ($this->createEntryType)(
        'Second Entry Type',
        ['handle' => 'duplicateHandle']
    ))->toThrow(InvalidArgumentException::class, "An entry type with handle 'duplicateHandle' already exists.");
});

it('generates valid handle from entry type name', function () {
    $result = ($this->createEntryType)('Complex Entry Type Name! With @#$% Characters');
    
    expect($result['handle'])->toBe('complexEntryTypeNameWithCharacters');
});

it('handles entry type names starting with numbers', function () {
    $result = ($this->createEntryType)('123 Numeric Type');
    
    expect($result['handle'])->toBe('entryType123NumericType');
});

it('includes control panel edit URL', function () {
    $result = ($this->createEntryType)('URL Test Entry Type');
    
    expect($result['editUrl'])->toContain('/settings/entry-types/');
    expect($result['editUrl'])->toContain((string)$result['entryTypeId']);
});

it('throws exception for invalid translation method', function () {
    expect(fn() => ($this->createEntryType)(
        'Invalid Translation Test',
        ['titleTranslationMethod' => 'invalid']
    ))->toThrow(InvalidArgumentException::class, "Invalid translation method 'invalid'");
});

it('creates entry type with field layout', function () {
    $result = ($this->createEntryType)('Layout Test Entry Type');
    
    expect($result['fieldLayoutId'])->not->toBeNull();
    
    $entryType = Craft::$app->getEntries()->getEntryTypeById($result['entryTypeId']);
    expect($entryType->fieldLayoutId)->not->toBeNull();
    
    $fieldLayout = $entryType->getFieldLayout();
    expect($fieldLayout)->not->toBeNull();
});

it('returns all expected response fields', function () {
    $result = ($this->createEntryType)(
        'Complete Test Entry Type',
        [
            'handle' => 'completeTest',
            'hasTitleField' => false,
            'titleTranslationMethod' => 'language',
            'titleTranslationKeyFormat' => '{site}_{id}',
            'icon' => 'article',
            'color' => 'red'
        ]
    );
    
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
        'editUrl'
    ]);
    
    expect($result['_notes'])->toContain('successfully created');
});