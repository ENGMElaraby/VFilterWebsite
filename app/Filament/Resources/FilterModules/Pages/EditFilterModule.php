<?php

namespace App\Filament\Resources\FilterModules\Pages;

use App\Config\ConfigStore;
use App\Config\GuardAddressClashException;
use App\Config\InvalidModuleSetupException;
use App\Enums\Filter\CommandName;
use App\Filament\Resources\FilterModules\FilterModuleResource;
use App\Filter\CommandQueue;
use App\Filter\Exceptions\CommandInProgressException;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterModule;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Saves through ConfigStore (audited, versioned, ModuleRules checked on commit) and offers a
 * restart after address changes (spec 003, FR-018/FR-019).
 */
class EditFilterModule extends EditRecord
{
    protected static string $resource = FilterModuleResource::class;

    private const ADDRESS_FIELDS = ['module_ip', 'module_port', 'guard_ip', 'guard_port'];

    private bool $addressChanged = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var FilterModule $record */
        $before = $record->only(self::ADDRESS_FIELDS);

        try {
            $saved = app(ConfigStore::class)->update($record, $data);
        } catch (GuardAddressClashException $exception) {
            $record->refresh();
            $this->addError('data.guard_port', $exception->getMessage());

            throw new Halt;
        } catch (InvalidModuleSetupException $exception) {
            $record->refresh();
            Notification::make()->danger()->title($exception->getMessage())->send();

            throw new Halt;
        }

        $this->addressChanged = $saved->only(self::ADDRESS_FIELDS) != $before;

        return $saved;
    }

    protected function getSavedNotification(): ?Notification
    {
        /** @var FilterModule $record */
        $record = $this->getRecord();

        if (! $this->addressChanged) {
            return parent::getSavedNotification();
        }

        return Notification::make()
            ->success()
            ->title(__('panel.modules.restart_needed', ['name' => $record->name]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('restartModule')
                ->label(__('panel.modules.restart_now'))
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => __('panel.commands.confirm_restart', ['target' => $this->getRecord()->getAttribute('name')]))
                ->visible(fn (): bool => Gate::allows('create', FilterCommand::class) && (bool) $this->getRecord()->getAttribute('enabled'))
                ->action(function (): void {
                    /** @var FilterModule $record */
                    $record = $this->getRecord();

                    try {
                        app(CommandQueue::class)->queue(CommandName::Restart, strtolower($record->name));
                        Notification::make()->success()->title(__('panel.commands.queued', [
                            'command' => __('panel.commands.names.restart'),
                            'target' => $record->name,
                        ]))->send();
                    } catch (CommandInProgressException|InvalidArgumentException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                    }
                }),
        ];
    }
}
