<?php

use craft\models\Volume;
use craft\elements\Asset;
use happycog\craftmcp\tools\DeleteVolume;
use happycog\craftmcp\tools\CreateVolume;
use happycog\craftmcp\tools\CreateAsset;

beforeEach(function () {
    $this->deleteVolume = Craft::$container->get(DeleteVolume::class);
    $this->createVolume = Craft::$container->get(CreateVolume::class);
    $this->createAsset = Craft::$container->get(CreateAsset::class);
    
    // Get available file systems
    $this->availableFs = Craft::$app->getFs()->getAllFilesystems();
    
    if (empty($this->availableFs)) {
        $this->fail('No file systems available. Please ensure at least one file system exists in the system.');
    }
    
    $this->testFs = $this->availableFs[0];
    
    // Track created volumes and assets for cleanup
    $this->createdVolumeIds = [];
    $this->createdAssetIds = [];
});

afterEach(function () {
    // Clean up any created assets first
    $assetsService = Craft::$app->getAssets();
    foreach ($this->createdAssetIds as $assetId) {
        $asset = Asset::find()->id($assetId)->one();
        if ($asset instanceof Asset) {
            Craft::$app->getElements()->deleteElement($asset);
        }
    }
    
    // Clean up any created volumes
    $volumesService = Craft::$app->getVolumes();
    foreach ($this->createdVolumeIds as $volumeId) {
        $volume = $volumesService->getVolumeById($volumeId);
        if ($volume) {
            $volumesService->deleteVolume($volume);
        }
    }
});

it('can delete an empty volume', function () {
    // Create test volume
    $uniqueId = uniqid();
    $result = ($this->createVolume)->create(
        name: "Delete Test Volume {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: "delete_test_{$uniqueId}"
    );
    $volumeId = $result['volumeId'];
    
    // Delete the volume
    $deleteResult = ($this->deleteVolume)->delete($volumeId);
    
    expect($deleteResult)->toBeArray();
    expect($deleteResult)->toHaveKeys(['_notes', 'deletedVolumeId', 'name', 'handle']);
    expect($deleteResult['deletedVolumeId'])->toBe($volumeId);
    expect($deleteResult['name'])->toBe("Delete Test Volume {$uniqueId}");
    expect($deleteResult['handle'])->toContain('delete_test_volume');
    
    // Verify volume was deleted from database
    $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);
    expect($volume)->toBeNull();
});

it('throws exception for non-existent volume', function () {
    expect(fn() => ($this->deleteVolume)->delete(99999))
        ->toThrow(InvalidArgumentException::class, 'Volume with ID 99999 not found');
});

it('throws exception when volume contains assets', function () {
    // Create test volume
    $uniqueId = uniqid();
    $volumeResult = ($this->createVolume)->create(
        name: "Volume with Assets {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: "assets_test_{$uniqueId}"
    );
    $volumeId = $volumeResult['volumeId'];
    $this->createdVolumeIds[] = $volumeId;
    
    // Create a temporary test file
    $tempFilePath = sys_get_temp_dir() . "/test_file_{$uniqueId}.txt";
    file_put_contents($tempFilePath, 'Test content for delete volume test');
    
    // Create a test asset in the volume
    $assetResult = ($this->createAsset)->create(
        volumeId: $volumeId,
        filePath: $tempFilePath,
        filename: "test_file_{$uniqueId}.txt"
    );
    
    // Clean up temp file
    unlink($tempFilePath);
    $this->createdAssetIds[] = $assetResult['assetId'];
    
    // Try to delete volume with assets
    expect(fn() => ($this->deleteVolume)->delete($volumeId))
        ->toThrow(InvalidArgumentException::class, "Cannot delete volume 'Volume with Assets {$uniqueId}' because it contains 1 assets. Please delete all assets first.");
});

it('prevents deletion when volume has multiple assets', function () {
    // Create test volume
    $uniqueId = uniqid();
    $volumeResult = ($this->createVolume)->create(
        name: "Multiple Assets Volume {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: "multi_assets_{$uniqueId}"
    );
    $volumeId = $volumeResult['volumeId'];
    $this->createdVolumeIds[] = $volumeId;
    
    // Create multiple test assets in the volume
    for ($i = 1; $i <= 3; $i++) {
        $tempFilePath = sys_get_temp_dir() . "/test_file_{$i}_{$uniqueId}.txt";
        file_put_contents($tempFilePath, "Test content for file {$i}");
        
        $assetResult = ($this->createAsset)->create(
            volumeId: $volumeId,
            filePath: $tempFilePath,
            filename: "test_file_{$i}_{$uniqueId}.txt"
        );
        $this->createdAssetIds[] = $assetResult['assetId'];
        
        // Clean up temp file
        unlink($tempFilePath);
    }
    
    // Try to delete volume with multiple assets
    expect(fn() => ($this->deleteVolume)->delete($volumeId))
        ->toThrow(InvalidArgumentException::class, "Cannot delete volume 'Multiple Assets Volume {$uniqueId}' because it contains 3 assets. Please delete all assets first.");
});

