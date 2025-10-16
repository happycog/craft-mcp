<?php

use happycog\craftmcp\tools\UpdateAsset;
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
            $defaults['title'] = 'Test Asset for Update';
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

it('can update asset title', function () {
    // Create asset first
    $asset = ($this->createTestAsset)();
    $assetId = $asset['assetId'];

    // Update the title
    $result = Craft::$container->get(UpdateAsset::class)->update(
        assetId: $assetId,
        title: 'Updated Asset Title'
    );

    expect($result)->toHaveKeys(['_notes', 'assetId', 'filename', 'title', 'url', 'size', 'editUrl']);
    expect($result['assetId'])->toBe($assetId);
    expect($result['title'])->toBe('Updated Asset Title');
    expect($result['_notes'])->toContain('successfully updated');

    // Verify in database
    $updatedAsset = Asset::find()->id($assetId)->one();
    expect($updatedAsset)->toBeInstanceOf(Asset::class);
    expect($updatedAsset->title)->toBe('Updated Asset Title');
});

it('can update asset custom fields', function () {
    // Create asset first
    $asset = ($this->createTestAsset)();
    $assetId = $asset['assetId'];

    // Update with field data (empty since test volume has no custom fields)
    $result = Craft::$container->get(UpdateAsset::class)->update(
        assetId: $assetId,
        fieldData: []
    );

    expect($result['assetId'])->toBe($assetId);
    
    // Verify asset exists and was updated
    $updatedAsset = Asset::find()->id($assetId)->one();
    expect($updatedAsset)->toBeInstanceOf(Asset::class);
});

it('can update asset title and fields together', function () {
    // Create asset first
    $asset = ($this->createTestAsset)();
    $assetId = $asset['assetId'];

    // Update both title and fields
    $result = Craft::$container->get(UpdateAsset::class)->update(
        assetId: $assetId,
        title: 'Complete Update',
        fieldData: []
    );

    expect($result['assetId'])->toBe($assetId);
    expect($result['title'])->toBe('Complete Update');

    // Verify in database
    $updatedAsset = Asset::find()->id($assetId)->one();
    expect($updatedAsset)->toBeInstanceOf(Asset::class);
    expect($updatedAsset->title)->toBe('Complete Update');
});

it('can replace asset file from local path', function () {
    // Create initial asset
    $initialFile = ($this->createTestFile)('Initial content', 'initial.txt');
    $this->tempFiles[] = $initialFile;
    
    $asset = ($this->createTestAsset)([
        'filePath' => $initialFile,
        'title' => 'File Replacement Test'
    ]);
    $assetId = $asset['assetId'];

    // Create replacement file
    $replacementFile = ($this->createTestFile)('Replacement content', 'replacement.txt');
    $this->tempFiles[] = $replacementFile;

    // Update with file replacement
    $result = Craft::$container->get(UpdateAsset::class)->update(
        assetId: $assetId,
        replaceFilePath: $replacementFile
    );

    expect($result['assetId'])->toBe($assetId);
    expect($result['filename'])->toContain('replacement'); // Craft may append timestamp to avoid conflicts
    expect($result['size'])->toBeGreaterThan(0);

    // Verify asset was updated
    $updatedAsset = Asset::find()->id($assetId)->one();
    expect($updatedAsset)->toBeInstanceOf(Asset::class);
    expect($updatedAsset->filename)->toContain('replacement');
});

it('can update title and replace file simultaneously', function () {
    // Create initial asset
    $initialFile = ($this->createTestFile)('Initial content', 'initial.txt');
    $this->tempFiles[] = $initialFile;
    
    $asset = ($this->createTestAsset)([
        'filePath' => $initialFile,
    ]);
    $assetId = $asset['assetId'];

    // Create replacement file
    $replacementFile = ($this->createTestFile)('New content', 'new-file.txt');
    $this->tempFiles[] = $replacementFile;

    // Update both title and file
    $result = Craft::$container->get(UpdateAsset::class)->update(
        assetId: $assetId,
        title: 'Updated with New File',
        replaceFilePath: $replacementFile
    );

    expect($result['assetId'])->toBe($assetId);
    expect($result['title'])->toBe('Updated with New File');
    expect($result['filename'])->toContain('new-file'); // Craft may append timestamp to avoid conflicts

    // Verify in database
    $updatedAsset = Asset::find()->id($assetId)->one();
    expect($updatedAsset)->toBeInstanceOf(Asset::class);
    expect($updatedAsset->title)->toBe('Updated with New File');
    expect($updatedAsset->filename)->toContain('new-file');
});

