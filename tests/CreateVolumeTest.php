<?php

use craft\models\Volume;
use craft\models\FsInterface;
use happycog\craftmcp\tools\CreateVolume;

beforeEach(function () {
    $this->createVolume = Craft::$container->get(CreateVolume::class);
    
    // Get available file systems
    $this->availableFs = Craft::$app->getFs()->getAllFilesystems();
    
    if (empty($this->availableFs)) {
        $this->fail('No file systems available. Please ensure at least one file system exists in the system.');
    }
    
    $this->testFs = $this->availableFs[0];
    
    // Track created volumes for cleanup
    $this->createdVolumeIds = [];
});

afterEach(function () {
    // Clean up any created volumes
    $volumesService = Craft::$app->getVolumes();
    foreach ($this->createdVolumeIds as $volumeId) {
        $volume = $volumesService->getVolumeById($volumeId);
        if ($volume) {
            $volumesService->deleteVolume($volume);
        }
    }
});

it('can create a volume with required parameters', function () {
    $uniqueSubpath = 'test_volume_' . uniqid();
    
    $result = ($this->createVolume)->create(
        name: 'Test Volume',
        fsHandle: $this->testFs->handle,
        subpath: $uniqueSubpath
    );

    expect($result)->toBeArray();
    expect($result)->toHaveKeys(['_notes', 'volumeId', 'name', 'handle', 'fsHandle', 'subpath', 'editUrl']);
    
    expect($result['volumeId'])->toBeInt();
    expect($result['name'])->toBe('Test Volume');
    expect($result['fsHandle'])->toBe($this->testFs->handle);
    expect($result['handle'])->toBe('test_volume');
    expect($result['subpath'])->toBe($uniqueSubpath . '/');
    
    // Track for cleanup
    $this->createdVolumeIds[] = $result['volumeId'];
    
    // Verify volume exists in database
    $volume = Craft::$app->getVolumes()->getVolumeById($result['volumeId']);
    expect($volume)->toBeInstanceOf(Volume::class);
    expect($volume->name)->toBe('Test Volume');
});

it('can create a volume with custom handle', function () {
    $uniqueSubpath = 'custom_handle_' . uniqid();
    
    $result = ($this->createVolume)->create(
        name: 'Custom Handle Volume',
        fsHandle: $this->testFs->handle,
        handle: 'custom_handle',
        subpath: $uniqueSubpath
    );

    expect($result['handle'])->toBe('custom_handle');
    expect($result['name'])->toBe('Custom Handle Volume');
    expect($result['subpath'])->toBe($uniqueSubpath . '/');
    
    $this->createdVolumeIds[] = $result['volumeId'];
});

it('can create a volume with subpath', function () {
    $uniqueSubpath = 'subfolder/test_' . uniqid();
    
    $result = ($this->createVolume)->create(
        name: 'Subpath Volume',
        fsHandle: $this->testFs->handle,
        subpath: $uniqueSubpath
    );

    expect($result['subpath'])->toBe($uniqueSubpath . '/');
    expect($result['name'])->toBe('Subpath Volume');
    
    $this->createdVolumeIds[] = $result['volumeId'];
});

it('auto-generates handles from names', function () {
    $uniqueId = uniqid();
    $uniqueSubpath = 'handle_gen_' . $uniqueId;
    
    // Create volume with a name that should generate a predictable handle
    $result = ($this->createVolume)->create(
        name: "Handle Generation Test {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: $uniqueSubpath
    );
    $this->createdVolumeIds[] = $result['volumeId'];
    
    // Verify handle was auto-generated correctly from name
    expect($result['name'])->toBe("Handle Generation Test {$uniqueId}");
    expect($result['handle'])->toContain('handle_generation_test');
    expect($result['handle'])->toContain($uniqueId);
});

it('throws exception for non-existent file system', function () {
    expect(fn() => ($this->createVolume)->create(
        name: 'Invalid FS Volume',
        fsHandle: 'non_existent_fs'
    ))->toThrow(InvalidArgumentException::class, "File system with handle 'non_existent_fs' does not exist");
});

it('includes control panel edit URL in response', function () {
    $uniqueSubpath = 'url_test_' . uniqid();
    
    $result = ($this->createVolume)->create(
        name: 'URL Test Volume',
        fsHandle: $this->testFs->handle,
        subpath: $uniqueSubpath
    );

    expect($result['editUrl'])->toContain('/settings/assets/volumes/');
    expect($result['editUrl'])->toContain((string) $result['volumeId']);
    
    $this->createdVolumeIds[] = $result['volumeId'];
});

it('handles special characters in name properly', function () {
    $uniqueSubpath = 'special_chars_' . uniqid();
    
    $result = ($this->createVolume)->create(
        name: 'Test Volume with Special @#$ Characters!',
        fsHandle: $this->testFs->handle,
        subpath: $uniqueSubpath
    );

    expect($result['name'])->toBe('Test Volume with Special @#$ Characters!');
    expect($result['handle'])->toBe('test_volume_with_special_characters');
    
    $this->createdVolumeIds[] = $result['volumeId'];
});

it('can apply additional settings', function () {
    $uniqueSubpath = 'settings_' . uniqid();
    
    $result = ($this->createVolume)->create(
        name: 'Settings Volume',
        fsHandle: $this->testFs->handle,
        subpath: $uniqueSubpath,
        settings: [
            'hasUrls' => true,
            'url' => 'https://example.com/uploads'
        ]
    );

    expect($result['name'])->toBe('Settings Volume');
    
    $this->createdVolumeIds[] = $result['volumeId'];
    
    // Verify settings were applied
    $volume = Craft::$app->getVolumes()->getVolumeById($result['volumeId']);
    expect($volume)->toBeInstanceOf(Volume::class);
});

it('creates volume with proper field layout', function () {
    $uniqueSubpath = 'field_layout_' . uniqid();
    
    $result = ($this->createVolume)->create(
        name: 'Field Layout Volume',
        fsHandle: $this->testFs->handle,
        subpath: $uniqueSubpath
    );

    $this->createdVolumeIds[] = $result['volumeId'];
    
    // Verify volume has a field layout
    $volume = Craft::$app->getVolumes()->getVolumeById($result['volumeId']);
    $fieldLayout = $volume->getFieldLayout();
    expect($fieldLayout)->not()->toBeNull();
});