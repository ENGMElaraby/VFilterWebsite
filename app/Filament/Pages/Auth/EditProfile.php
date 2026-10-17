<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PanelLocale;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use SensitiveParameter;

/**
 * Filament profile plus language and theme, and an audit entry for password changes.
 * Filament already asks for the current password before an email or password change.
 */
class EditProfile extends BaseEditProfile
{
    private bool $passwordChanged = false;

    private bool $localeChanged = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
            Select::make('locale')
                ->label(__('panel.profile.locale'))
                ->options(collect(PanelLocale::SUPPORTED)->mapWithKeys(
                    fn (string $locale): array => [$locale => PanelLocale::name($locale)],
                ))
                ->required(),
            Select::make('theme')
                ->label(__('panel.profile.theme'))
                ->options([
                    'light' => __('panel.profile.themes.light'),
                    'dark' => __('panel.profile.themes.dark'),
                    'system' => __('panel.profile.themes.system'),
                ])
                ->required(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(#[SensitiveParameter] array $data): array
    {
        $this->passwordChanged = filled($data['password'] ?? null);
        $this->localeChanged = ($data['locale'] ?? null) !== $this->user()->locale;

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->passwordChanged) {
            app(AuditLogger::class)->log('user.password_changed', $this->user());
        }
    }

    /**
     * Reload after a language change so the whole page switches language and direction.
     */
    protected function getRedirectUrl(): ?string
    {
        return $this->localeChanged ? static::getUrl() : null;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
