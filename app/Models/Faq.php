<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'question', 'answer', 'link_url', 'link_label', 'group', 'is_featured', 'show_on_car', 'is_published', 'sort'])]
class Faq extends Model
{
    /** Разделы в порядке показа. Названия можно переопределить в «Настройки сайта». */
    public const GROUPS = [
        'rules' => 'Требования и документы',
        'booking' => 'Бронирование и оплата',
        'deposit' => 'Залог и страховка',
        'pickup' => 'Получение, доставка и возврат',
        'trip' => 'В поездке',
        'incidents' => 'ДТП, поломки и штрафы',
        'business' => 'Юрлицам',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'show_on_car' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderBy('sort')->orderBy('id');
    }

    public function anchor(): string
    {
        return 'q-'.($this->slug ?: $this->id);
    }

    public static function groupLabel(string $key): string
    {
        return (string) (Setting::get('faq_group_'.$key) ?: (self::GROUPS[$key] ?? $key));
    }
}
