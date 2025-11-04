<?php

use happycog\craftmcp\tools\DeleteAsset;
use happycog\craftmcp\tools\CreateAsset;
use craft\elements\Asset;
use craft\models\Volume;

beforeEach(function () {
    // Set up test volume and file system for asset tests
    $this->testVolume = Craft::$app->getVolumes()->getAllVolumes()[0] ?? null;
    
    if (!$this->testVolume) {
        $this->markTestSkipped('No volume available for asset testing');
    }

    $this->createTestFile = function (string $content = 'test content', string $filename = 'test.txt'): string {
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/' . $filename;
        file_put_contents($tempFile, $content);
        return $tempFile;
    };

    $this->createTestAsset = function (array $params = []): array {
        $defaults = [
            'volumeId' => $this->testVolume->id,
            'filePath' => ($this->createTestFile)(),
        ];
        
        // Only set title as default if not explicitly passed (including null)
        if (!array_key_exists('title', $params)) {
            $defaults['title'] = 'Test Asset for Deletion';
        }

        $merged = array_merge($defaults, $params);
        return Craft::$container->get(CreateAsset::class)->create(
            volumeId: $merged['volumeId'],
            filePath: $merged['filePath'] ?? null,
            url: $merged['url'] ?? null,
            filename: $merged['filename'] ?? null,
            title: $merged['title'] ?? null,
            fieldData: $merged['fieldData'] ?? []
        );
    };

    $this->tempFiles = [];
});

