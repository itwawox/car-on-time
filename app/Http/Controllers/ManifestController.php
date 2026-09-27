<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $brand = Setting::get('brand_name', 'Car on Time');

        return response()->json([
            'name' => $brand.' — аренда авто в Крыму',
            'short_name' => $brand,
            'lang' => 'ru',
            'start_url' => '/',
            'display' => 'browser',
            'background_color' => '#f2f5f7',
            'theme_color' => '#0f3344',
            'icons' => [
                ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
