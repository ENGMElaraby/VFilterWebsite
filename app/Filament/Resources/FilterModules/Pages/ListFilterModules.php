<?php

namespace App\Filament\Resources\FilterModules\Pages;

use App\Config\InvalidModuleSetupException;
use App\Config\ModuleSlots;
use App\Filament\Resources\FilterModules\FilterModuleResource;
use App\Models\Filter\FilterModule;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ListFilterModules extends ListRecords
{
    protected static string $resource = FilterModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addAgent')
                ->label(__('panel.modules.add_agent'))
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => Gate::allows('create', FilterModule::class) && app(ModuleSlots::class)->canAddAgent())
                ->action(function (): void {
                    try {
                        $agent = app(ModuleSlots::class)->addAgent();
                        Notification::make()->success()->title(__('panel.modules.agent_added', ['name' => $agent->name]))->send();
                    } catch (InvalidModuleSetupException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                    }
                }),
        ];
    }
}
