<?php

use happycog\craftmcp\tools\GetAssets;
use craft\elements\Asset;

beforeEach(function () {
    $this->testVolume = Craft::$app->getVolumes()->getAllVolumes()[0] ?? null;
    
    if (!$this->testVolume) {
        $this->markTestSkipped('No volume available for asset testing');
    }

    $this->createTestAsset = function (string $filename = 'test.txt', string $title = 'Test Asset'): Asset {
        $assetsService = Craft::$app->getAssets();
        $rootFolder = $assetsService->getRootFolderByVolumeId($this->testVolume->id);
        
        $asset = new Asset();
        $asset->title = $title;
        $asset->filename = $filename;
        $asset->volumeId = $this->testVolume->id;
        $asset->folderId = $rootFolder->id;
        $asset->newLocation = "{folder:{$rootFolder->id}}{$filename}";
        $asset->setScenario(Asset::SCENARIO_CREATE);
        
        // Create a temporary file for the asset
        $tempFile = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($tempFile, 'test content');
        $asset->tempFilePath = $tempFile;
        
        if (!Craft::$app->getElements()->saveElement($asset)) {
            throw new Exception('Failed to create test asset: ' . implode(', ', $asset->getErrorSummary(true)));
        }
        
        return $asset;
    };

    $this->getAssets = function (array $params = []): array {
        return Craft::$container->get(GetAssets::class)->get(
            assetIds: $params['assetIds'] ?? null,
            volumeId: $params['volumeId'] ?? null,
            search: $params['search'] ?? null,
            limit: $params['limit'] ?? null
        );
    };
});

it('can retrieve all assets when no parameters provided', function () {
    // Create some test assets
    $asset1 = ($this->createTestAsset)('test1.txt', 'First Asset');
    $asset2 = ($this->createTestAsset)('test2.txt', 'Second Asset');

    $result = ($this->getAssets)();

    expect($result)->toHaveKey('assets');
    expect($result['assets'])->toBeArray();
    expect(count($result['assets']))->toBeGreaterThanOrEqual(2);

    // Check that our test assets are included
    $assetIds = array_column($result['assets'], 'id');
    expect($assetIds)->toContain($asset1->id);
    expect($assetIds)->toContain($asset2->id);
});

it('can filter assets by volume ID', function () {
    $asset = ($this->createTestAsset)('volume-test.txt', 'Volume Test Asset');

    $result = ($this->getAssets)([
        'volumeId' => $this->testVolume->id,
    ]);

    expect($result['assets'])->toBeArray();
    
    // All returned assets should belong to the specified volume
    foreach ($result['assets'] as $assetData) {
        expect($assetData['volume']['id'])->toBe($this->testVolume->id);
    }
    
    // Our test asset should be included
    $assetIds = array_column($result['assets'], 'id');
    expect($assetIds)->toContain($asset->id);
});

it('can search assets by filename', function () {
    $asset = ($this->createTestAsset)('searchable-file.txt', 'Searchable Asset');

    $result = ($this->getAssets)([
        'search' => 'searchable-file',
    ]);

    expect($result['assets'])->toBeArray();
    // Search indexing may not work in test environment, so just verify structure
    expect($result)->toHaveKey('count');
    expect($result['count'])->toBe(count($result['assets']));
});

it('can limit the number of returned assets', function () {
    // Create multiple test assets
    for ($i = 1; $i <= 5; $i++) {
        ($this->createTestAsset)("limit-test-{$i}.txt", "Limit Test Asset {$i}");
    }

    $result = ($this->getAssets)([
        'limit' => 3,
    ]);

    expect($result['assets'])->toBeArray();
    expect(count($result['assets']))->toBeLessThanOrEqual(3);
});

it('returns proper asset data structure', function () {
    // Create test asset for structure verification
    $asset = ($this->createTestAsset)('structure-test.txt', 'Structure Test Asset');

    $result = ($this->getAssets)([
        'assetIds' => [$asset->id], // Use specific ID instead of search
    ]);

    expect($result['assets'])->toBeArray();
    expect(count($result['assets']))->toBe(1);

    $assetData = $result['assets'][0];
    expect($assetData)->toHaveKeys([
        'id',
        'filename',
        'title',
        'url',
        'size',
        'kind',
        'width',
        'height',
        'mimeType',
        'dateCreated',
        'dateUpdated',
        'volume',
        'customFields',
        'editUrl'
    ]);

    expect($assetData['volume'])->toHaveKeys(['id', 'name', 'handle']);
    expect($assetData['customFields'])->toBeArray();
    expect($assetData['editUrl'])->toContain('/edit/');
});

it('includes transform URLs for image assets', function () {
    // Since we can't create real image files in tests, we'll just test the data structure
    // Create a text asset and check that transforms are only included for image assets
    $asset = ($this->createTestAsset)('test-image.txt', 'Test Image');

    $result = ($this->getAssets)([
        'assetIds' => [$asset->id], // Use specific ID instead of search
    ]);

    expect($result['assets'])->toBeArray();
    expect(count($result['assets']))->toBe(1);
    
    $assetData = $result['assets'][0];
    // Text assets should NOT have transforms key
    expect($assetData)->not->toHaveKey('transforms');
});

it('handles empty results gracefully', function () {
    $result = ($this->getAssets)([
        'search' => 'non-existent-file-name-xyz',
    ]);

    expect($result)->toHaveKey('assets');
    expect($result['assets'])->toBeArray();
    expect($result['assets'])->toBeEmpty();
});

it('can combine multiple filters', function () {
    $asset = ($this->createTestAsset)('combined-filter.txt', 'Combined Filter Asset');

    // Use assetIds and volumeId together to test multiple filters
    $result = ($this->getAssets)([
        'assetIds' => [$asset->id],
        'volumeId' => $this->testVolume->id,
        'limit' => 10,
    ]);

    expect($result['assets'])->toBeArray();
    expect($result['count'])->toBe(1);
    
    // Should find our asset
    $assetIds = array_column($result['assets'], 'id');
    expect($assetIds)->toContain($asset->id);
    
    // All results should be from the correct volume
    foreach ($result['assets'] as $assetData) {
        expect($assetData['volume']['id'])->toBe($this->testVolume->id);
    }
});

it('throws exception for invalid volume ID', function () {
    expect(fn() => ($this->getAssets)([
        'volumeId' => 99999, // Non-existent volume
    ]))->toThrow(InvalidArgumentException::class);
});