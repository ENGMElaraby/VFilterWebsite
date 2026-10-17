<?php

use App\Enums\Filter\SettingType;

it('casts and serializes each type', function (SettingType $type, mixed $value, string $stored) {
    expect($type->serialize($value))->toBe($stored)
        ->and($type->cast($stored))->toBe($value);
})->with([
    'bool true' => [SettingType::Bool, true, '1'],
    'bool false' => [SettingType::Bool, false, '0'],
    'int' => [SettingType::Int, 42, '42'],
    'string' => [SettingType::String, 'مرحبا', 'مرحبا'],
    'json' => [SettingType::Json, ['a' => [1, 2]], '{"a":[1,2]}'],
    'color' => [SettingType::Color, '#FF8800', '#FF8800'],
]);

it('validates values', function (SettingType $type, mixed $value, bool $valid) {
    expect($type->isValid($value))->toBe($valid);
})->with([
    [SettingType::Bool, true, true], [SettingType::Bool, 'true', false], [SettingType::Bool, 1, false],
    [SettingType::Int, 5, true], [SettingType::Int, '5', false], [SettingType::Int, 5.5, false],
    [SettingType::String, 'ok', true], [SettingType::String, str_repeat('x', 4001), false], [SettingType::String, 5, false],
    [SettingType::Json, ['x' => 1], true], [SettingType::Json, null, true], [SettingType::Json, new stdClass, false],
    [SettingType::Color, '#a1b2c3', true], [SettingType::Color, '#A1B2C3FF', true], [SettingType::Color, 'red', false], [SettingType::Color, '#12345', false],
]);
