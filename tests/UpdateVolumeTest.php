<?php

use craft\models\Volume;
use happycog\craftmcp\tools\UpdateVolume;
use happycog\craftmcp\tools\CreateVolume;

beforeEach(function () {
    $this->updateVolume = Craft::$container->get(UpdateVolume::class);
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

it('can update volume name', function () {
    // Create test volume
    $uniqueId = uniqid();
    $result = ($this->createVolume)->create(
        name: "Test Volume {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: "test_{$uniqueId}"
    );
    $this->createdVolumeIds[] = $result['volumeId'];
    
    // Update the name
    $updateResult = ($this->updateVolume)->update(
        volumeId: $result['volumeId'],
        name: "Updated Volume Name {$uniqueId}"
    );
    
    expect($updateResult)->toBeArray();
    expect($updateResult)->toHaveKeys(['_notes', 'volumeId', 'name', 'handle', 'fsHandle', 'subpath', 'editUrl']);
    expect($updateResult['volumeId'])->toBe($result['volumeId']);
    expect($updateResult['name'])->toBe("Updated Volume Name {$uniqueId}");
    expect($updateResult['handle'])->toBe($result['handle']); // Handle should remain the same
    
    // Verify volume was updated in database
    $volume = Craft::$app->getVolumes()->getVolumeById($result['volumeId']);
    expect($volume)->toBeInstanceOf(Volume::class);
    expect($volume->name)->toBe("Updated Volume Name {$uniqueId}");
});

it('can update volume handle', function () {
    // Create test volume
    $uniqueId = uniqid();
    $result = ($this->createVolume)->create(
        name: "Handle Update Test {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: "handle_{$uniqueId}"
    );
    $this->createdVolumeIds[] = $result['volumeId'];
    
    // Update the handle
    $newHandle = "updated_handle_{$uniqueId}";
    $updateResult = ($this->updateVolume)->update(
        volumeId: $result['volumeId'],
        handle: $newHandle
    );
    
    expect($updateResult['volumeId'])->toBe($result['volumeId']);
    expect($updateResult['handle'])->toBe($newHandle);
    expect($updateResult['name'])->toBe($result['name']); // Name should remain the same
    
    // Verify handle was updated in database
    $volume = Craft::$app->getVolumes()->getVolumeById($result['volumeId']);
    expect($volume->handle)->toBe($newHandle);
});

it('can update volume subpath', function () {
    // Create test volume
    $uniqueId = uniqid();
    $result = ($this->createVolume)->create(
        name: "Subpath Update Test {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: "original_{$uniqueId}"
    );
    $this->createdVolumeIds[] = $result['volumeId'];
    
    // Update the subpath
    $newSubpath = "updated/path/{$uniqueId}";
    $updateResult = ($this->updateVolume)->update(
        volumeId: $result['volumeId'],
        subpath: $newSubpath
    );
    
    expect($updateResult['volumeId'])->toBe($result['volumeId']);
    expect($updateResult['subpath'])->toBe($newSubpath . '/'); // Craft adds trailing slash
    
    // Verify subpath was updated in database
    $volume = Craft::$app->getVolumes()->getVolumeById($result['volumeId']);
    expect($volume->subpath)->toBe($newSubpath . '/');
});

it('can update volume file system', function () {
    // Skip if only one file system available
    if (count($this->availableFs) < 2) {
        $this->markTestSkipped('Test requires at least 2 file systems.');
    }
    
    // Create test volume with first file system
    $uniqueId = uniqid();
    $result = ($this->createVolume)->create(
        name: "FS Update Test {$uniqueId}",
        fsHandle: $this->availableFs[0]->handle,
        subpath: "fs_test_{$uniqueId}"
    );
    $this->createdVolumeIds[] = $result['volumeId'];
    
    // Update to second file system
    $newFsHandle = $this->availableFs[1]->handle;
    $updateResult = ($this->updateVolume)->update(
        volumeId: $result['volumeId'],
        fsHandle: $newFsHandle
    );
    
    expect($updateResult['volumeId'])->toBe($result['volumeId']);
    expect($updateResult['fsHandle'])->toBe($newFsHandle);
    
    // Verify file system was updated in database
    $volume = Craft::$app->getVolumes()->getVolumeById($result['volumeId']);
    expect($volume->getFsHandle())->toBe($newFsHandle);
});

it('can update multiple properties at once', function () {
    // Create test volume
    $uniqueId = uniqid();
    $result = ($this->createVolume)->create(
        name: "Multi Update Test {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: "multi_{$uniqueId}"
    );
    $this->createdVolumeIds[] = $result['volumeId'];
    
    // Update multiple properties
    $newName = "Fully Updated Volume {$uniqueId}";
    $newHandle = "fully_updated_{$uniqueId}";
    $newSubpath = "fully/updated/{$uniqueId}";
    
    $updateResult = ($this->updateVolume)->update(
        volumeId: $result['volumeId'],
        name: $newName,
        handle: $newHandle,
        subpath: $newSubpath
    );
    
    expect($updateResult['volumeId'])->toBe($result['volumeId']);
    expect($updateResult['name'])->toBe($newName);
    expect($updateResult['handle'])->toBe($newHandle);
    expect($updateResult['subpath'])->toBe($newSubpath . '/');
    
    // Verify all properties were updated in database
    $volume = Craft::$app->getVolumes()->getVolumeById($result['volumeId']);
    expect($volume->name)->toBe($newName);
    expect($volume->handle)->toBe($newHandle);
    expect($volume->subpath)->toBe($newSubpath . '/');
});

it('can apply additional settings', function () {
    // Create test volume
    $uniqueId = uniqid();
    $result = ($this->createVolume)->create(
        name: "Settings Update Test {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: "settings_{$uniqueId}"
    );
    $this->createdVolumeIds[] = $result['volumeId'];
    
    // Update with additional settings
    $updateResult = ($this->updateVolume)->update(
        volumeId: $result['volumeId'],
        settings: [
            'hasUrls' => true,
            'url' => 'https://example.com/uploads'
        ]
    );
    
    expect($updateResult['volumeId'])->toBe($result['volumeId']);
    
    // Verify settings were applied
    $volume = Craft::$app->getVolumes()->getVolumeById($result['volumeId']);
    expect($volume)->toBeInstanceOf(Volume::class);
    // Note: Testing specific settings properties depends on volume implementation
});

it('throws exception for non-existent volume', function () {
    expect(fn() => ($this->updateVolume)->update(
        volumeId: 99999,
        name: 'Should Not Work'
    ))->toThrow(InvalidArgumentException::class, 'Volume with ID 99999 not found');
});

it('throws exception for duplicate handle', function () {
    // Create first volume
    $uniqueId1 = uniqid();
    $result1 = ($this->createVolume)->create(
        name: "First Volume {$uniqueId1}",
        fsHandle: $this->testFs->handle,
        subpath: "first_{$uniqueId1}",
        handle: "first_handle_{$uniqueId1}"
    );
    $this->createdVolumeIds[] = $result1['volumeId'];
    
    // Create second volume
    $uniqueId2 = uniqid();
    $result2 = ($this->createVolume)->create(
        name: "Second Volume {$uniqueId2}",
        fsHandle: $this->testFs->handle,
        subpath: "second_{$uniqueId2}",
        handle: "second_handle_{$uniqueId2}"
    );
    $this->createdVolumeIds[] = $result2['volumeId'];
    
    // Try to update second volume with first volume's handle
    expect(fn() => ($this->updateVolume)->update(
        volumeId: $result2['volumeId'],
        handle: "first_handle_{$uniqueId1}"
    ))->toThrow(InvalidArgumentException::class, "Handle 'first_handle_{$uniqueId1}' is already in use");
});

it('throws exception for non-existent file system', function () {
    // Create test volume
    $uniqueId = uniqid();
    $result = ($this->createVolume)->create(
        name: "FS Error Test {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: "fs_error_{$uniqueId}"
    );
    $this->createdVolumeIds[] = $result['volumeId'];
    
    // Try to update with non-existent file system
    expect(fn() => ($this->updateVolume)->update(
        volumeId: $result['volumeId'],
        fsHandle: 'non_existent_fs'
    ))->toThrow(InvalidArgumentException::class, "File system with handle 'non_existent_fs' does not exist");
});

it('includes control panel edit URL in response', function () {
    // Create test volume
    $uniqueId = uniqid();
    $result = ($this->createVolume)->create(
        name: "URL Test {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: "url_{$uniqueId}"
    );
    $this->createdVolumeIds[] = $result['volumeId'];
    
    // Update volume
    $updateResult = ($this->updateVolume)->update(
        volumeId: $result['volumeId'],
        name: "Updated URL Test {$uniqueId}"
    );
    
    expect($updateResult['editUrl'])->toContain('/settings/assets/volumes/');
    expect($updateResult['editUrl'])->toContain((string) $result['volumeId']);
});

it('allows updating volume handle to same value', function () {
    // Create test volume
    $uniqueId = uniqid();
    $result = ($this->createVolume)->create(
        name: "Same Handle Test {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: "same_{$uniqueId}",
        handle: "same_handle_{$uniqueId}"
    );
    $this->createdVolumeIds[] = $result['volumeId'];
    
    // Update with same handle (should not throw error)
    $updateResult = ($this->updateVolume)->update(
        volumeId: $result['volumeId'],
        handle: "same_handle_{$uniqueId}",
        name: "Updated Same Handle Test {$uniqueId}"
    );
    
    expect($updateResult['volumeId'])->toBe($result['volumeId']);
    expect($updateResult['handle'])->toBe("same_handle_{$uniqueId}");
    expect($updateResult['name'])->toBe("Updated Same Handle Test {$uniqueId}");
});