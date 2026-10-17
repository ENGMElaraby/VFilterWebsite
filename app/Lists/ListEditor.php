<?php

namespace App\Lists;

use App\Config\ConfigStore;
use App\Models\Filter\FilterAreaRule;
use App\Models\Filter\FilterListEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Loads a list as "value // comment" text and saves only what changed, through ConfigStore
 * (audited per entry, one change signal per save). Spec 005, FR-001..FR-005.
 */
class ListEditor
{
    private const SEPARATOR = ' // ';

    public function __construct(
        private readonly ListParser $parser,
        private readonly ConfigStore $store,
    ) {}

    public function text(ListName $list): string
    {
        return $this->rows($list)
            ->map(fn (FilterListEntry $row): string => $row->comment === null ? $row->value : $row->value.self::SEPARATOR.$row->comment)
            ->implode("\n");
    }

    public function count(ListName $list): int
    {
        return FilterListEntry::query()->where('list', $list->value)->count();
    }

    public function fingerprint(ListName $list): string
    {
        return sha1($this->rows($list)->map(fn (FilterListEntry $row): string => $row->value."\0".$row->comment)->implode("\n"));
    }

    public function save(ListName $list, string $text, string $fingerprint): SaveResult
    {
        Gate::authorize('manage-filter-settings');

        $parsed = $this->parser->parse($list, $text);

        if (! $parsed->isValid()) {
            throw new InvalidListException($parsed);
        }

        return $this->store->batch(function (ConfigStore $store) use ($list, $parsed, $fingerprint): SaveResult {
            if ($this->fingerprint($list) !== $fingerprint) {
                throw new ListChangedException;
            }

            $current = $this->rows($list)->keyBy(fn (FilterListEntry $row): string => ListParser::key($list, $row->value));
            $removed = $current->diffKeys($parsed->entries);

            // Deletes first: a case-insensitive unique index must never see the old and new value together.
            foreach ($removed as $row) {
                $store->delete($row);
                $this->forgetCafeLimits($list, $row->value);
            }

            $updated = 0;
            $added = 0;

            foreach ($parsed->entries as $key => $entry) {
                $row = $current->get($key);

                if ($row === null) {
                    $store->create(FilterListEntry::class, ['list' => $list->value, 'value' => $entry['value'], 'comment' => $entry['comment']]);
                    $added++;
                } elseif ($row->value !== $entry['value'] || $row->comment !== $entry['comment']) {
                    $store->update($row, ['value' => $entry['value'], 'comment' => $entry['comment']]);
                    $updated++;
                }
            }

            return new SaveResult($added, $removed->count(), $updated, $parsed);
        });
    }

    /**
     * @return Collection<int, FilterListEntry>
     */
    private function rows(ListName $list): Collection
    {
        return FilterListEntry::query()->where('list', $list->value)->orderBy('id')->get();
    }

    /**
     * A cafe IP's special limits go with it (FR-010).
     */
    private function forgetCafeLimits(ListName $list, string $ip): void
    {
        if ($list !== ListName::NetCafeIps) {
            return;
        }

        FilterAreaRule::query()->where('rule', CafeLimits::RULE)->where('target', $ip)->get()
            ->each(fn (FilterAreaRule $rule) => $this->store->delete($rule));
    }
}
