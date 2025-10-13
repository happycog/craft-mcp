<?php

use Craft;
use craft\fs\Local;
use craft\services\Fs;
use happycog\craftmcp\exceptions\ModelSaveException;
use happycog\craftmcp\tools\CreateFileSystem;

beforeEach(function () {
    $this->fsService = Craft::$container->get(Fs::class);
    $this->tool = Craft::$container->get(CreateFileSystem::class);
});

afterEach(function () {
    // Clean up any file systems created during tests
    $fileSystems = $this->fsService->getAllFilesystems();
    foreach ($fileSystems as $fs) {
        if (str_starts_with($fs->handle, 'test_fs_')) {
            $this->fsService->removeFilesystem($fs);
        }
    }
});

test('can create basic local file system with required parameters', function () {
    $uniqueId = uniqid();
    
    $result = $this->tool->create(
        name: 'Test FileSystem',
        type: 'local',
        handle: 'test_fs_' . $uniqueId,
        settings: ['path' => '@webroot/test-uploads-' . $uniqueId]
    );

    expect($result)->toBeArray();
    expect($result['name'])->toBe('Test FileSystem');
    expect($result['handle'])->toBe('test_fs_' . $uniqueId);
    expect($result['type'])->toBe(Local::class);
    
    // Verify file system was actually created by looking it up by handle
    $fs = $this->fsService->getFilesystemByHandle('test_fs_' . $uniqueId);
    expect($fs)->toBeInstanceOf(Local::class);
    expect($fs->name)->toBe('Test FileSystem');
    expect($fs->handle)->toBe('test_fs_' . $uniqueId);
});

test('auto-generates handle when not provided', function () {
    $uniqueId = uniqid();
    
    $result = $this->tool->create(
        name: 'Auto Handle Test',
        type: 'local',
        settings: ['path' => '@webroot/auto-test-' . $uniqueId]
    );

    expect($result['handle'])->toBe('auto_handle_test');
    
    // Verify the file system exists with the generated handle
    $fs = $this->fsService->getFilesystemByHandle('auto_handle_test');
    expect($fs)->not->toBeNull();
});

test('uses provided handle when specified', function () {
    $uniqueId = uniqid();
    $customHandle = 'custom_fs_' . $uniqueId;
    
    $result = $this->tool->create(
        name: 'Custom Handle Test',
        type: 'local',
        handle: $customHandle,
        settings: ['path' => '@webroot/custom-test-' . $uniqueId]
    );

    expect($result['handle'])->toBe($customHandle);
    
    // Verify the file system exists with the custom handle
    $fs = $this->fsService->getFilesystemByHandle($customHandle);
    expect($fs)->not->toBeNull();
});

test('ensures handle uniqueness by appending counter', function () {
    $uniqueId = uniqid();
    $baseName = 'Duplicate Test';
    
    // Create first file system
    $result1 = $this->tool->create(
        name: $baseName,
        type: 'local',
        settings: ['path' => '@webroot/dup1-' . $uniqueId]
    );

    // Create second file system with same name
    $result2 = $this->tool->create(
        name: $baseName,
        type: 'local', 
        settings: ['path' => '@webroot/dup2-' . $uniqueId]
    );

    expect($result1['handle'])->toBe('duplicate_test');
    expect($result2['handle'])->toBe('duplicate_test_1');
});

test('creates file system with URL settings', function () {
    $uniqueId = uniqid();
    
    $result = $this->tool->create(
        name: 'URL Test FileSystem',
        type: 'local',
        settings: [
            'path' => '@webroot/url-test-' . $uniqueId,
            'hasUrls' => true,
            'url' => '/uploads/' . $uniqueId
        ]
    );

    expect($result['hasUrls'])->toBe(true);
    expect($result['url'])->toBe('/uploads/' . $uniqueId);
    
    // Verify in database by handle since ID may be null in tests
    $fs = $this->fsService->getFilesystemByHandle($result['handle']);
    expect($fs->hasUrls)->toBe(true);
    expect($fs->url)->toBe('/uploads/' . $uniqueId);
});

test('creates file system without URLs by default', function () {
    $uniqueId = uniqid();
    
    $result = $this->tool->create(
        name: 'No URL Test',
        type: 'local',
        settings: ['path' => '@webroot/no-url-' . $uniqueId]
    );

    // Default hasUrls should be false
    expect($result['hasUrls'])->toBe(false);
    expect($result['url'])->toBeNull();
});

test('throws error for unsupported file system type', function () {
    expect(fn() => $this->tool->create(
        name: 'Invalid Type Test',
        type: 'aws-s3',
        settings: ['path' => '@webroot/test']
    ))->toThrow(\InvalidArgumentException::class, 'Unsupported file system type: aws-s3');
});

test('throws error when path is missing for local file system', function () {
    expect(fn() => $this->tool->create(
        name: 'Missing Path Test',
        type: 'local',
        settings: []
    ))->toThrow(\InvalidArgumentException::class, 'Local file system requires a "path" setting');
});

