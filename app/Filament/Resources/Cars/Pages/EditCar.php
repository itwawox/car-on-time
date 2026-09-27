<?php

namespace App\Filament\Resources\Cars\Pages;

use App\Filament\Resources\Cars\CarResource;
use App\Models\Car;
use App\Support\CarDescription;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditCar extends EditRecord
{
    protected static string $resource = CarResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label('На сайте')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn () => route('car.show', $this->record->slug), true),
            Action::make('describe')
                ->label('Пересобрать описание')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Описание будет собрано заново из справочника модели и текущих характеристик. Сначала сохраните изменения характеристик.')
                ->action(function () {
                    /** @var Car $car */
                    $car = $this->record->fresh();
                    $car->forceFill(['description' => CarDescription::build($car), 'description_generated_at' => now()])->save();
                    $this->refreshFormData(['description']);
                    Notification::make()->title('Описание пересобрано')->success()->send();
                }),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Описание поправили руками — автосборка (cars:describe) больше его не трогает
        // (сравниваем видимый текст — редактор при сохранении переформатирует HTML)
        $plain = fn (?string $html) => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html))));
        if ($plain($data['description'] ?? null) !== $plain($this->record->description)) {
            $data['description_generated_at'] = null;
        }

        return $data;
    }
}
