<?php

use craft\models\Volume;
use happycog\craftmcp\tools\GetVolumes;

beforeEach(function () {
    $this->getVolumes = Craft::$container->get(GetVolumes::class);
    
    // Use existing test volume from the system
    $this->testVolume = Craft::$app->getVolumes()->getAllVolumes()[0] ?? null;
    
    if (!$this->testVolume) {
        $this->fail('No test volume available. Please ensure at least one volume exists in the system.');
    }
});

it('can retrieve all volumes when no parameters provided', function () {
    $result = ($this->getVolumes)->get();

    expect($result)->toBeArray();
    expect($result)->not()->toBeEmpty();
    
    // Check that our test volume is in the results
    $volumeIds = array_column($result, 'id');
    expect($volumeIds)->toContain($this->testVolume->id);
    
    // Verify structure of first volume
    $firstVolume = $result[0];
    expect($firstVolume)->toHaveKeys([
        'id', 'name', 'handle', 'subpath', 'editUrl', 'fileSystem', 'assetCount', 'fieldLayout'
    ]);
    
    expect($firstVolume['fileSystem'])->toHaveKeys(['handle', 'name', 'type']);
    expect($firstVolume['fieldLayout'])->toHaveKeys(['id', 'customFields']);
});

it('can filter volumes by volume IDs', function () {
    $result = ($this->getVolumes)->get(volumeIds: [$this->testVolume->id]);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    
    $volumeData = $result[0];
    expect($volumeData['id'])->toBe($this->testVolume->id);
    expect($volumeData['name'])->toBe($this->testVolume->name);
    expect($volumeData['handle'])->toBe($this->testVolume->handle);
});

it('returns proper volume data structure', function () {
    $result = ($this->getVolumes)->get(volumeIds: [$this->testVolume->id]);
    $volumeData = $result[0];

    expect($volumeData)->toBeArray();
    
    // Check required fields
    expect($volumeData['id'])->toBeInt();
    expect($volumeData['name'])->toBeString();
    expect($volumeData['handle'])->toBeString();
    expect($volumeData['subpath'])->toBeString();
    expect($volumeData['editUrl'])->toBeString();
    expect($volumeData['assetCount'])->toBeInt();
    
    // Check file system structure
    expect($volumeData['fileSystem'])->toBeArray();
    expect($volumeData['fileSystem']['handle'])->toBeString();
    expect($volumeData['fileSystem']['name'])->toBeString();
    expect($volumeData['fileSystem']['type'])->toBeString();
    
    // Check field layout structure
    expect($volumeData['fieldLayout'])->toBeArray();
    expect($volumeData['fieldLayout']['id'])->toBeInt();
    expect($volumeData['fieldLayout']['customFields'])->toBeArray();
});

it('includes control panel edit URL', function () {
    $result = ($this->getVolumes)->get(volumeIds: [$this->testVolume->id]);
    $volumeData = $result[0];

    expect($volumeData['editUrl'])->toContain('/settings/assets/volumes/');
    expect($volumeData['editUrl'])->toContain((string) $this->testVolume->id);
});

it('handles empty results gracefully', function () {
    $result = ($this->getVolumes)->get(volumeIds: [99999]); // Non-existent volume ID

    expect($result)->toBeArray();
    expect($result)->toBeEmpty();
});

it('returns asset count for each volume', function () {
    $result = ($this->getVolumes)->get(volumeIds: [$this->testVolume->id]);
    $volumeData = $result[0];

    expect($volumeData['assetCount'])->toBeInt();
    expect($volumeData['assetCount'])->toBeGreaterThanOrEqual(0);
});

it('includes field layout information', function () {
    $result = ($this->getVolumes)->get(volumeIds: [$this->testVolume->id]);
    $volumeData = $result[0];

    expect($volumeData['fieldLayout'])->toBeArray();
    expect($volumeData['fieldLayout']['id'])->toBeInt();
    expect($volumeData['fieldLayout']['customFields'])->toBeArray();
    
    // Each custom field should have proper structure
    foreach ($volumeData['fieldLayout']['customFields'] as $field) {
        expect($field)->toHaveKeys(['id', 'name', 'handle', 'type', 'required']);
        expect($field['id'])->toBeInt();
        expect($field['name'])->toBeString();
        expect($field['handle'])->toBeString();
        expect($field['type'])->toBeString();
        expect($field['required'])->toBeBool();
    }
});

it('can filter multiple volumes by IDs', function () {
    $allVolumes = ($this->getVolumes)->get();
    
    if (count($allVolumes) < 2) {
        $this->markTestSkipped('Need at least 2 volumes for multiple volume test');
    }
    
    $firstTwoVolumeIds = array_slice(array_column($allVolumes, 'id'), 0, 2);
    $result = ($this->getVolumes)->get(volumeIds: $firstTwoVolumeIds);

    expect($result)->toHaveCount(2);
    
    $resultIds = array_column($result, 'id');
    expect($resultIds)->toBe($firstTwoVolumeIds);
});

it('returns volumes in consistent order', function () {
    $result1 = ($this->getVolumes)->get();
    $result2 = ($this->getVolumes)->get();

    expect(array_column($result1, 'id'))->toBe(array_column($result2, 'id'));
});