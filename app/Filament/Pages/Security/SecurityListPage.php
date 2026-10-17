<?php

namespace App\Filament\Pages\Security;

use App\Lists\InvalidListException;
use App\Lists\IpRange;
use App\Lists\ListChangedException;
use App\Lists\ListEditor;
use App\Lists\ListName;
use App\Lists\ListParser;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

    /** @var array{text?: mixed, rows?: mixed} */
    public array $data = [];

    public string $fingerprint = '';

    abstract public static function list(): ListName;

    /**
     * Short lists are edited as rows (value + comment, add/remove); long ones as pasted text.
     */
    public static function usesRows(): bool
    {
        return false;
    }

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
            ->components([static::usesRows() ? $this->rowsField() : $this->textField()])
            ->statePath('data');
    }

    private function rowsField(): Repeater
    {
        return Repeater::make('rows')
            ->hiddenLabel()
            ->helperText(__('panel.lists.live'))
            ->schema([
                TextInput::make('value')
                    ->label(__('panel.lists.value'))
                    ->placeholder(__('panel.lists.placeholders.'.static::list()->format()->value))
                    ->required()
                    ->maxLength(static::list()->format()->maxLength())
                    ->extraInputAttributes(['dir' => 'ltr', 'spellcheck' => 'false']),
                TextInput::make('comment')
                    ->label(__('panel.lists.comment'))
                    ->maxLength(ListParser::COMMENT_MAX_LENGTH),
            ])
            ->table([
                TableColumn::make(__('panel.lists.value'))->markAsRequired(),
                TableColumn::make(__('panel.lists.comment')),
            ])
            ->compact()
            ->defaultItems(0)
            ->reorderable(false)
            ->addActionLabel(__('panel.lists.add_row'))
            ->addable(fn (): bool => static::canEdit())
            ->deletable(fn (): bool => static::canEdit())
            ->disabled(fn (): bool => ! static::canEdit());
    }

    private function textField(): Textarea
    {
        return Textarea::make('text')
            ->label(__('panel.lists.field'))
            ->helperText(__('panel.lists.live'))
            ->rows(self::TEXTAREA_ROWS)
            ->extraInputAttributes(['dir' => 'auto', 'spellcheck' => 'false', 'class' => 'font-mono'])
            ->disabled(fn (): bool => ! static::canEdit());
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
        $text = $this->currentText();

        if ($text === null) {
            return;
        }

        if (static::usesRows()) {
            $this->form->validate();
        }

        try {
            $result = app(ListEditor::class)->save(static::list(), $text, $this->fingerprint);
        } catch (InvalidListException $exception) {
            $this->showErrors($exception);

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
        $this->form->fill(static::usesRows()
            ? ['rows' => $editor->entries(static::list())]
            : ['text' => $editor->text(static::list())]);
    }

    /**
     * The form as "value // comment" lines; in row mode line N is row N. Null for a bad payload.
     */
    private function currentText(): ?string
    {
        if (! static::usesRows()) {
            $text = $this->data['text'] ?? '';

            return is_string($text) ? $text : null;
        }

        $rows = $this->data['rows'] ?? [];

        if (! is_array($rows)) {
            return null;
        }

        return implode("\n", array_map(function (mixed $row): string {
            $value = is_array($row) && is_string($row['value'] ?? null) ? trim($row['value']) : '';
            $comment = is_array($row) && is_string($row['comment'] ?? null) ? trim($row['comment']) : '';

            // Keep one line per row (even empty) so parser line numbers point at rows.
            return $comment === '' || $value === '' ? $value : $value.' // '.$comment;
        }, array_values($rows)));
    }

    private function showErrors(InvalidListException $exception): void
    {
        if (! static::usesRows()) {
            $this->addError('data.text', $exception->getMessage());

            return;
        }

        $keys = array_keys(is_array($this->data['rows'] ?? null) ? $this->data['rows'] : []);

        foreach ($exception->parsed->errors as $error) {
            $key = $keys[$error['line'] - 1] ?? null;

            $key === null
                ? Notification::make()->danger()->title($error['reason'])->send()
                : $this->addError("data.rows.{$key}.value", $error['reason']);
        }
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

        $after = app(ListParser::class)->parse(ListName::AllowedIps, $this->currentText() ?? '');

        return ! collect($after->entries)->contains(fn (array $entry): bool => IpRange::contains($entry['value'], $ip));
    }
}
