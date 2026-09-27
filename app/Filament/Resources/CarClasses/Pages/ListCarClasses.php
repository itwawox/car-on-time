<?php

namespace App\Filament\Resources\CarClasses\Pages;

use App\Filament\Resources\CarClasses\CarClassResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCarClasses extends ListRecords
{
    protected static string $resource = CarClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
