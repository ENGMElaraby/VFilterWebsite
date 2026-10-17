<?php

use App\Lists\ListName;
use App\Lists\ListParser;
use App\Lists\ParsedList;

function parseList(ListName $list, string $text): ParsedList
{
    return app(ListParser::class)->parse($list, $text);
}

/**
 * @return array<string, string|null> value => comment
 */
function parsedValues(ParsedList $parsed): array
{
    return collect($parsed->entries)->mapWithKeys(fn (array $e): array => [$e['value'] => $e['comment']])->all();
}

it('reads one value per line with optional comments', function () {
    $parsed = parseList(ListName::BlockedIps, "1.2.3.4 // office\r\n\n  5.6.7.8  \n// only a note\n9.9.9.9//\n");

    expect($parsed->errors)->toBe([])
        ->and(parsedValues($parsed))->toBe(['1.2.3.4' => 'office', '5.6.7.8' => null, '9.9.9.9' => null]);
});

it('merges duplicates without regard to letter case', function () {
    $parsed = parseList(ListName::BlockedWords, "Hack\nhack // again\nبوت\n");

    expect(parsedValues($parsed))->toBe(['Hack' => null, 'بوت' => null])->and($parsed->duplicates)->toBe(1);
});

it('normalises CIDR ranges and reports it', function (string $line, string $stored) {
    $parsed = parseList(ListName::BlockedIps, $line);

    expect($parsed->errors)->toBe([])->and(array_keys(parsedValues($parsed)))->toBe([$stored]);
})->with([
    ['1.2.3.4/24', '1.2.3.0/24'],
    ['10.0.0.0/8', '10.0.0.0/8'],
    ['2001:DB8::1/64', '2001:db8::/64'],
    ['2001:DB8::1', '2001:db8::1'],
]);

it('reports bad values by line number', function (ListName $list, string $text, int $line) {
    $parsed = parseList($list, $text);

    expect($parsed->errors)->toHaveCount(1)->and($parsed->errors[0]['line'])->toBe($line);
})->with([
    'bad ip' => [ListName::BlockedIps, "1.2.3.4\n\n1.2.3.999", 3],
    'prefix too wide' => [ListName::BlockedIps, '1.0.0.0/7', 1],
    'ipv6 prefix too wide' => [ListName::AllowedIps, '2001:db8::/31', 1],
    'cidr in cafe list' => [ListName::NetCafeIps, "5.5.5.5\n5.5.5.0/24", 2],
    'bad username' => [ListName::ProxyBypassUsernames, 'ok_name\nbad name', 1],
    'long username' => [ListName::ProxyBypassUsernames, str_repeat('a', 33), 1],
    'hwid with space' => [ListName::BlockedHwids, 'AB CD', 1],
    'long word' => [ListName::BlockedWords, str_repeat('x', 65), 1],
    'long comment' => [ListName::BlockedIps, '1.1.1.1 // '.str_repeat('c', 257), 1],
]);

it('refuses more than 10,000 entries', function () {
    $text = implode("\n", array_map(fn (int $i): string => 'word'.$i, range(1, 10_001)));

    expect(parseList(ListName::BlockedWords, $text)->errors[0]['line'])->toBe(0);
});
