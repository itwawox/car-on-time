<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::query()->where('slug', $slug)->where('is_published', true)->firstOrFail();

        return view('page', [
            'page' => $page,
            'crumbs' => [
                ['name' => 'Главная', 'url' => route('home')],
                ['name' => $page->title],
            ],
        ]);
    }
}
