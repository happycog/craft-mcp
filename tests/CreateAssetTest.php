<?php

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

    $this->createAsset = function (array $params = []): array {
        $defaults = [
            'volumeId' => $this->testVolume->id,
            'filePath' => ($this->createTestFile)(),
        ];
        
        // Only set title as default if not explicitly passed (including null)
        if (!array_key_exists('title', $params)) {
            $defaults['title'] = 'Test Asset';
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

it('can create an asset from file path', function () {
    $tempFile = ($this->createTestFile)('Hello World', 'hello.txt');
    
    $result = ($this->createAsset)([
        'filePath' => $tempFile,
        'title' => 'Hello Asset',
    ]);

    expect($result)->toHaveKeys(['_notes', 'assetId', 'filename', 'title', 'url', 'size']);
    expect($result['title'])->toBe('Hello Asset');
    expect($result['filename'])->toBe('hello.txt');
    expect($result['size'])->toBeGreaterThan(0);

    // Verify asset exists in database
    $asset = Asset::find()->id($result['assetId'])->one();
    expect($asset)->toBeInstanceOf(Asset::class);
    expect($asset->title)->toBe('Hello Asset');
    
    // Clean up
    unlink($tempFile);
});

it('auto-generates filename from file path when not provided', function () {
    $tempFile = ($this->createTestFile)('Content', 'auto-name.txt');
    
    $result = ($this->createAsset)([
        'filePath' => $tempFile,
        'title' => 'Auto Named Asset',
    ]);

    expect($result['filename'])->toBe('auto-name.txt');
    
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});

it('auto-generates title from filename when not provided', function () {
    $tempFile = ($this->createTestFile)('Content', 'my-document.pdf');
    
    $result = ($this->createAsset)([
        'filePath' => $tempFile,
        'title' => null, // Explicitly pass null to trigger auto-generation
    ]);

    expect($result['title'])->toBe('my-document'); // Should strip extension
    expect($result['filename'])->toBe('my-document.pdf');
    
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});

it('can set empty field data without errors', function () {
    $tempFile = ($this->createTestFile)('Content', 'field-test.txt');
    
    $result = ($this->createAsset)([
        'filePath' => $tempFile,
        'title' => 'Field Test Asset',
        'fieldData' => [], // Empty field data should work without issues
    ]);

    expect($result['assetId'])->toBeInt();
    expect($result['title'])->toBe('Field Test Asset');
    
    $asset = Asset::find()->id($result['assetId'])->one();
    expect($asset)->toBeInstanceOf(Asset::class);
    expect($asset->title)->toBe('Field Test Asset');
    
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});

it('throws exception for non-existent file path', function () {
    expect(fn() => ($this->createAsset)([
        'filePath' => '/non/existent/file.txt',
    ]))->toThrow(InvalidArgumentException::class);
});

it('throws exception for invalid volume ID', function () {
    $tempFile = ($this->createTestFile)();
    
    expect(fn() => ($this->createAsset)([
        'volumeId' => 99999, // Non-existent volume
        'filePath' => $tempFile,
    ]))->toThrow(InvalidArgumentException::class);
    
    unlink($tempFile);
});

it('handles different file types correctly', function () {
    // Test with different non-image file extensions that don't require special validation
    $files = [
        'document.txt' => 'Text document content',
        'data.json' => '{"key": "value"}',
        'config.xml' => '<?xml version="1.0"?><root></root>',
    ];

    foreach ($files as $filename => $content) {
        $tempFile = ($this->createTestFile)($content, $filename);
        
        $result = ($this->createAsset)([
            'filePath' => $tempFile,
            'title' => "Test {$filename}",
        ]);

        expect($result['filename'])->toBe($filename);
        expect($result['title'])->toBe("Test {$filename}");
        
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
});

it('includes control panel edit URL in response', function () {
    $tempFile = ($this->createTestFile)();
    
    $result = ($this->createAsset)([
        'filePath' => $tempFile,
    ]);

    expect($result)->toHaveKey('editUrl');
    expect($result['editUrl'])->toContain('/edit/');
    expect($result['editUrl'])->toContain((string)$result['assetId']);
    
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});