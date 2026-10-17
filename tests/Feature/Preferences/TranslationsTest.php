<?php

/**
 * @return list<string>
 */
function translationKeys(array $values, string $prefix = ''): array
{
    $keys = [];

    foreach ($values as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        $keys = [...$keys, ...(is_array($value) ? translationKeys($value, $path) : [$path])];
    }

    sort($keys);

    return $keys;
}

it('has the same keys in english and arabic', function (string $file) {
    $english = require lang_path("en/{$file}.php");
    $arabic = require lang_path("ar/{$file}.php");

    expect(translationKeys($arabic))->toBe(translationKeys($english));
})->with(['panel', 'roles', 'audit']);
