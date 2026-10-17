<?php

namespace App\Filament\Resources\GameAssistants\Pages;

use App\Filament\Resources\GameAssistants\GameAssistantResource;
use App\Lists\GameAssistants;
use App\Models\Filter\FilterGaPermission;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ListGameAssistants extends ListRecords
{
    protected static string $resource = GameAssistantResource::class;

    public function getSubheading(): string
    {
        return __('panel.assistants.help');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')
                ->label(__('panel.assistants.add'))
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => Gate::allows('create', FilterGaPermission::class))
                ->schema([TextInput::make('username')->label(__('panel.assistants.username'))->required()->maxLength(32)])
                ->action(function (array $data, Action $action): void {
                    try {
                        app(GameAssistants::class)->add((string) $data['username']);
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                        $action->halt();
                    }
                }),
        ];
    }
}
