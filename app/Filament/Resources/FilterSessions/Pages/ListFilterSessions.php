<?php

namespace App\Filament\Resources\FilterSessions\Pages;

use App\Filament\Resources\FilterSessions\FilterSessionResource;
use App\Models\Filter\FilterSession;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;

class ListFilterSessions extends ListRecords
{
    protected static string $resource = FilterSessionResource::class;

    public bool $unavailable = false;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->emptyStateHeading(fn (): ?string => $this->unavailable ? __('panel.dashboard.database_unavailable') : null);
    }

    /**
     * An unreachable VFILTER shows an empty table with the banner instead of an error page.
     *
     * @return Collection<int, FilterSession>|Paginator<int, FilterSession>|CursorPaginator<int, FilterSession>
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
}
