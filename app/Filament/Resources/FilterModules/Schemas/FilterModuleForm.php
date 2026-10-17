<?php

namespace App\Filament\Resources\FilterModules\Schemas;

use App\Config\ModuleRules;
use App\Enums\Filter\ModuleType;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FilterModuleForm
{
    private const ADDRESS_MAX_LENGTH = 64;

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label(__('panel.modules.fields.name'))
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('type')
                        ->label(__('panel.modules.fields.type'))
                        ->formatStateUsing(fn (ModuleType|string|null $state): string => __('panel.modules.types.'.($state instanceof ModuleType ? $state->value : (string) $state)))
                        ->disabled()
                        ->dehydrated(false),
                    self::address('module_ip')->helperText(__('panel.modules.help.module_ip')),
                    self::port('module_port'),
                    self::address('guard_ip')->helperText(__('panel.modules.help.guard_ip')),
                    self::port('guard_port'),
                    Toggle::make('enabled')->label(__('panel.modules.fields.enabled')),
                ]),
        ]);
    }

    private static function address(string $field): TextInput
    {
        return TextInput::make($field)
            ->label(__('panel.modules.fields.'.$field))
            ->required()
            ->maxLength(self::ADDRESS_MAX_LENGTH)
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (! app(ModuleRules::class)->isAddress((string) $value)) {
                    $fail(__('panel.modules.errors.address'));
                }
            });
    }

    private static function port(string $field): TextInput
    {
        return TextInput::make($field)
            ->label(__('panel.modules.fields.'.$field))
            ->required()
            ->integer()
            ->minValue(ModuleRules::MIN_PORT)
            ->maxValue(ModuleRules::MAX_PORT);
    }
}