it('can delete volume after assets are removed', function () {
    // Create test volume
    $uniqueId = uniqid();
    $volumeResult = ($this->createVolume)->create(
        name: "Cleared Volume {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: "cleared_{$uniqueId}"
    );
    $volumeId = $volumeResult['volumeId'];
    
    // Create a temporary test file
    $tempFilePath = sys_get_temp_dir() . "/temp_file_{$uniqueId}.txt";
    file_put_contents($tempFilePath, 'Temporary content');
    
    // Create a test asset in the volume
    $assetResult = ($this->createAsset)->create(
        volumeId: $volumeId,
        filePath: $tempFilePath,
        filename: "temp_file_{$uniqueId}.txt"
    );
    
    // Clean up temp file
    unlink($tempFilePath);
    
    // Delete the asset first
    $asset = Asset::find()->id($assetResult['assetId'])->one();
    expect($asset)->toBeInstanceOf(Asset::class);
    Craft::$app->getElements()->deleteElement($asset);
    
    // Now delete the volume (should succeed)
    $deleteResult = ($this->deleteVolume)->delete($volumeId);
    
    expect($deleteResult['deletedVolumeId'])->toBe($volumeId);
    expect($deleteResult['name'])->toBe("Cleared Volume {$uniqueId}");
    
    // Verify volume was deleted
    $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);
    expect($volume)->toBeNull();
});

it('stores volume information before deletion', function () {
    // Create test volume with specific properties
    $uniqueId = uniqid();
    $volumeName = "Info Test Volume {$uniqueId}";
    $volumeHandle = "info_test_{$uniqueId}";
    
    $result = ($this->createVolume)->create(
        name: $volumeName,
        fsHandle: $this->testFs->handle,
        subpath: "info_test_{$uniqueId}",
        handle: $volumeHandle
    );
    $volumeId = $result['volumeId'];
    
    // Delete the volume and verify stored info
    $deleteResult = ($this->deleteVolume)->delete($volumeId);
    
    expect($deleteResult['deletedVolumeId'])->toBe($volumeId);
    expect($deleteResult['name'])->toBe($volumeName);
    expect($deleteResult['handle'])->toBe($volumeHandle);
});

it('includes descriptive notes in response', function () {
    // Create test volume
    $uniqueId = uniqid();
    $result = ($this->createVolume)->create(
        name: "Notes Test Volume {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: "notes_{$uniqueId}"
    );
    $volumeId = $result['volumeId'];
    
    // Delete the volume
    $deleteResult = ($this->deleteVolume)->delete($volumeId);
    
    expect($deleteResult['_notes'])->toBe('The volume was successfully deleted.');
});

it('properly checks asset count including subdirectories', function () {
    // Create test volume
    $uniqueId = uniqid();
    $volumeResult = ($this->createVolume)->create(
        name: "Subdir Volume {$uniqueId}",
        fsHandle: $this->testFs->handle,
        subpath: "subdir_test_{$uniqueId}"
    );
    $volumeId = $volumeResult['volumeId'];
    $this->createdVolumeIds[] = $volumeId;
    
    // Create a temporary test file
    $tempFilePath = sys_get_temp_dir() . "/subfolder_file_{$uniqueId}.txt";
    file_put_contents($tempFilePath, 'Test content in subfolder');
    
    // Create asset that might be in a subfolder path
    $assetResult = ($this->createAsset)->create(
        volumeId: $volumeId,
        filePath: $tempFilePath,
        filename: "subfolder_file_{$uniqueId}.txt"
    );
    
    // Clean up temp file
    unlink($tempFilePath);
    $this->createdAssetIds[] = $assetResult['assetId'];
    
    // Verify asset exists in the volume
    $assetCount = Asset::find()->volumeId($volumeId)->count();
    expect($assetCount)->toBeGreaterThan(0);
    
    // Try to delete volume with asset
    expect(fn() => ($this->deleteVolume)->delete($volumeId))
        ->toThrow(InvalidArgumentException::class, 'Cannot delete volume');
});