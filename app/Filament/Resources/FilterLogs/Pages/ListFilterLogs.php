<?php

namespace App\Filament\Resources\FilterLogs\Pages;

use App\Filament\Resources\FilterLogs\FilterLogResource;
use App\Filament\Resources\FilterLogs\Tables\FilterLogsTable;
use App\Logs\LogExporter;
use App\Logs\LogPruner;
use App\Logs\LogRetention;
use App\Models\Filter\FilterLog;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Log search with Live tail, CSV export and the retention setting (spec 004, US1-US4).
 */
class ListFilterLogs extends ListRecords
{
    protected static string $resource = FilterLogResource::class;

    private const LIVE_INTERVAL = '5s';

    public bool $live = false;

    public bool $unavailable = false;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->poll(fn (): ?string => $this->live ? self::LIVE_INTERVAL : null)
            ->emptyStateHeading(fn (): ?string => $this->unavailable ? __('panel.dashboard.database_unavailable') : null);
    }

    /**
     * Live only follows the first page, so a page being read never shifts (FR-005).
     */
    public function updatedPaginators(int|string $page, string $pageName): void
    {
        if ((int) $page > 1) {
            $this->live = false;
        }
    }

    /**
     * An unreachable VFILTER shows an empty table with the banner instead of an error page.
     *
     * @return Collection<int, FilterLog>|Paginator<int, FilterLog>|CursorPaginator<int, FilterLog>
     */
    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        try {
            return parent::getTableRecords();
        } catch (QueryException) {
            $this->unavailable = true;

            return new Collection;
        }
    }

    public function getSubheading(): ?string
    {
        $run = rescue(fn () => LogPruner::lastRun(), null, report: false);

        if ($run === null) {
            return __('panel.logs.never_pruned');
        }

        $values = $run->new_values ?? [];
        $key = ($values['complete'] ?? true) ? 'panel.logs.last_prune' : 'panel.logs.last_prune_incomplete';

        return __($key, [
            'time' => $run->created_at->setTimezone((string) config('vfilter.display_timezone'))->format('Y-m-d H:i'),
            'deleted' => (int) ($values['deleted'] ?? 0),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('live')
                ->label(fn (): string => $this->live ? __('panel.logs.pause') : __('panel.logs.live'))
                ->icon(fn () => $this->live ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
                ->color(fn (): string => $this->live ? 'success' : 'gray')
                ->action(function (): void {
                    $this->live = ! $this->live;

                    if ($this->live) {
                        $this->resetPage($this->getTablePaginationPageName());
                    }
                }),
            Action::make('export')
                ->label(__('panel.logs.export'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (LogExporter $exporter): StreamedResponse {
                    $filters = FilterLogsTable::filters($this->tableFilters[FilterLogsTable::SEARCH_FILTER] ?? []);

                    if ($exporter->wouldTruncate($filters)) {
                        Notification::make()->warning()->title(__('panel.logs.export_truncated', ['max' => number_format($exporter->maxRows())]))->send();
                    }

                    return $exporter->stream($filters, (string) config('vfilter.display_timezone'));
                }),
            $this->retentionAction(),
        ];
    }

    private function retentionAction(): Action
    {
        return Action::make('retention')
            ->label(__('panel.retention.action'))
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->visible(fn (): bool => Gate::allows('manage-filter-settings'))
            ->fillForm(fn (LogRetention $retention): array => ['days' => $retention->days()])
            ->schema([
                TextInput::make('days')
                    ->label(__('panel.retention.days'))
                    ->helperText(__('panel.retention.help'))
                    ->required()
                    ->integer()
                    ->minValue(LogRetention::MIN_DAYS)
                    ->maxValue(LogRetention::MAX_DAYS)
                    ->live(debounce: 500),
                Text::make(fn (Get $get): string => __('panel.retention.shorter_confirm', ['days' => (int) $get('days')]))
                    ->color('danger')
                    ->visible(fn (Get $get): bool => (int) $get('days') > 0 && (int) $get('days') < app(LogRetention::class)->days()),
            ])
            ->action(function (array $data, LogRetention $retention): void {
                $retention->set((int) $data['days']);
                Notification::make()->success()->title(__('panel.retention.saved', ['days' => (int) $data['days']]))->send();
            });
    }
}
