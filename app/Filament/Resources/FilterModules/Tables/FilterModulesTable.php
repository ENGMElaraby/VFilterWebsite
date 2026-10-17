<?php

namespace App\Filament\Resources\FilterModules\Tables;

use App\Config\InvalidModuleSetupException;
use App\Config\ModuleSlots;
use App\Enums\Filter\ModuleType;
use App\Models\Filter\FilterModule;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FilterModulesTable
{
    public static function configure(Table $table): Table
    {
        $canSeeAddresses = Gate::allows('viewAddresses', FilterModule::class);
        $masked = fn (mixed $state): string => $canSeeAddresses ? (string) $state : __('panel.modules.masked');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderByRaw(self::orderSql()))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label(__('panel.modules.fields.name')),
                TextColumn::make('type')
                    ->label(__('panel.modules.fields.type'))
                    ->formatStateUsing(fn (ModuleType $state): string => __('panel.modules.types.'.$state->value)),
                TextColumn::make('module_ip')->label(__('panel.modules.fields.module_ip'))->formatStateUsing($masked),
                TextColumn::make('module_port')->label(__('panel.modules.fields.module_port'))->formatStateUsing($masked),
                TextColumn::make('guard_ip')->label(__('panel.modules.fields.guard_ip')),
                TextColumn::make('guard_port')->label(__('panel.modules.fields.guard_port')),
                IconColumn::make('enabled')->label(__('panel.modules.fields.enabled'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('removeAgent')
                    ->label(__('panel.modules.remove_agent'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn (FilterModule $record): string => __('panel.modules.remove_confirm', ['name' => $record->name]))
                    ->visible(fn (FilterModule $record): bool => Gate::allows('delete', $record) && app(ModuleSlots::class)->isLastAgent($record))
                    ->action(function (FilterModule $record): void {
                        try {
                            app(ModuleSlots::class)->removeLastAgent();
                            Notification::make()->success()->title(__('panel.modules.agent_removed', ['name' => $record->name]))->send();
                        } catch (InvalidModuleSetupException $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();
                        }
                    }),
            ]);
    }

    /**
     * Gateway, Download, then Agent1..AgentN (numeric order, so Agent10 would not sort before Agent2).
     */
    private static function orderSql(): string
    {
        $length = DB::connection('filter')->getDriverName() === 'sqlsrv' ? 'LEN' : 'LENGTH';

        return "CASE type WHEN 'gateway' THEN 0 WHEN 'download' THEN 1 ELSE 2 END, {$length}(name), name";
    }
}
