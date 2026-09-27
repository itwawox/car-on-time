<?php

namespace App\Filament\Resources\CarClasses\Pages;

use App\Filament\Resources\CarClasses\CarClassResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCarClass extends EditRecord
{
    protected static string $resource = CarClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