afterEach(function () {
    // Clean up temp files
    if (isset($this->tempFiles)) {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
});

it('can delete an asset successfully', function () {
    // Create asset first
    $tempFile = ($this->createTestFile)('Delete me', 'delete-test.txt');
    $this->tempFiles[] = $tempFile;
    
    $asset = ($this->createTestAsset)([
        'filePath' => $tempFile,
        'title' => 'Asset to Delete'
    ]);
    $assetId = $asset['assetId'];

    // Verify asset exists before deletion
    $existingAsset = Asset::find()->id($assetId)->one();
    expect($existingAsset)->toBeInstanceOf(Asset::class);
    expect($existingAsset->title)->toBe('Asset to Delete');

    // Delete the asset
    $result = Craft::$container->get(DeleteAsset::class)->delete(assetId: $assetId);

    // Verify response
    expect($result)->toHaveKeys(['_notes', 'deletedAssetId', 'filename', 'title']);
    expect($result['deletedAssetId'])->toBe($assetId);
    expect($result['filename'])->toBe('delete-test.txt');
    expect($result['title'])->toBe('Asset to Delete');
    expect($result['_notes'])->toContain('successfully deleted');

    // Verify asset no longer exists in database
    $deletedAsset = Asset::find()->id($assetId)->one();
    expect($deletedAsset)->toBeNull();
});

it('throws exception for non-existent asset ID', function () {
    expect(fn() => Craft::$container->get(DeleteAsset::class)->delete(
        assetId: 99999 // Non-existent asset
    ))->toThrow(InvalidArgumentException::class);
});

it('preserves asset information in response', function () {
    // Create asset with specific properties
    $tempFile = ($this->createTestFile)('Preserve info test', 'preserve.txt');
    $this->tempFiles[] = $tempFile;
    
    $asset = ($this->createTestAsset)([
        'filePath' => $tempFile,
        'title' => 'Asset With Info'
    ]);
    $assetId = $asset['assetId'];

    // Delete and check preserved info
    $result = Craft::$container->get(DeleteAsset::class)->delete(assetId: $assetId);

    expect($result['deletedAssetId'])->toBe($assetId);
    expect($result['filename'])->toBe('preserve.txt');
    expect($result['title'])->toBe('Asset With Info');
});

it('handles assets with custom field data', function () {
    // Create asset with field data
    $tempFile = ($this->createTestFile)('Field data test', 'field-data.txt');
    $this->tempFiles[] = $tempFile;
    
    $asset = ($this->createTestAsset)([
        'filePath' => $tempFile,
        'title' => 'Asset With Fields',
        'fieldData' => []
    ]);
    $assetId = $asset['assetId'];

    // Verify asset exists with field data
    $existingAsset = Asset::find()->id($assetId)->one();
    expect($existingAsset)->toBeInstanceOf(Asset::class);

    // Delete should succeed regardless of field data
    $result = Craft::$container->get(DeleteAsset::class)->delete(assetId: $assetId);

    expect($result['deletedAssetId'])->toBe($assetId);
    expect($result['title'])->toBe('Asset With Fields');

    // Verify deletion
    $deletedAsset = Asset::find()->id($assetId)->one();
    expect($deletedAsset)->toBeNull();
});

it('handles different file types correctly', function () {
    $fileTypes = [
        ['content' => 'PDF content', 'filename' => 'document.pdf', 'title' => 'PDF Document'],
        ['content' => 'Text data', 'filename' => 'text.txt', 'title' => 'Text File'],
        ['content' => '{"data": "value"}', 'filename' => 'data.json', 'title' => 'JSON Data'],
    ];

    foreach ($fileTypes as $fileData) {
        $tempFile = ($this->createTestFile)($fileData['content'], $fileData['filename']);
        $this->tempFiles[] = $tempFile;
        
        $asset = ($this->createTestAsset)([
            'filePath' => $tempFile,
            'title' => $fileData['title']
        ]);
        $assetId = $asset['assetId'];

        // Delete the asset
        $result = Craft::$container->get(DeleteAsset::class)->delete(assetId: $assetId);

        expect($result['deletedAssetId'])->toBe($assetId);
        expect($result['filename'])->toBe($fileData['filename']);
        expect($result['title'])->toBe($fileData['title']);

        // Verify deletion
        $deletedAsset = Asset::find()->id($assetId)->one();
        expect($deletedAsset)->toBeNull();
    }
});

it('returns proper response structure', function () {
    // Create simple asset
    $tempFile = ($this->createTestFile)();
    $this->tempFiles[] = $tempFile;
    
    $asset = ($this->createTestAsset)(['filePath' => $tempFile]);
    
    $result = Craft::$container->get(DeleteAsset::class)->delete(assetId: $asset['assetId']);

    // Verify response has all required keys
    expect($result)->toHaveKeys(['_notes', 'deletedAssetId', 'filename', 'title']);
    
    // Verify data types
    expect($result['deletedAssetId'])->toBeInt();
    expect($result['filename'])->toBeString();
    expect($result['title'])->toBeString();
    expect($result['_notes'])->toBeString();
    expect($result['_notes'])->toContain('successfully deleted');
});

it('handles deletion of asset without title gracefully', function () {
    // Create asset and then manually clear title to test edge case
    $tempFile = ($this->createTestFile)('No title test', 'no-title.txt');
    $this->tempFiles[] = $tempFile;
    
    $asset = ($this->createTestAsset)([
        'filePath' => $tempFile,
        'title' => '' // Empty title
    ]);
    $assetId = $asset['assetId'];

    // Delete should still work
    $result = Craft::$container->get(DeleteAsset::class)->delete(assetId: $assetId);

    expect($result['deletedAssetId'])->toBe($assetId);
    expect($result['filename'])->toBe('no-title.txt');
    expect($result['title'])->toBe('No title'); // Craft auto-generates title when empty

    // Verify deletion
    $deletedAsset = Asset::find()->id($assetId)->one();
    expect($deletedAsset)->toBeNull();
});

it('confirms asset is fully removed from system', function () {
    // Create asset
    $tempFile = ($this->createTestFile)('Full removal test', 'removal.txt');
    $this->tempFiles[] = $tempFile;
    
    $asset = ($this->createTestAsset)(['filePath' => $tempFile]);
    $assetId = $asset['assetId'];

    // Verify asset exists in multiple ways before deletion
    $existingAsset = Asset::find()->id($assetId)->one();
    expect($existingAsset)->toBeInstanceOf(Asset::class);

    $allAssets = Asset::find()->all();
    $assetIds = array_map(fn($a) => $a->id, $allAssets);
    expect($assetIds)->toContain($assetId);

    // Delete the asset
    Craft::$container->get(DeleteAsset::class)->delete(assetId: $assetId);

    // Verify complete removal
    expect(Asset::find()->id($assetId)->one())->toBeNull();
    
    $allAssetsAfter = Asset::find()->all();
    $assetIdsAfter = array_map(fn($a) => $a->id, $allAssetsAfter);
    expect($assetIdsAfter)->not->toContain($assetId);
});