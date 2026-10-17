<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\AuditLogger;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

/**
 * Forced password change after the owner created or reset an account (FR-013).
 *
 * @property-read Schema $form
 */
class ChangePassword extends Page
{
    protected static ?string $slug = 'change-password';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        if (! $this->user()->must_change_password) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function getTitle(): string
    {
        return __('panel.change_password.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('current_password')
                    ->label(__('panel.change_password.current'))
                    ->password()
                    ->required()
                    ->currentPassword(),
                TextInput::make('password')
                    ->label(__('panel.change_password.new'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->confirmed()
                    ->different('current_password')
                    ->rule(Password::defaults()),
                TextInput::make('password_confirmation')
                    ->label(__('panel.change_password.confirm'))
                    ->password()
                    ->revealable()
                    ->required(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('panel.change_password.submit'))->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = $this->user();
        $audit = app(AuditLogger::class);

        $audit->transaction(function () use ($audit, $user, $data): void {
            $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->saveQuietly();
            $audit->log('user.password_changed', $user);
        });

        Notification::make()->title(__('panel.change_password.done'))->success()->send();

        $this->redirect(Filament::getUrl());
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
