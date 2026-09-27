<?php

namespace App\Filament\Resources\SearchSynonyms\Pages;

use App\Filament\Resources\SearchSynonyms\SearchSynonymResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSearchSynonym extends CreateRecord
{
    protected static string $resource = SearchSynonymResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['term'] = mb_strtolower(trim($data['term']));

        return $data;
    }

    public function getTitle(): string
    {
        return 'Новый синоним';
    }
}