it('throws exception for non-existent asset ID', function () {
    expect(fn() => Craft::$container->get(UpdateAsset::class)->update(
        assetId: 99999, // Non-existent asset
        title: 'Should fail'
    ))->toThrow(InvalidArgumentException::class);
});

it('throws exception for non-existent replacement file', function () {
    // Create asset first
    $asset = ($this->createTestAsset)();
    
    expect(fn() => Craft::$container->get(UpdateAsset::class)->update(
        assetId: $asset['assetId'],
        replaceFilePath: '/non/existent/file.txt'
    ))->toThrow(InvalidArgumentException::class);
});

it('throws exception when both replaceFileUrl and replaceFilePath are provided', function () {
    // Create asset first
    $asset = ($this->createTestAsset)();
    
    expect(fn() => Craft::$container->get(UpdateAsset::class)->update(
        assetId: $asset['assetId'],
        replaceFileUrl: 'http://example.com/file.txt',
        replaceFilePath: '/some/local/file.txt'
    ))->toThrow(RuntimeException::class);
});

it('includes all required fields in response', function () {
    // Create asset first
    $asset = ($this->createTestAsset)();
    $assetId = $asset['assetId'];

    // Simple title update
    $result = Craft::$container->get(UpdateAsset::class)->update(
        assetId: $assetId,
        title: 'Response Test'
    );

    // Verify all required response fields
    expect($result)->toHaveKeys(['_notes', 'assetId', 'filename', 'title', 'url', 'size', 'editUrl']);
    expect($result['assetId'])->toBe($assetId);
    expect($result['title'])->toBe('Response Test');
    expect($result['url'])->toBeString();
    expect($result['size'])->toBeInt();
    expect($result['dateUpdated'])->toBeString(); // ISO 8601 format
});

it('includes control panel edit URL in response', function () {
    // Create asset first
    $asset = ($this->createTestAsset)();
    
    $result = Craft::$container->get(UpdateAsset::class)->update(
        assetId: $asset['assetId'],
        title: 'Edit URL Test'
    );

    expect($result)->toHaveKey('editUrl');
    expect($result['editUrl'])->toContain('/edit/');
    expect($result['editUrl'])->toContain((string)$result['assetId']);
});

it('handles empty field data gracefully', function () {
    // Create asset first
    $asset = ($this->createTestAsset)();
    
    $result = Craft::$container->get(UpdateAsset::class)->update(
        assetId: $asset['assetId'],
        fieldData: [] // Empty array should work fine
    );

    expect($result['assetId'])->toBe($asset['assetId']);
    expect($result)->toHaveKey('_notes');
});

it('preserves existing values when updating selectively', function () {
    // Create asset with initial title
    $asset = ($this->createTestAsset)([
        'title' => 'Original Title'
    ]);
    $assetId = $asset['assetId'];
    $originalFilename = $asset['filename'];

    // Update only field data, should preserve title and filename
    $result = Craft::$container->get(UpdateAsset::class)->update(
        assetId: $assetId,
        fieldData: []
    );

    expect($result['assetId'])->toBe($assetId);
    expect($result['title'])->toBe('Original Title'); // Should be preserved
    expect($result['filename'])->toBe($originalFilename); // Should be preserved

    // Verify in database
    $updatedAsset = Asset::find()->id($assetId)->one();
    expect($updatedAsset)->toBeInstanceOf(Asset::class);
    expect($updatedAsset->title)->toBe('Original Title');
});