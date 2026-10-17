<?php

namespace App\Filament\Pages\Security;

use App\Lists\CafeLimits;
use App\Lists\ListName;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/**
 * Net cafe IPs plus per-IP special limits (spec 005 US1 + US3).
 */
class NetCafeIps extends SecurityListPage
{
    protected static ?string $slug = 'security/net-cafe-ips';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 7;

    public static function list(): ListName
    {
        return ListName::NetCafeIps;
    }

    protected function getHeaderActions(): array
    {
        return [...parent::getHeaderActions(), $this->limitsAction()];
    }

    private function limitsAction(): Action
    {
        $fields = array_map(
            fn (string $field): TextInput => TextInput::make($field)->label(__('panel.cafe.fields.'.$field))
                ->integer()->required()->default(0)->minValue(CafeLimits::UNLIMITED)->maxValue(CafeLimits::MAX),
            CafeLimits::FIELDS,
        );

        return Action::make('cafeLimits')
            ->label(__('panel.cafe.action'))
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->visible(fn (): bool => static::canEdit())
            ->modalDescription(__('panel.cafe.help'))
            ->schema([
                Select::make('ip')->label(__('panel.cafe.ip'))->required()
                    ->options(fn (CafeLimits $limits): array => array_combine($limits->cafeIps(), $limits->cafeIps()) ?: [])
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        foreach (app(CafeLimits::class)->get((string) $state) as $field => $value) {
                            $set($field, $value);
                        }
                    }),
                Grid::make(['default' => 1, 'sm' => 2])->schema($fields),
            ])
            ->action(function (array $data, CafeLimits $limits): void {
                try {
                    $limits->set((string) $data['ip'], $data);
                    Notification::make()->success()->title(__('panel.cafe.saved', ['ip' => $data['ip']]))->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                }
            });
    }
}
