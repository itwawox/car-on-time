<?php

namespace App\Http\Controllers;

use App\Support\Seo\SeoSettings;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        // Тестовые стенды и локальная копия не должны попасть в индекс как дубль сайта
        $body = app()->isProduction()
            ? rtrim((string) SeoSettings::indexing()['robots'])."\n\nSitemap: ".route('sitemap')."\n"
            : "User-agent: *\nDisallow: /\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
