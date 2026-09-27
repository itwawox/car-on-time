<?php

namespace App\Filament\Resources\Leads;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\Leads\Pages\CreateLead;
use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Partners\PartnerResource;
use App\Models\Lead;
use App\Models\Partner;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeadResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoneArrowDownLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Заявки';

    protected static ?string $navigationLabel = 'Обращения';

    protected static ?string $modelLabel = 'обращение';

    protected static ?string $pluralModelLabel = 'Обращения';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function getNavigationBadge(): ?string
    {
        $count = Lead::query()->where('status', 'new')->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Обращение')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('type')->label('Тип')->options(Lead::TYPES)->required(),
                    Select::make('status')->label('Статус')->options(Lead::STATUSES)->required()->default('new'),
                    TextInput::make('phone')->label('Телефон')->tel()->required(),
                    TextInput::make('name')->label('Имя'),
                    TextInput::make('company')->label('Компания'),
                    TextInput::make('inn')->label('ИНН'),
                    TextEntry::make('details_lines')->label('О машине')->columnSpanFull()
                        ->state(fn (?Lead $record) => $record?->detailLines() ?? [])
                        ->listWithLineBreaks()->bulleted()
                        ->visible(fn (?Lead $record) => filled($record?->details)),
                    Textarea::make('message')->label('Сообщение')->rows(3)->columnSpanFull(),
                    TextInput::make('page')->label('Со страницы')->disabled()->columnSpanFull(),
                    Textarea::make('notes')->label('Заметки менеджера')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Когда')->dateTime('d.m H:i')->sortable(),
                TextColumn::make('type')->label('Тип')->badge()->formatStateUsing(fn ($state) => Lead::TYPES[$state] ?? $state)
                    ->color(fn ($state) => ['corporate' => 'info', 'owner' => 'success'][$state] ?? 'gray'),
                TextColumn::make('phone')->label('Телефон')->copyable()->searchable(),
                TextColumn::make('name')->label('Имя')->description(fn (Lead $l) => $l->company)->searchable(),
                TextColumn::make('message')->label('Сообщение')->limit(60)->wrap()->toggleable()
                    ->description(fn (Lead $l) => $l->details ? implode(' · ', $l->detailLines()) : null),
                TextColumn::make('status')->label('Статус')->badge()->formatStateUsing(fn ($state) => Lead::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['new' => 'warning', 'in_work' => 'info', 'done' => 'success'][$state] ?? 'gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(Lead::STATUSES),
                SelectFilter::make('type')->label('Тип')->options(Lead::TYPES),
            ])
            ->recordActions([
                Action::make('call')->label('')->tooltip('Позвонить')->icon(Heroicon::OutlinedPhone)
                    ->url(fn (Lead $l) => 'tel:'.preg_replace('/[^\d+]/', '', $l->phone)),
                Action::make('partner')->label('Создать партнёра')->icon(Heroicon::OutlinedUserPlus)->color('info')
                    ->visible(fn (Lead $l) => $l->type === 'owner')
                    ->requiresConfirmation()
                    ->modalDescription('Партнёр появится выключенным — включите его после осмотра машины и проверки документов.')
                    ->action(function (Lead $l, $livewire) {
                        $partner = self::createPartner($l);
                        Notification::make()->title('Партнёр создан')->success()->send();
                        $livewire->redirect(PartnerResource::getUrl('edit', ['record' => $partner]));
                    }),
                Action::make('done')->label('Закрыть')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (Lead $l) => $l->status !== 'done')
                    ->action(fn (Lead $l) => $l->update(['status' => 'done'])),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    /** Партнёр из заявки «Сдать авто»: выключен до осмотра, заявка — в работу */
    public static function createPartner(Lead $lead): Partner
    {
        $details = $lead->details ?? [];
        $name = $lead->name ?: ($details['car'] ?? null ? 'Владелец '.$details['car'] : 'Владелец '.$lead->phone);

        $partner = Partner::query()->create([
            'name' => $name,
            'phone' => $lead->phone,
            'is_active' => false,
            'notes' => trim(implode("\n", [
                'Из заявки «Сдать авто» от '.$lead->created_at?->format('d.m.Y').'.',
                ...$lead->detailLines(),
                $lead->message,
            ])),
        ]);
        $lead->update(['status' => 'in_work', 'notes' => trim(($lead->notes ? $lead->notes."\n" : '').'Создан партнёр #'.$partner->id)]);

        return $partner;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
            'create' => CreateLead::route('/create'),
            'edit' => EditLead::route('/{record}/edit'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'bookings';
    }
}
