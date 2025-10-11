<?php

declare(strict_types=1);

use happycog\craftmcp\tools\GetEntryTypes;

it('GetEntryTypes getAll returns a flat list of entry types with fields', function () {
    $tool = Craft::$container->get(GetEntryTypes::class);
    $result = $tool->getAll();

    expect($result)->toBeArray();
    expect(count($result))->toBeGreaterThan(0);

    $first = $result[0];
    expect($first)->toHaveKeys([
        'id', 'name', 'handle', 'hasTitleField', 'fieldLayoutId', 'uid', 'usage', 'section', 'editUrl', 'fields'
    ]);
    expect($first['fields'])->toBeArray();
});

it('GetEntryTypes getAll respects entryTypeIds filter', function () {
    $tool = Craft::$container->get(GetEntryTypes::class);
    $all = $tool->getAll();

    $firstId = $all[0]['id'] ?? null;
    if ($firstId) {
        $filtered = $tool->getAll([$firstId]);
        expect($filtered)->toHaveCount(1);
        expect($filtered[0]['id'])->toBe($firstId);
    }
});

it('GetEntryTypes entry type format includes usage and section data', function () {
    $tool = Craft::$container->get(GetEntryTypes::class);
    $all = $tool->getAll();

    $entryType = $all[0];
    expect($entryType['usage'])->toHaveKeys(['entries', 'drafts', 'total']);

    // Section may be null for standalone types; if present, ensure it has expected keys
    if ($entryType['section'] !== null) {
        expect($entryType['section'])->toHaveKeys(['id', 'name', 'handle', 'type']);
    }
});

it('GetEntryTypes fields include required layout context', function () {
    $tool = Craft::$container->get(GetEntryTypes::class);
    $all = $tool->getAll();

    // Find an entry type with fields
    foreach ($all as $et) {
        if (!empty($et['fields'])) {
            $field = $et['fields'][0];
            expect($field)->toHaveKeys(['id','handle','name','type','instructions','required']);
            break;
        }
    }
});
