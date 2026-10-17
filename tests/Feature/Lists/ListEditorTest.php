<?php

use App\Config\ConfigStore;
use App\Enums\Filter\ConfigArea;
use App\Lists\InvalidListException;
use App\Lists\ListChangedException;
use App\Lists\ListEditor;
use App\Lists\ListName;
use App\Lists\SaveResult;
use App\Models\AuditLog;
use App\Models\Filter\FilterConfigVersion;
use App\Models\Filter\FilterListEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(fn () => $this->signIn(User::factory()->admin()->withTwoFactor()->create()));

function listsVersion(): int
{
    return (int) FilterConfigVersion::query()->whereKey(ConfigArea::Lists->value)->value('version');
}

function saveList(ListName $list, string $text): SaveResult
{
    $editor = app(ListEditor::class);

    return $editor->save($list, $text, $editor->fingerprint($list));
}

it('saves entries and shows them back as text', function () {
    $result = saveList(ListName::BlockedIps, "1.2.3.4 // office\n5.6.7.8");

    expect([$result->added, $result->removed, $result->updated])->toBe([2, 0, 0])
        ->and(app(ListEditor::class)->text(ListName::BlockedIps))->toBe("1.2.3.4 // office\n5.6.7.8")
        ->and(AuditLog::query()->where('action', 'lists.created')->count())->toBe(2);
});

it('applies only the difference and signals the filter once', function () {
    saveList(ListName::BlockedWords, "hack\nbot // spam\ncheat");
    $before = listsVersion();
    AuditLog::query()->toBase()->delete();

    $result = saveList(ListName::BlockedWords, "HACK // case only\nbot // spam bots\nexploit");

    expect([$result->added, $result->removed, $result->updated])->toBe([1, 1, 2])
        ->and(FilterListEntry::query()->where('list', 'blocked_words')->pluck('value')->sort(SORT_FLAG_CASE | SORT_STRING)->values()->all())->toBe(['bot', 'exploit', 'HACK'])
        ->and(listsVersion())->toBe($before + 1)
        ->and(AuditLog::query()->count())->toBe(4);
});

it('writes nothing when the text did not change', function () {
    saveList(ListName::BlockedIps, '1.2.3.4');
    $before = listsVersion();
    $audits = AuditLog::query()->count();

    $result = saveList(ListName::BlockedIps, "1.2.3.4\n\n");

    expect([$result->added, $result->removed, $result->updated])->toBe([0, 0, 0])
        ->and(listsVersion())->toBe($before)
        ->and(AuditLog::query()->count())->toBe($audits);
});

it('saves nothing when any line is invalid', function () {
    expect(fn () => saveList(ListName::BlockedIps, "1.2.3.4\nnope"))->toThrow(InvalidListException::class);
    expect(FilterListEntry::count())->toBe(0);
});

it('refuses a save over changes made by someone else', function () {
    $editor = app(ListEditor::class);
    $stale = $editor->fingerprint(ListName::BlockedIps);
    saveList(ListName::BlockedIps, '9.9.9.9');

    expect(fn () => $editor->save(ListName::BlockedIps, '1.1.1.1', $stale))->toThrow(ListChangedException::class);
    expect(FilterListEntry::query()->pluck('value')->all())->toBe(['9.9.9.9']);
});

it('keeps lists separate', function () {
    saveList(ListName::BlockedIps, '1.2.3.4');
    saveList(ListName::AllowedIps, '1.2.3.4');
    saveList(ListName::BlockedIps, '');

    expect(FilterListEntry::query()->pluck('list')->all())->toBe(['allowed_ips']);
});

it('refuses viewers', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());

    expect(fn () => saveList(ListName::BlockedIps, '1.2.3.4'))->toThrow(AuthorizationException::class);
});

it('removes rows the parser would merge that were written elsewhere', function () {
    FilterListEntry::query()->create(['list' => 'blocked_words', 'value' => 'Hack']);
    FilterListEntry::query()->create(['list' => 'blocked_words', 'value' => 'hack']);

    $result = saveList(ListName::BlockedWords, 'Hack');

    expect($result->removed)->toBe(1)
        ->and(FilterListEntry::query()->pluck('value')->all())->toBe(['Hack']);
});

it('reports a database duplicate as a list error instead of failing', function () {
    $this->mock(ConfigStore::class, function ($mock): void {
        $mock->shouldReceive('batch')->andThrow(new UniqueConstraintViolationException('filter', 'insert', [], new Exception('dup')));
    });

    expect(fn () => saveList(ListName::BlockedWords, 'hack'))->toThrow(InvalidListException::class, __('panel.lists.errors.collation'));
});
