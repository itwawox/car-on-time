<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    public function getTitle(): string
    {
        return 'Заявка №'.$this->getRecord()->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('call')->label('Позвонить')->icon(Heroicon::OutlinedPhone)->color('gray')
                ->url(fn (Booking $record) => 'tel:'.preg_replace('/[^\d+]/', '', $record->phone)),
            DeleteAction::make(),
        ];
    }

    /** Подтвердить бронь на занятую машину нельзя: сначала другая машина или даты. */
    protected function beforeSave(): void
    {
        $state = $this->form->getState();
        if (($state['status'] ?? null) !== 'confirmed') {
            return;
        }

        $draft = $this->getRecord()->replicate()->fill($state);
        $draft->id = $this->getRecord()->getKey();
        $draft->unsetRelation('car');

        if ($conflict = BookingResource::conflicts($draft)) {
            Notification::make()->title('Машина занята на эти даты')->body($conflict)->danger()->persistent()->send();
            $this->halt();
        }
    }
}
