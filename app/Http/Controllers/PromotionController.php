<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use App\Models\Setting;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(): View
    {
        $title = Setting::get('promotions_title') ?: 'Акции';

        return view('promotions.index', [
            'promotions' => Promotion::query()->active()->get(),
            'heading' => $title,
            'title' => $title.' на аренду авто в Крыму | '.Setting::get('brand_name', 'Car on Time'),
            'description' => Setting::get('promotions_description') ?: 'Действующие акции и промокоды на аренду автомобилей в Крыму.',
            'crumbs' => [['name' => 'Главная', 'url' => route('home')], ['name' => $title]],
        ]);
    }

    public function show(string $slug): View
    {
        $promotion = Promotion::query()->active()->where('slug', $slug)->firstOrFail();
        $list = Setting::get('promotions_title') ?: 'Акции';

        return view('promotions.show', [
            'promotion' => $promotion,
            'title' => $promotion->seo_title ?: $promotion->title.' | '.Setting::get('brand_name', 'Car on Time'),
            'description' => $promotion->seo_description ?: $promotion->excerpt,
            'ogImage' => $promotion->cover ? url($promotion->coverUrl()) : null,
            'crumbs' => [['name' => 'Главная', 'url' => route('home')], ['name' => $list, 'url' => route('promotions')], ['name' => $promotion->title]],
        ]);
    }
}
