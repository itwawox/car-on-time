<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function __invoke(): View
    {
        $faqs = Faq::query()->published()->get();
        $groups = collect(Faq::GROUPS)->keys()
            ->merge($faqs->pluck('group')->unique()) // разделы, заведённые в админке вручную, — в конце
            ->unique()
            ->mapWithKeys(fn ($key) => [$key => $faqs->where('group', $key)->values()])
            ->filter(fn ($items) => $items->isNotEmpty());

        return view('faq', [
            'faqs' => $faqs,
            'groups' => $groups,
            'crumbs' => [
                ['name' => 'Главная', 'url' => route('home')],
                ['name' => 'Вопросы и ответы'],
            ],
        ]);
    }
}
