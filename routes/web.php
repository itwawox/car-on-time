<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Bitrix24WebhookController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingDocumentsController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FavoritesController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Support\Seo\IndexNow;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/katalog', [CatalogController::class, 'index'])->name('catalog');
Route::get('/katalog/count', [CatalogController::class, 'count'])->middleware('throttle:120,1')->name('catalog.count');
Route::get('/katalog/page/{page}', [CatalogController::class, 'index'])->whereNumber('page')->name('catalog.page');
Route::get('/klass/{slug}', [CatalogController::class, 'klass'])->name('klass');
Route::get('/klass/{slug}/page/{page}', [CatalogController::class, 'klass'])->whereNumber('page')->name('klass.page');
Route::get('/kuzov/{slug}', [CatalogController::class, 'kuzov'])->name('kuzov');
Route::get('/kuzov/{slug}/page/{page}', [CatalogController::class, 'kuzov'])->whereNumber('page')->name('kuzov.page');
Route::get('/korobka/{slug}', [CatalogController::class, 'korobka'])->name('korobka');
Route::get('/korobka/{slug}/page/{page}', [CatalogController::class, 'korobka'])->whereNumber('page')->name('korobka.page');
Route::get('/marka/{slug}', [CatalogController::class, 'marka'])->name('marka');
Route::get('/marka/{slug}/page/{page}', [CatalogController::class, 'marka'])->whereNumber('page')->name('marka.page');
Route::get('/arenda-avto-v-{slug}', CityController::class)->name('city')->where('slug', '[A-Za-z0-9-]+');
Route::get('/arenda-avto-v-{slug}/page/{page}', CityController::class)->whereNumber('page')->where('slug', '[A-Za-z0-9-]+')->name('city.page');
Route::get('/avto/{slug}', [CarController::class, 'show'])->name('car.show');
Route::get('/poisk', [SearchController::class, 'index'])->name('search');
Route::get('/poisk/podskazki', [SearchController::class, 'suggest'])->middleware('throttle:120,1')->name('search.suggest');
Route::get('/podbor', [QuizController::class, 'show'])->name('quiz');
Route::get('/podbor/rezultat', [QuizController::class, 'result'])->name('quiz.result');
Route::get('/podbor/count', [QuizController::class, 'count'])->middleware('throttle:120,1')->name('quiz.count');
Route::get('/podbor/pohozhie', [QuizController::class, 'similar'])->middleware('throttle:60,1')->name('quiz.similar');
Route::get('/stati', [ArticleController::class, 'index'])->name('articles');
Route::get('/stati/page/{page}', [ArticleController::class, 'index'])->whereNumber('page')->name('articles.page');
Route::get('/stati/{slug}', [ArticleController::class, 'show'])->name('article');
// Раздел «Гиды» стал «Статьями»
Route::permanentRedirect('/gid', '/stati');
Route::get('/gid/{slug}', fn (string $slug) => redirect('/stati/'.$slug, 301));
Route::get('/sravnenie', CompareController::class)->name('compare');
Route::get('/izbrannoe', FavoritesController::class)->name('favorites');
Route::get('/akcii', [PromotionController::class, 'index'])->name('promotions');
Route::get('/akcii/{slug}', [PromotionController::class, 'show'])->name('promotion');
Route::post('/obratnyj-zvonok', [LeadController::class, 'store'])->middleware('throttle:6,10')->name('lead.store');
Route::get('/otzyvy', [ReviewController::class, 'index'])->name('reviews');
Route::post('/otzyvy', [ReviewController::class, 'store'])->middleware('throttle:5,10')->name('reviews.store');
Route::get('/faq', FaqController::class)->name('faq');
Route::post('/zayavka', [BookingController::class, 'store'])->name('booking.store');
Route::post('/zayavka/{booking}/detali', [BookingController::class, 'details'])->middleware(['signed', 'throttle:10,1'])->name('booking.details');
Route::get('/zayavka/{booking}/spasibo', [BookingController::class, 'thanks'])->middleware('signed')->name('booking.thanks');
// Личный кабинет клиента: вход по коду из SMS или со страницы заявки
Route::get('/kabinet', [CabinetController::class, 'index'])->name('cabinet');
Route::post('/kabinet/kod', [CabinetController::class, 'sendCode'])->middleware('throttle:5,1')->name('cabinet.code');
Route::post('/kabinet/vhod', [CabinetController::class, 'verifyCode'])->middleware('throttle:10,1')->name('cabinet.verify');
Route::post('/kabinet/vyhod', [CabinetController::class, 'logout'])->name('cabinet.logout');
Route::post('/kabinet/{booking}/prodlit', [CabinetController::class, 'extend'])->middleware('throttle:10,1')->name('cabinet.extend');
Route::post('/b/{token}/kabinet', [CabinetController::class, 'fromBooking'])->where('token', '[a-z0-9]{12}')->middleware('throttle:10,1')->name('cabinet.from-booking');
Route::post('/b/{token}/dokumenty', [BookingDocumentsController::class, 'upload'])->where('token', '[a-z0-9]{12}')->middleware('throttle:10,1')->name('booking.documents');
Route::get('/b/{token}/akt/{media}', [BookingDocumentsController::class, 'actPhoto'])->where('token', '[a-z0-9]{12}')->name('booking.act-photo');
Route::get('/b/{token}', [BookingController::class, 'status'])->where('token', '[a-z0-9]{12}')->middleware('throttle:30,1')->name('booking.status');
Route::post('/b/{token}/oplata', [PaymentController::class, 'pay'])->where('token', '[a-z0-9]{12}')->middleware('throttle:10,1')->name('booking.pay');
Route::get('/zayavka/{booking}/calendar.ics', [BookingController::class, 'calendar'])->middleware('signed')->name('booking.calendar');
Route::get('/quote', QuoteController::class)->name('quote');
Route::get('/quote/batch', [QuoteController::class, 'batch'])->middleware('throttle:60,1')->name('quote.batch');
// Исходящий вебхук Битрикс24: смена стадии сделки → статус брони
Route::post('/integrations/bitrix24/webhook', Bitrix24WebhookController::class)->middleware('throttle:120,1')->name('integrations.bitrix24.webhook');
// Уведомления ЮKassa о платежах
Route::post('/integrations/yookassa/webhook', [PaymentController::class, 'webhook'])->middleware('throttle:120,1')->name('integrations.yookassa.webhook');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/site.webmanifest', ManifestController::class)->name('manifest');
// Файл-подтверждение ключа IndexNow
Route::get('/{key}.txt', function (string $key) {
    abort_unless(hash_equals(IndexNow::key(), $key), 404);

    return response($key, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->where('key', '[a-z0-9]{32}')->name('indexnow.key');
Route::get('/{slug}', [PageController::class, 'show'])->name('page')
    ->where('slug', 'usloviya|kontakty|politika-konfidencialnosti|soglasie-pdn|yurlicam|sdat-avto|cookie|rekomendatelnye-tehnologii|polzovatelskoe-soglashenie');
