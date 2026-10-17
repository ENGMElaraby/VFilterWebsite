<?php

namespace App\Filament\Pages\Security;

use App\Lists\InvalidListException;
use App\Lists\IpRange;
use App\Lists\ListChangedException;
use App\Lists\ListEditor;
use App\Lists\ListName;
use App\Lists\ListParser;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * One security list edited as "value // comment" lines (spec 005 US1). Subclasses pick the list.
 *
 * @property-read Schema $form
 */
abstract class SecurityListPage extends Page
{
    protected string $view = 'filament.pages.security-list';

    private const TEXTAREA_ROWS = 18;

    /** @var array{text?: string} */
    public array $data = [];

    public string $fingerprint = '';

    abstract public static function list(): ListName;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.security');
    }

    public static function getNavigationLabel(): string
    {
        return static::list()->label();
    }

    public function getTitle(): string
    {
        return static::list()->label();
    }

    public function getSubheading(): string
    {
        return static::list()->help().' '.__('panel.lists.count', ['count' => app(ListEditor::class)->count(static::list())]);
    }

    public function mount(): void
    {
        $this->load();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('text')
                    ->label(__('panel.lists.field'))
                    ->helperText(__('panel.lists.live'))
                    ->rows(self::TEXTAREA_ROWS)
                    ->extraInputAttributes(['dir' => 'auto', 'spellcheck' => 'false', 'class' => 'font-mono'])
                    ->disabled(fn (): bool => ! static::canEdit()),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('panel.lists.save'))
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (): bool => static::canEdit())
                ->requiresConfirmation()
                // A description alone opens the modal, so hide it unless the save would lock the admin out.
                ->modalHidden(fn (): bool => ! $this->removesOwnIp())
                ->modalDescription(fn (): string => __('panel.lists.own_ip_warning', ['ip' => request()->ip()]))
                ->action(fn () => $this->save()),
            Action::make('reload')
                ->label(__('panel.lists.reload'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => $this->load()),
        ];
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $text = (string) ($this->data['text'] ?? '');

        try {
            $result = app(ListEditor::class)->save(static::list(), $text, $this->fingerprint);
        } catch (InvalidListException $exception) {
            $this->addError('data.text', $exception->getMessage());

            return;
        } catch (ListChangedException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->persistent()->send();

            return;
        }

        $notes = array_filter([
            $result->parsed->duplicates > 0 ? __('panel.lists.duplicates', ['count' => $result->parsed->duplicates]) : null,
            ...array_map(fn (array $n): string => __('panel.lists.normalised', $n), $result->parsed->normalised),
        ]);

        Notification::make()->success()
            ->title($result->changed()
                ? __('panel.lists.saved', ['added' => $result->added, 'removed' => $result->removed, 'updated' => $result->updated])
                : __('panel.lists.unchanged'))
            ->body($notes === [] ? null : implode("\n", $notes))
            ->send();

        $this->load();
    }

    public static function canEdit(): bool
    {
        return Gate::allows('manage-filter-settings');
    }

    private function load(): void
    {
        $editor = app(ListEditor::class);
        $this->fingerprint = $editor->fingerprint(static::list());
        $this->form->fill(['text' => $editor->text(static::list())]);
    }

    /**
     * Saving Allowed GM IPs without the admin's own address could lock them out (FR-006).
     */
    private function removesOwnIp(): bool
    {
        if (static::list() !== ListName::AllowedIps) {
            return false;
        }

        $ip = (string) request()->ip();
        $before = explode("\n", app(ListEditor::class)->text(ListName::AllowedIps));
        $wasAllowed = collect($before)->contains(fn (string $line): bool => IpRange::contains(trim(explode('//', $line)[0]), $ip));

        if (! $wasAllowed) {
            return false;
        }

        $after = app(ListParser::class)->parse(ListName::AllowedIps, (string) ($this->data['text'] ?? ''));

        return ! collect($after->entries)->contains(fn (array $entry): bool => IpRange::contains($entry['value'], $ip));
    }
}