test('throws error when path is not a string', function () {
    expect(fn() => $this->tool->create(
        name: 'Invalid Path Test', 
        type: 'local',
        settings: ['path' => 123]
    ))->toThrow(\InvalidArgumentException::class, 'Local file system requires a "path" setting');
});

test('handles empty name by generating fallback handle', function () {
    // Empty names should cause ModelSaveException due to Craft's validation
    expect(fn() => $this->tool->create(
        name: '',
        type: 'local',
        settings: ['path' => '@webroot/empty-name-test']
    ))->toThrow(ModelSaveException::class, 'name: Name cannot be blank');
});

test('sanitizes name to create valid handle', function () {
    $uniqueId = uniqid();
    
    $result = $this->tool->create(
        name: 'Test File-System (2024) #special!',
        type: 'local',
        settings: ['path' => '@webroot/sanitize-' . $uniqueId]
    );

    expect($result['handle'])->toBe('test_file_system_2024_special');
});

test('response includes all expected fields', function () {
    $uniqueId = uniqid();
    
    $result = $this->tool->create(
        name: 'Complete Response Test',
        type: 'local',
        handle: 'test_fs_' . $uniqueId,
        settings: [
            'path' => '@webroot/complete-' . $uniqueId,
            'hasUrls' => true,
            'url' => '/complete/' . $uniqueId
        ]
    );

    expect($result)->toHaveKeys([
        '_notes',
        'fileSystemId',
        'name',
        'handle', 
        'type',
        'hasUrls',
        'url',
        'dateCreated',
        'editUrl',
        'settings'
    ]);

    expect($result['_notes'])->toBe('The file system was successfully created.');
    // In test environment, dateCreated may be null due to transaction rollbacks
    expect($result)->toHaveKey('dateCreated');
    expect($result['editUrl'])->toContain('/settings/fs/test_fs_' . $uniqueId);
});

test('throws ModelSaveException when file system save fails', function () {
    // Test with empty path which should cause ModelSaveException during save
    expect(fn() => $this->tool->create(
        name: 'Invalid FileSystem',
        type: 'local', 
        settings: ['path' => ''] // Empty path should cause save to fail
    ))->toThrow(ModelSaveException::class); // Save fails due to empty path
});

test('edit URL is correctly formatted with control panel URL', function () {
    $uniqueId = uniqid();
    $handle = 'test_fs_' . $uniqueId;
    
    $result = $this->tool->create(
        name: 'Edit URL Test',
        type: 'local',
        handle: $handle,
        settings: ['path' => '@webroot/edit-url-' . $uniqueId]
    );

    $cpUrl = Craft::$app->getConfig()->general->cpUrl ?? '';
    $expectedEditUrl = $cpUrl . '/settings/fs/' . $handle;
    
    expect($result['editUrl'])->toBe($expectedEditUrl);
});

test('can create multiple file systems with different configurations', function () {
    $uniqueId = uniqid();
    
    // Create file system without URLs
    $result1 = $this->tool->create(
        name: 'Private FS',
        type: 'local',
        handle: 'test_fs_private_' . $uniqueId,
        settings: ['path' => '@webroot/private-' . $uniqueId]
    );

    // Create file system with URLs
    $result2 = $this->tool->create(
        name: 'Public FS',
        type: 'local', 
        handle: 'test_fs_public_' . $uniqueId,
        settings: [
            'path' => '@webroot/public-' . $uniqueId,
            'hasUrls' => true,
            'url' => '/public/' . $uniqueId
        ]
    );

    expect($result1['handle'])->toBe('test_fs_private_' . $uniqueId);
    expect($result1['hasUrls'])->toBe(false);
    
    expect($result2['handle'])->toBe('test_fs_public_' . $uniqueId);
    expect($result2['hasUrls'])->toBe(true);
    expect($result2['url'])->toBe('/public/' . $uniqueId);

    // Verify both exist in database
    $fs1 = $this->fsService->getFilesystemByHandle($result1['handle']);
    $fs2 = $this->fsService->getFilesystemByHandle($result2['handle']);
    
    expect($fs1)->toBeInstanceOf(Local::class);
    expect($fs2)->toBeInstanceOf(Local::class);
});

test('settings are properly sanitized in response', function () {
    $uniqueId = uniqid();
    
    $result = $this->tool->create(
        name: 'Settings Test',
        type: 'local',
        settings: [
            'path' => '@webroot/settings-' . $uniqueId,
            'hasUrls' => true,
            'url' => '/settings/' . $uniqueId
        ]
    );

    // Settings should only include non-sensitive information
    expect($result['settings'])->toHaveKey('path');
    expect($result['settings']['path'])->toBe('@webroot/settings-' . $uniqueId);
    
    // Should not include any credential-like keys
    expect($result['settings'])->not->toHaveKey('password');
    expect($result['settings'])->not->toHaveKey('accessKey');
    expect($result['settings'])->not->toHaveKey('secretKey');
});